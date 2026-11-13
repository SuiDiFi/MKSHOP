<?php
// +----------------------------------------------------------------------
// | 后台 · 财务对账管理（M7 对齐 KASUSHOU moneyday / 渠道对账 dockingday）
// +----------------------------------------------------------------------
declare (strict_types = 1);

namespace app\controller\admin;

use app\common\service\MoneyDayService;
use app\common\model\MemberMoney;
use mks\BaseController;
use think\facade\Db;

class MoneyDayController extends BaseController
{
    /**
     * 客户对账列表
     * GET date? uid?
     */
    public function index()
    {
        $query = Db::name('money_day')->order('total_create_time desc, uid asc');
        $date = (string) $this->request->param('date', '');
        if ($date !== '') {
            $query = $query->where('total_create_time', $date);
        }
        $uid = (int) $this->request->param('uid', 0);
        if ($uid > 0) {
            $query = $query->where('uid', $uid);
        }
        $list = $query->limit(200)->select()->toArray();
        return $this->ok(['list' => $list, 'count' => count($list)]);
    }

    /**
     * 手动更新指定日期对账单（对齐「更新对账单」按钮）
     */
    public function update()
    {
        $date = (string) $this->request->param('date', date('Y-m-d', strtotime('-1 day')));
        $count = MoneyDayService::generate($date);
        return $this->ok(['count' => $count], "已生成 {$date} 对账单 {$count} 条");
    }

    /**
     * 渠道对账列表
     */
    public function channel_index()
    {
        $query = Db::name('docking_day')->order('day desc, supplier_id asc');
        $date = (string) $this->request->param('date', '');
        if ($date !== '') {
            $query = $query->where('day', $date);
        }
        $list = $query->limit(200)->select()->toArray();
        return $this->ok(['list' => $list, 'count' => count($list)]);
    }

    /**
     * 手动更新渠道对账
     */
    public function channel_update()
    {
        $date = (string) $this->request->param('date', date('Y-m-d', strtotime('-1 day')));
        $count = MoneyDayService::generateChannel($date);
        return $this->ok(['count' => $count], "已生成 {$date} 渠道对账单 {$count} 条");
    }

    /**
     * 客户对账导出 CSV
     */
    public function export()
    {
        $query = Db::name('money_day')->order('total_create_time desc, uid asc');
        $date = (string) $this->request->param('date', '');
        if ($date !== '') {
            $query = $query->where('total_create_time', $date);
        }
        $rows = $query->limit(5000)->select()->toArray();
        $header = ['日期', '用户ID', '期初余额', '期末余额', '加款金额', '扣款金额', '充值金额', '订单利润',
            '订单金额', '订单成本', '退款金额', '订单佣金', '充值手续费'];
        return response_csv('moneyday_' . date('Ymd_His') . '.csv', $header, array_map(function ($r) {
            return [
                $r['total_create_time'], $r['uid'], $r['money_before_balance'], $r['money_after_balance'],
                $r['money_add_money'], $r['money_sub_money'], $r['recharge_money'], $r['order_order_price'],
                $r['order_total_price'], $r['order_cost_value'], $r['order_back_price'],
                $r['order_commission_money'], $r['recharge_money_service'],
            ];
        }, $rows));
    }
}
