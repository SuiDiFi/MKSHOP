<?php
// +----------------------------------------------------------------------
// | 前台 · 商品浏览（M2：在售列表/详情，含实时库存）
// +----------------------------------------------------------------------
declare (strict_types = 1);

namespace app\controller\front;

use app\common\model\Goods;
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
     * 在售商品列表
     */
    public function index()
    {
        $page  = max(1, (int) $this->request->param('page', 1));
        $limit = min(50, max(1, (int) $this->request->param('limit', 12)));
        $query = Goods::where('status', 1)
            ->whereIn('check_status', [3, 9])
            ->where('delete_time', 0);
        $keyword = (string) $this->request->param('keyword', '');
        if ($keyword !== '') {
            $query = $query->whereLike('goods_name', '%' . $keyword . '%');
        }
        $categoryId = (int) $this->request->param('category_id', 0);
        if ($categoryId > 0) {
            $query = $query->whereRaw("FIND_IN_SET({$categoryId}, category_ids)");
        }
        $total = (clone $query)->count();
        $list  = $query->order('sort desc, id desc')->page($page, $limit)
            ->field('id,goods_no,goods_name,goods_short_name,goods_type,face_value,real_price,goods_img,goods_tags,ishot,stock_num,sales_num,virtual_num,recharge_time_type,recharge_time_value,start_count,end_count,num_count')
            ->select()
            ->toArray();
        return $this->ok(['list' => $list, 'count' => $total]);
    }

    /**
     * 商品详情（含实时可售库存与数量档位）
     */
    public function read(int $id)
    {
        $goods = Goods::where('id', $id)->where('status', 1)->where('delete_time', 0)->findOrEmpty();
        if ($goods->isEmpty()) {
            return $this->fail('商品不存在或已下架');
        }
        $data = $goods->toArray();
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
