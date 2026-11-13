<?php
// +----------------------------------------------------------------------
// | 财务对账服务（M7 对齐 KASUSHOU 财务对账管理 moneyday / 渠道对账 dockingday）
// | 口径（对齐原系统列语义）：
// |   订单利润/金额/成本：当日完成（finish_time 落日）且 status=3 的订单，按 uid 汇总
// |   退款金额：member_money 当日 T_ORDER_REFUND 合计
// |   充值金额：member_money 当日 T_RECHARGE_ONLINE 合计；服务费暂记 0
// |   加款/扣款：member_money 当日 T_ADMIN_ADD / T_ADMIN_SUB 合计
// |   期初/期末余额：当日该用户 member_money 首条 before / 末条 after（无流水=期末=期初=当前余额）
// | 幂等：按 (uid, 日期) 先删后插；可重复执行
// +----------------------------------------------------------------------
declare (strict_types = 1);

namespace app\common\service;

use app\common\model\MemberMoney;
use think\facade\Db;

class MoneyDayService
{
    /**
     * 生成/更新指定日期的客户对账单
     * @return int 生成的行数
     */
    public static function generate(string $date): int
    {
        $dayStart = strtotime($date . ' 00:00:00');
        $dayEnd   = $dayStart + 86399;
        if ($dayStart <= 0) {
            throw_fail('日期格式不正确', 1);
        }

        // ── 订单口径（按 uid） ──
        $orders = Db::name('order')
            ->whereBetween('finish_time', [$dayStart, $dayEnd])
            ->where('status', 3)
            ->where('delete_time', 0)
            ->field('uid, COUNT(*) cnt, SUM(total_price) total_price, SUM(cost_value) cost_value, SUM(order_price) order_price')
            ->group('uid')
            ->select()->toArray();
        $orderMap = [];
        foreach ($orders as $o) {
            $orderMap[(int) $o['uid']] = $o;
        }

        // ── 资金流水口径（按 uid × trade_type） ──
        $moneyRows = Db::name('member_money')
            ->whereBetween('create_time', [$dayStart, $dayEnd])
            ->field('uid, trade_type, SUM(money) money')
            ->group('uid, trade_type')
            ->select()->toArray();
        $moneyMap = [];
        $uids = array_map(fn ($r) => (int) $r['uid'], $moneyRows);
        foreach ($orders as $o) {
            $uids[] = (int) $o['uid'];
        }
        foreach ($moneyRows as $r) {
            $moneyMap[(int) $r['uid']][(int) $r['trade_type']] = (float) $r['money'];
        }

        // ── 佣金口径（仅收入方向 change_type=1，回冲在退款列体现） ──
        $commRows = Db::name('member_commission')
            ->whereBetween('create_time', [$dayStart, $dayEnd])
            ->where('change_type', 1)
            ->field('uid, SUM(money) money')
            ->group('uid')
            ->select()->toArray();
        foreach ($commRows as $r) {
            $uids[] = (int) $r['uid'];
        }

        // ── 期初/期末余额（当日首末流水） ──
        $edgeRows = Db::name('member_money')
            ->whereBetween('create_time', [$dayStart, $dayEnd])
            ->field('uid, `before`, `after`, id')
            ->order('id asc')
            ->select()->toArray();
        $first = $last = [];
        foreach ($edgeRows as $r) {
            $uid = (int) $r['uid'];
            $uids[] = $uid;
            if (!isset($first[$uid])) {
                $first[$uid] = (float) $r['before'];
            }
            $last[$uid] = (float) $r['after'];
        }

        $uids = array_values(array_unique($uids));
        $count = 0;
        foreach ($uids as $uid) {
            if ($uid <= 0) {
                continue;
            }
            $o = $orderMap[$uid] ?? [];
            $m = $moneyMap[$uid] ?? [];
            $before = $first[$uid] ?? ($last[$uid] ?? (float) Db::name('member')->where('id', $uid)->value('balance'));
            $after  = $last[$uid] ?? $before;
            $row = [
                'total_create_time'      => $date,
                'uid'                    => $uid,
                'order_order_price'      => (float) ($o['order_price'] ?? 0),
                'order_total_price'      => (float) ($o['total_price'] ?? 0),
                'order_cost_value'       => (float) ($o['cost_value'] ?? 0),
                'order_back_price'       => (float) ($m[MemberMoney::T_ORDER_REFUND] ?? 0),
                'order_commission_money' => array_sum(array_map(fn ($r) => (int) $r['uid'] === $uid ? (float) $r['money'] : 0, $commRows)),
                'recharge_money'         => (float) ($m[MemberMoney::T_RECHARGE_ONLINE] ?? 0),
                'recharge_money_service' => 0,
                'money_add_money'        => (float) ($m[MemberMoney::T_ADMIN_ADD] ?? 0),
                'money_sub_money'        => (float) ($m[MemberMoney::T_ADMIN_SUB] ?? 0),
                'money_before_balance'   => $before,
                'money_after_balance'    => $after,
            ];
            Db::name('money_day')
                ->where('uid', $uid)->where('total_create_time', $date)->delete();
            Db::name('money_day')->insert($row);
            $count++;
        }
        return $count;
    }

    /**
     * 生成/更新指定日期的渠道对账（按供货商 supplier_id 维度；无供货商订单归 0=自营）
     */
    public static function generateChannel(string $date): int
    {
        $dayStart = strtotime($date . ' 00:00:00');
        $dayEnd   = $dayStart + 86399;
        if ($dayStart <= 0) {
            throw_fail('日期格式不正确', 1);
        }
        // 确保表存在（首次使用自动建）
        Db::execute("CREATE TABLE IF NOT EXISTS `mks_docking_day` (
            `id` int unsigned NOT NULL AUTO_INCREMENT,
            `day` varchar(32) NOT NULL DEFAULT '' COMMENT '对账日期',
            `supplier_id` int NOT NULL DEFAULT 0 COMMENT '供货商ID（0=自营）',
            `supplier_name` varchar(100) NOT NULL DEFAULT '',
            `order_profit` decimal(16,4) NOT NULL DEFAULT 0,
            `order_total` decimal(16,4) NOT NULL DEFAULT 0,
            `order_cost` decimal(16,5) NOT NULL DEFAULT 0,
            `order_commission` decimal(16,4) NOT NULL DEFAULT 0,
            `create_time` int NOT NULL DEFAULT 0,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uk_day_supplier` (`day`,`supplier_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='渠道对账日结（M7）'");

        // 佣金按订单归属
        $comms = Db::name('member_commission')
            ->whereBetween('create_time', [$dayStart, $dayEnd])
            ->field('order_id, SUM(money) money')
            ->group('order_id')
            ->select()->toArray();
        $commMap = [];
        foreach ($comms as $c) {
            $commMap[(int) $c['order_id']] = (float) $c['money'];
        }
        $orders = Db::name('order')
            ->whereBetween('finish_time', [$dayStart, $dayEnd])
            ->where('status', 3)
            ->where('delete_time', 0)
            ->field('id, uid, supplier_id, supplier_name, total_price, cost_value, order_price')
            ->select()->toArray();
        $agg = [];
        foreach ($orders as $o) {
            $sid = (int) $o['supplier_id'];
            $key = $sid;
            if (!isset($agg[$key])) {
                $agg[$key] = [
                    'supplier_id'      => $sid,
                    'supplier_name'    => $sid > 0 ? (string) $o['supplier_name'] : '自营',
                    'order_profit'     => 0.0,
                    'order_total'      => 0.0,
                    'order_cost'       => 0.0,
                    'order_commission' => 0.0,
                ];
            }
            $agg[$key]['order_total']      += (float) $o['total_price'];
            $agg[$key]['order_cost']       += (float) $o['cost_value'];
            $agg[$key]['order_profit']     += (float) $o['order_price'];
            $agg[$key]['order_commission'] += $commMap[(int) $o['id']] ?? 0;
        }
        $count = 0;
        foreach ($agg as $row) {
            Db::name('docking_day')
                ->where('day', $date)->where('supplier_id', $row['supplier_id'])->delete();
            Db::name('docking_day')->insert($row + ['day' => $date, 'create_time' => time()]);
            $count++;
        }
        return $count;
    }
}
