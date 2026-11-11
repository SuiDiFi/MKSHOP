<?php
// +----------------------------------------------------------------------
// | 后台 · 分销佣金管理（M4：订单佣金记录；对齐权限点 commission/*）
// +----------------------------------------------------------------------
declare (strict_types = 1);

namespace app\controller\admin;

use app\common\model\OrderCommission;
use mks\BaseController;

class CommissionController extends BaseController
{
    /**
     * 佣金记录列表
     */
    public function index()
    {
        $page  = max(1, (int) $this->request->param('page', 1));
        $limit = min(100, max(1, (int) $this->request->param('limit', 15)));
        $query = OrderCommission::order('id desc');
        $orderId = (int) $this->request->param('order_id', 0);
        if ($orderId > 0) {
            $query = $query->where('order_id', $orderId);
        }
        $uid = (int) $this->request->param('uid', 0);
        if ($uid > 0) {
            $query = $query->where(function ($q) use ($uid) {
                $q->whereOr('uid1', $uid)->whereOr('uid2', $uid);
            });
        }
        $sureStatus = $this->request->param('sure_status', '');
        if ($sureStatus !== '') {
            $query = $query->where('sure_status', (int) $sureStatus);
        }
        $total = (clone $query)->count();
        $list  = $query->page($page, $limit)
            ->field('id,order_id,layer,uid1,uid1_name,price1,uid2,uid2_name,price2,commision_price,total_commision_price,back_commision_price,sure_status,sure_count,frozen_time,create_time')
            ->select()->toArray();
        foreach ($list as &$r) {
            $r['sure_status_text'] = [1 => '待结算', 2 => '已结算', 3 => '已退款', 4 => '部分退款'][(int) $r['sure_status']] ?? '未知';
        }
        return $this->ok(['list' => $list, 'count' => $total]);
    }

    /**
     * 手动结算单条（管理员兜底；冻结期未到也可强制）
     */
    public function settle(int $id)
    {
        $row = OrderCommission::findOrEmpty($id);
        if ($row->isEmpty()) {
            return $this->fail('记录不存在');
        }
        if ((int) $row->getAttr('sure_status') !== 1) {
            return $this->fail('仅待结算记录可操作');
        }
        \app\common\service\CommissionService::grantCommission((int) $row->getAttr('order_id'));
        return $this->ok([], '结算成功');
    }
}
