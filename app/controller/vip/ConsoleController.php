<?php
// +----------------------------------------------------------------------
// | 分站主控制台（M6）：概览/商品定价/订单/结算/抽成流水/配置/域名
// | 鉴权：MemberToken + VipOwnerAuth（member.vip_id 与 vip_site.uid 双校验）
// +----------------------------------------------------------------------
declare (strict_types = 1);

namespace app\controller\vip;

use app\common\model\Goods;
use app\common\model\Member;
use app\common\model\MemberCommission;
use app\common\model\Order;
use app\common\model\OrderVip;
use app\common\model\SysUrl;
use app\common\model\VipGoodsPrice;
use app\common\model\VipSite;
use app\common\service\VipPriceService;
use app\common\service\VipSiteService;
use mks\BaseController;

class ConsoleController extends BaseController
{
    protected function site(): VipSite
    {
        return $this->request->vipSite;
    }

    protected function memberId(): int
    {
        return (int) ($this->request->memberId ?? 0);
    }

    /**
     * 概览
     */
    public function overview()
    {
        $site    = $this->site();
        $vipId   = (int) $site->getAttr('id');
        $uid     = (int) $site->getAttr('uid');
        $today   = strtotime('today');
        $orderQ  = Order::where('vip_id', $vipId)->where('delete_time', 0);
        $member  = Member::findOrEmpty($uid);
        $shareQ  = OrderVip::where('vip_id', $vipId);
        return $this->ok([
            'site' => [
                'id'          => $vipId,
                'name'        => (string) $site->getAttr('name'),
                'domain'      => (string) $site->getAttr('domain'),
                'path_prefix' => (string) $site->getAttr('path_prefix'),
                'rate'        => (string) $site->getAttr('rate'),
                'share_rate'  => (string) $site->getAttr('share_rate'),
                'status'      => (int) $site->getAttr('status'),
                'expire_time' => (int) $site->getAttr('expire_time'),
            ],
            'orders' => [
                'total'       => (clone $orderQ)->count(),
                'today'       => (clone $orderQ)->where('create_time', '>=', $today)->count(),
                'sales'       => (string) (clone $orderQ)->sum('total_price'),
                'today_sales' => (string) (clone $orderQ)->where('create_time', '>=', $today)->sum('total_price'),
            ],
            'share' => [
                'total'     => (string) (clone $shareQ)->sum('commision_price'),
                'pending'   => (string) (clone $shareQ)->where('sure_status', OrderVip::SURE_WAIT)->sum('commision_price'),
                'settled'   => (string) (clone $shareQ)->where('sure_status', OrderVip::SURE_SETTLED)->sum('commision_price'),
                'refunded'  => (string) (clone $shareQ)->sum('back_commision_price'),
            ],
            'wallet' => [
                'commission_money'       => $member->isEmpty() ? '0.0000' : (string) $member->getAttr('commission_money'),
                'freez_commission_money' => $member->isEmpty() ? '0.0000' : (string) $member->getAttr('freez_commission_money'),
                'total_commission_money' => $member->isEmpty() ? '0.0000' : (string) $member->getAttr('total_commission_money'),
            ],
        ]);
    }

    /**
     * 商品与定价列表
     */
    public function goods_index()
    {
        $site  = $this->site();
        $vipId = (int) $site->getAttr('id');
        $page  = max(1, (int) $this->request->param('page', 1));
        $limit = min(100, max(1, (int) $this->request->param('limit', 20)));
        $query = Goods::where('status', 1)->where('delete_time', 0);
        $query = VipPriceService::applyVisibleScope($query, $site);
        $keyword = (string) $this->request->param('keyword', '');
        if ($keyword !== '') {
            $query = $query->whereLike('goods_name', '%' . $keyword . '%');
        }
        $total = (clone $query)->count();
        $list  = $query->order('id desc')->page($page, $limit)
            ->field('id,goods_name,goods_img,real_price,vip_id,status')
            ->select()->toArray();
        $ovRows = VipGoodsPrice::where('vip_id', $vipId)
            ->whereIn('goods_id', array_column($list, 'id'))
            ->select()->toArray();
        $overrides = array_column($ovRows, null, 'goods_id');
        foreach ($list as &$r) {
            $ov = $overrides[(int) $r['id']] ?? null;
            $model = null;
            if ($ov !== null) {
                $model = (new VipGoodsPrice())->data($ov, true);
            }
            $p = VipPriceService::price((string) $r['real_price'], $site, $model);
            $r['base_price']  = $p['base'];
            $r['vip_price']   = $p['price'];
            $r['vip_mode']    = $p['mode'];
            $r['vip_value']   = $p['value'];
            $r['has_override'] = $p['override'] ? 1 : 0;
            $r['add_price']   = $p['add'];
        }
        unset($r);
        return $this->ok(['list' => $list, 'count' => $total]);
    }

    /**
     * 保存商品级定价
     */
    public function goods_price_save()
    {
        $vipId   = (int) $this->site()->getAttr('id');
        $goodsId = (int) $this->request->param('goods_id', 0);
        $mode    = (int) $this->request->param('mode', 0);
        $value   = (string) $this->request->param('value', '0');
        if ($goodsId <= 0 || !in_array($mode, [1, 2, 3], true) || bccomp($value, '0', 4) < 0) {
            return $this->fail('参数错误');
        }
        $goods = Goods::findOrEmpty($goodsId);
        if ($goods->isEmpty()) {
            return $this->fail('商品不存在');
        }
        $check = VipPriceService::resolve($goods, $this->site());
        if (!$check['visible']) {
            return $this->fail('该商品不在本站销售范围');
        }
        $row = VipGoodsPrice::where('vip_id', $vipId)->where('goods_id', $goodsId)->findOrEmpty();
        if ($row->isEmpty()) {
            VipGoodsPrice::create([
                'vip_id'      => $vipId,
                'goods_id'    => $goodsId,
                'mode'        => $mode,
                'value'       => $value,
                'status'      => 1,
                'create_time' => time(),
                'update_time' => time(),
            ]);
        } else {
            $row->save(['mode' => $mode, 'value' => $value, 'status' => 1, 'update_time' => time()]);
        }
        return $this->ok([], '已保存');
    }

    /**
     * 清除商品级定价（回落到分站比例）
     */
    public function goods_price_clear()
    {
        $vipId   = (int) $this->site()->getAttr('id');
        $goodsId = (int) $this->request->param('goods_id', 0);
        if ($goodsId <= 0) {
            return $this->fail('参数错误');
        }
        VipGoodsPrice::where('vip_id', $vipId)->where('goods_id', $goodsId)->delete();
        return $this->ok([], '已恢复默认');
    }

    /**
     * 订单列表
     */
    public function order_index()
    {
        $vipId  = (int) $this->site()->getAttr('id');
        $page   = max(1, (int) $this->request->param('page', 1));
        $limit  = min(100, max(1, (int) $this->request->param('limit', 20)));
        $query  = Order::where('vip_id', $vipId)->where('delete_time', 0);
        $status = (string) $this->request->param('status', '');
        if ($status !== '') {
            $query = $query->where('status', (int) $status);
        }
        $keyword = (string) $this->request->param('keyword', '');
        if ($keyword !== '') {
            $query = $query->where(function ($q) use ($keyword) {
                $q->whereLike('ordersn', '%' . $keyword . '%')
                    ->whereOr('goods_name', 'like', '%' . $keyword . '%');
            });
        }
        $total = (clone $query)->count();
        $list  = $query->order('id desc')->page($page, $limit)
            ->field('id,ordersn,uid,nickname,goods_id,goods_name,goods_img,quantity,real_price,total_price,vip_goods_price,vip_add_price,status,pay_time,create_time')
            ->select()->toArray();
        return $this->ok(['list' => $list, 'count' => $total]);
    }

    /**
     * 订单详情（限本站）
     */
    public function order_read(int $id)
    {
        $vipId = (int) $this->site()->getAttr('id');
        $order = Order::where('id', $id)->where('vip_id', $vipId)->findOrEmpty();
        if ($order->isEmpty()) {
            return $this->fail('订单不存在');
        }
        return $this->ok($order->toArray());
    }

    /**
     * 结算（分站抽成）流水
     */
    public function settlement_index()
    {
        $vipId  = (int) $this->site()->getAttr('id');
        $page   = max(1, (int) $this->request->param('page', 1));
        $limit  = min(100, max(1, (int) $this->request->param('limit', 20)));
        $query  = OrderVip::where('vip_id', $vipId);
        $sure   = (string) $this->request->param('sure_status', '');
        if ($sure !== '') {
            $query = $query->where('sure_status', (int) $sure);
        }
        $total = (clone $query)->count();
        $rows  = $query->order('id desc')->page($page, $limit)->select()->toArray();
        $orderIds = array_column($rows, 'order_id');
        $orders   = $orderIds === [] ? [] : Order::whereIn('id', $orderIds)->column('ordersn,goods_name,status,total_price', 'id');
        foreach ($rows as &$r) {
            $o = $orders[(int) $r['order_id']] ?? null;
            $r['ordersn']     = $o['ordersn'] ?? '';
            $r['goods_name']  = $o['goods_name'] ?? '';
            $r['order_status'] = $o['status'] ?? 0;
            $r['order_total'] = $o['total_price'] ?? '0.0000';
        }
        unset($r);
        return $this->ok(['list' => $rows, 'count' => $total]);
    }

    /**
     * 抽成账户流水（佣金账本）
     */
    public function commission_logs()
    {
        $uid   = $this->memberId();
        $page  = max(1, (int) $this->request->param('page', 1));
        $limit = min(100, max(1, (int) $this->request->param('limit', 20)));
        $query = MemberCommission::where('uid', $uid)->whereIn('trade_type', [
            MemberCommission::T_VIP_SHARE,
            MemberCommission::T_VIP_SHARE_BACK,
            MemberCommission::T_VIP_SHARE_FREEZE,
            MemberCommission::T_VIP_SHARE_UNFREEZE,
        ]);
        $total = (clone $query)->count();
        $list  = $query->order('id desc')->page($page, $limit)->select()->toArray();
        return $this->ok(['list' => $list, 'count' => $total]);
    }

    /**
     * 分站配置读取
     */
    public function config_read()
    {
        $vipId = (int) $this->site()->getAttr('id');
        return $this->ok([
            'config' => VipSiteService::configRead($vipId),
            'whitelist' => array_keys(VipSiteService::CONFIG_WHITELIST),
        ]);
    }

    /**
     * 分站配置保存（白名单键）
     */
    public function config_save()
    {
        $vipId = (int) $this->site()->getAttr('id');
        VipSiteService::configWrite($vipId, $this->request->param());
        return $this->ok([], '已保存');
    }

    /**
     * 绑定域名（只读）
     */
    public function domain_index()
    {
        $vipId = (int) $this->site()->getAttr('id');
        $rows  = SysUrl::where('vip_id', $vipId)->order('id asc')->select()->toArray();
        return $this->ok($rows);
    }
}
