<?php
// +----------------------------------------------------------------------
// | 前台 · 商品浏览（M2：在售列表/详情，含实时库存）
// +----------------------------------------------------------------------
declare (strict_types = 1);

namespace app\controller\front;

use app\common\model\Goods;
use app\common\model\Member;
use app\common\service\OrderService;
use mks\BaseController;

class GoodsController extends BaseController
{
    /**
     * 分类列表（前台导航：仅启用分类，含商品计数冗余）
     */
    public function cates()
    {
        $rows = \app\common\model\GoodsCategory::where('status', 1)
            ->order('sort desc, id asc')
            ->field('id,pid,name,goods_num')
            ->select()->toArray();
        return $this->ok($rows);
    }

    /**
     * 在售商品列表（分站上下文：可见范围过滤 + 分站售价；登录会员：权限白名单过滤 + 密价）
     */
    public function index()
    {
        $page  = max(1, (int) $this->request->param('page', 1));
        $limit = min(50, max(1, (int) $this->request->param('limit', 12)));
        $query = Goods::where('status', 1)
            ->whereIn('check_status', [3, 9])
            ->where('delete_time', 0);
        $vipSite = $this->request->vipSite ?? null;
        $memberId = (int) ($this->request->memberId ?? 0);
        if ($vipSite) {
            $query = \app\common\service\VipPriceService::applyVisibleScope($query, $vipSite);
        } else {
            // 主站只展示平台公共商品（跨站不可见）
            $query = $query->where('vip_id', 0);
        }
        // 商品权限白名单（M7-P2：受约束用户仅见授权商品；SQL 级过滤保证分页计数一致）
        $query = \app\common\service\UserPriceService::applyRightScope($query, $memberId > 0 ? $memberId : null);
        $keyword = (string) $this->request->param('keyword', '');
        if ($keyword !== '') {
            $query = $query->whereLike('goods_name', '%' . $keyword . '%');
        }
        $categoryId = (int) $this->request->param('category_id', 0);
        if ($categoryId > 0) {
            $query = $query->whereRaw("FIND_IN_SET({$categoryId}, category_ids)");
        }
        // 指定商品ID集合（DIY 商品楼层）
        $ids = (string) $this->request->param('ids', '');
        if ($ids !== '') {
            $idArr = array_filter(array_map('intval', explode(',', $ids)), fn ($v) => $v > 0);
            if ($idArr !== []) {
                $query = $query->whereIn('id', $idArr);
            }
        }
        $total = (clone $query)->count();
        $list  = $query->order('sort desc, id desc')->page($page, $limit)
            ->field('id,goods_no,goods_name,goods_short_name,goods_type,face_value,real_price,goods_img,goods_tags,ishot,stock_num,sales_num,virtual_num,recharge_time_type,recharge_time_value,start_count,end_count,num_count,vip_id')
            ->select()
            ->toArray();
        if ($vipSite) {
            $ovRows = \app\common\model\VipGoodsPrice::where('vip_id', (int) $vipSite->getAttr('id'))
                ->whereIn('goods_id', array_column($list, 'id'))
                ->where('status', 1)
                ->select()
                ->toArray();
            $overrides = array_column($ovRows, null, 'goods_id');
            foreach ($list as &$r) {
                $ov  = $overrides[(int) $r['id']] ?? null;
                $ovModel = null;
                if ($ov !== null) {
                    $ovModel = (new \app\common\model\VipGoodsPrice())->data($ov, true);
                }
                $p = \app\common\service\VipPriceService::price((string) $r['real_price'], $vipSite, $ovModel);
                $r['base_price']   = $p['base'];
                $r['real_price']   = $p['price'];
                $r['vip_mode']     = $p['mode'];
                $r['vip_add']      = $p['add'];
            }
            unset($r);
        }
        // 主站取价展示（P2-G：密价/定价模板/会员折扣统一来自 UserPriceService::quoteMap，与下单同源）
        if (!$vipSite) {
            $member = $memberId > 0 ? Member::findOrEmpty($memberId) : null;
            $quotes = \app\common\service\UserPriceService::quoteMap($list, ($member === null || $member->isEmpty()) ? null : $member);
            foreach ($list as &$r) {
                $hit = $quotes[(int) $r['id']] ?? null;
                if ($hit !== null && $hit['source'] !== \app\common\service\UserPriceService::SRC_BASE) {
                    $r['base_price']   = (string) $r['real_price'];
                    $r['real_price']   = $hit['price'];
                    $r['price_source'] = $hit['source'];
                    $r['price_tpl_id'] = $hit['tpl_id'];
                }
            }
            unset($r);
        }
        return $this->ok(['list' => $list, 'count' => $total]);
    }

    /**
     * 商品详情（含实时可售库存与数量档位；分站上下文调价；登录会员：权限白名单 + 密价）
     */
    public function read(int $id)
    {
        $goods = Goods::where('id', $id)->where('status', 1)->where('delete_time', 0)->findOrEmpty();
        if ($goods->isEmpty()) {
            return $this->fail('商品不存在或已下架');
        }
        $memberId = (int) ($this->request->memberId ?? 0);
        // 商品权限白名单（M7-P2：越权访问详情按不可见处理）
        if ($memberId > 0 && !\app\common\service\UserPriceService::isGoodsVisible((int) $goods->getAttr('id'), $memberId)) {
            return $this->fail('商品不存在或已下架');
        }
        // 分站可见性与三级定价
        $vipSite = $this->request->vipSite ?? null;
        $data    = $goods->toArray();
        if (!$vipSite && (int) $goods->getAttr('vip_id') !== 0) {
            return $this->fail('商品不存在或已下架');
        }
        if ($vipSite) {
            $p = \app\common\service\VipPriceService::resolve($goods, $vipSite);
            if (!$p['visible']) {
                return $this->fail('商品不存在或已下架');
            }
            $data['base_price'] = $p['base'];
            $data['real_price'] = $p['price'];
            $data['vip_add']    = $p['add'];
        } else {
            // 主站取价展示（P2-G：密价 > 定价模板 > 会员折扣 > 基准价，与下单同源）
            $member = $memberId > 0 ? Member::findOrEmpty($memberId) : null;
            $quote  = \app\common\service\UserPriceService::quote($goods, ($member === null || $member->isEmpty()) ? null : $member);
            if ($quote['source'] !== \app\common\service\UserPriceService::SRC_BASE) {
                $data['base_price']   = (string) $data['real_price'];
                $data['real_price']   = $quote['price'];
                $data['price_source'] = $quote['source'];
                $data['price_tpl_id'] = $quote['tpl_id'];
            }
        }
        // 下单输入项模板（P1-A：绑定 tpl_id 时由前台按模板渲染输入项；未绑定保持原有数量/账号输入）
        $data['tpl'] = null;
        $tplId = (int) $goods->getAttr('tpl_id');
        if ($tplId > 0) {
            $tpl = \app\common\model\GoodsTpl::findOrEmpty($tplId);
            if (!$tpl->isEmpty() && (int) $tpl->getAttr('status') === 1 && (int) $tpl->getAttr('delete_time') === 0) {
                $inputs = $tpl->getAttr('inputs');
                if (is_string($inputs)) {
                    $inputs = json_decode($inputs, true) ?: [];
                }
                $data['tpl'] = [
                    'id'     => $tplId,
                    'name'   => (string) $tpl->getAttr('name'),
                    'inputs' => is_array($inputs) ? array_values($inputs) : [],
                ];
            }
        }
        // 实时库存（卡密商品以卡库余量为准）
        $stockNum = (int) $goods->getAttr('stock_num');
        if ((int) $goods->getAttr('goods_type') === 1) {
            $stock = OrderService::cardStockOf($goods);
            if ($stock !== null) {
                $stockNum = (int) \think\facade\Db::name('card_cate')->where('id', (int) $stock->getAttr('card_cate_id'))->value('card_num');
            }
        }
        $data['stock_real'] = $stockNum;
        $data['num_options'] = array_values(array_filter(array_map('intval', explode(',', (string) $goods->getAttr('num_count')))));
        return $this->ok($data);
    }
}
