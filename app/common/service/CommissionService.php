<?php
// +----------------------------------------------------------------------
// | MKSHOP 分销佣金服务（M4）
// | 模式：两级比例佣金（上级等级 member_level.one_rate/two_rate，基数=订单实付）
// | 两阶段：订单成功 → 写 order_commission（sure_time=0 立即入账 / >0 冻结期）
// |         → mks:commission 定时解冻入账
// | 退款：按 back_rate 比例回冲已入账佣金（封顶扣款），未入账直接作废
// | 红线：佣金入账必须走 MemberCommissionService（同事务流水+幂等）；
// |       settleIfSuccess 幂等（订单已有佣金记录则跳过）
// +----------------------------------------------------------------------
declare (strict_types = 1);

namespace app\common\service;

use app\common\model\Goods;
use app\common\model\Member;
use app\common\model\MemberCommission;
use app\common\model\MemberLevel;
use app\common\model\Order;
use app\common\model\OrderCommission;
use think\facade\Config;
use think\facade\Db;
use think\facade\Log;

class CommissionService
{
    /**
     * 订单成功结算入口（幂等）：订单已是 ST_SUCCESS 时计算并登记两级佣金
     * 挂载点：本地卡密发货成功 / 渠道单成功（轮询+回调）/ 后台人工成功
     */
    public static function settleIfSuccess(int $orderId): void
    {
        if (!Config::get('mks.commission.enabled', true)) {
            return;
        }
        $exists = OrderCommission::where('order_id', $orderId)->count();
        if ($exists > 0) {
            return; // 已登记（幂等）
        }
        $order = Order::findOrEmpty($orderId);
        if ($order->isEmpty() || (int) $order->getAttr('status') !== Order::ST_SUCCESS) {
            return;
        }
        $uid = (int) $order->getAttr('uid');
        if ($uid <= 0) {
            return; // 游客单无分销
        }
        $goods = Goods::findOrEmpty((int) $order->getAttr('goods_id'));
        if ($goods->isEmpty() || (int) $goods->getAttr('is_rebate') !== 1) {
            return; // 商品未开启分销
        }
        $buyer = Member::findOrEmpty($uid);
        if ($buyer->isEmpty() || (int) $buyer->getAttr('pid') <= 0) {
            return; // 无上级
        }
        $total = (string) $order->getAttr('total_price');

        // 上级链：一级=直接上级（其等级 one_rate），二级=上上级（其等级 two_rate）
        $l1 = Member::where('id', (int) $buyer->getAttr('pid'))->where('status', 1)->where('delete_time', 0)->findOrEmpty();
        if ($l1->isEmpty()) {
            return;
        }
        $l2 = Member::where('id', (int) $l1->getAttr('pid'))->where('status', 1)->where('delete_time', 0)->findOrEmpty();

        $rate1 = (int) MemberLevel::where('id', (int) $l1->getAttr('level_id'))->value('one_rate');
        $rate2 = $l2->isEmpty() ? 0 : (int) MemberLevel::where('id', (int) $l2->getAttr('level_id'))->value('two_rate');
        $price1 = $rate1 > 0 ? bcdiv(bcmul($total, (string) $rate1, 6), '100', 4) : '0.0000'; // total * rate / 100
        $price2 = $rate2 > 0 ? bcdiv(bcmul($total, (string) $rate2, 6), '100', 4) : '0.0000';
        if (bccomp($price1, '0.01', 4) < 0 && bccomp($price2, '0.01', 4) < 0) {
            return; // 无有效佣金
        }
        $sureTime = (int) Config::get('mks.commission.sure_time', 0);
        $frozen   = $sureTime > 0 ? time() + $sureTime * 60 : 0;

        OrderCommission::create([
            'order_id'             => $orderId,
            'layer'                => $l2->isEmpty() ? 1 : 2,
            'uid1'                 => (int) $l1->getAttr('id'),
            'uid1_name'            => (string) $l1->getAttr('nickname'),
            'price1'               => $price1,
            'uid2'                 => $l2->isEmpty() ? 0 : (int) $l2->getAttr('id'),
            'uid2_name'            => $l2->isEmpty() ? '' : (string) $l2->getAttr('nickname'),
            'puid'                 => (int) $buyer->getAttr('pid'),
            'puid_price'           => '0.000',
            'price2'               => $price2,
            'commision_price'      => bcadd($price1, $price2, 4),
            'total_commision_price' => bcadd($price1, $price2, 4),
            'back_commision_price' => '0.00',
            'status'               => 1,
            'sure_status'          => OrderCommission::SURE_WAIT, // 统一先待结算，即时分支由 grantCommission 置已结算
            'create_time'          => time(),
            'sure_count'           => 0,
            'frozen_time'          => $frozen,
            'level_price_difference' => '0',
            'vip_add_price'        => '0',
        ]);
        // 订单佣金快照
        Order::where('id', $orderId)->inc('commission_price', (float) bcadd($price1, $price2, 4))->update();

        if ($sureTime > 0) {
            self::log($orderId, '佣金登记：一级 ' . $price1 . ' / 二级 ' . $price2 . '，冻结 ' . $sureTime . ' 分钟');
            return; // 冻结期：等 mks:commission 到期入账
        }
        // 即时结算（SURE_WAIT → 入账 → SURE_SETTLED）
        self::grantCommission($orderId);
    }

    /**
     * 冻结期到期结算（mks:commission 定时任务；对每条 order_commission 整单入账）
     * @return array{scanned:int, settled:int, failed:int}
     */
    public static function settleDue(int $limit = 100): array
    {
        $stat = ['scanned' => 0, 'settled' => 0, 'failed' => 0];
        $ids = OrderCommission::where('sure_status', OrderCommission::SURE_WAIT)
            ->where('frozen_time', '<=', time())
            ->where('frozen_time', '>', 0)
            ->order('id asc')->limit($limit)->column('id');
        foreach ($ids as $id) {
            $stat['scanned']++;
            try {
                $ok = self::grantCommission((int) Db::name('order_commission')->where('id', $id)->value('order_id'));
                if ($ok) {
                    $stat['settled']++;
                }
            } catch (\Throwable $e) {
                $stat['failed']++;
                try {
                    Log::warning('[commission] settle failed order=' . $id . ' reason=' . mb_substr($e->getMessage(), 0, 300));
                } catch (\Throwable $ignore) {
                }
            }
        }
        return $stat;
    }

    /**
     * 佣金实际入账（订单下所有未结算行；幂等：无 sure_status=1 行直接返回 false）
     */
    public static function grantCommission(int $orderId): bool
    {
        $rows = OrderCommission::where('order_id', $orderId)
            ->where('sure_status', OrderCommission::SURE_WAIT)
            ->select();
        if ($rows->isEmpty()) {
            return false;
        }
        foreach ($rows as $row) {
            $amounts = [
                [(int) $row->getAttr('uid1'), (string) $row->getAttr('price1')],
                [(int) $row->getAttr('uid2'), (string) $row->getAttr('price2')],
            ];
            foreach ($amounts as [$uid, $money]) {
                if ($uid <= 0 || bccomp($money, '0', 4) <= 0) {
                    continue;
                }
                MemberCommissionService::changeMoney(
                    $uid,
                    MemberCommission::T_ORDER_COMMISSION,
                    MemberCommission::C_INCOME,
                    $money,
                    $orderId,
                    '订单佣金 ' . (string) Order::where('id', $orderId)->value('ordersn'),
                    'COMMISSION:' . $orderId . ':' . $row->getAttr('id') . ':' . $uid
                );
            }
            $row->save([
                'sure_status' => OrderCommission::SURE_SETTLED,
                'sure_count'  => (int) $row->getAttr('sure_count') + 1,
            ]);
        }
        self::log($orderId, '佣金结算入账');
        return true;
    }

    /**
     * 退款回冲（挂载 OrderService::refund；按退款比例 backRate）
     * @param string $backRate 退款比例 0~1（money/total_price）
     */
    public static function rollbackForRefund(int $orderId, string $backRate): void
    {
        if (bccomp($backRate, '0', 4) <= 0) {
            return;
        }
        $rows = OrderCommission::where('order_id', $orderId)
            ->whereIn('sure_status', [OrderCommission::SURE_WAIT, OrderCommission::SURE_SETTLED, OrderCommission::SURE_PART])
            ->select();
        foreach ($rows as $row) {
            $total   = (string) $row->getAttr('total_commision_price');
            $backed  = (string) $row->getAttr('back_commision_price');
            $remain  = bcsub($total, $backed, 4);
            if (bccomp($remain, '0', 4) <= 0) {
                continue;
            }
            $back = bcmul($total, $backRate, 4);
            if (bccomp($back, $remain, 4) > 0) {
                $back = $remain; // 累计回冲不超总额
            }
            if (bccomp($back, '0', 4) <= 0) {
                continue;
            }
            $sureStatus = (int) $row->getAttr('sure_status');
            if ($sureStatus === OrderCommission::SURE_WAIT) {
                // 冻结期未入账：直接作废，不动资金
                $row->save([
                    'sure_status'          => OrderCommission::SURE_REFUND,
                    'back_commision_price' => bcadd($backed, $remain, 4),
                ]);
                continue;
            }
            // 已入账：按层回冲（幂等键 + 余额不足自动封顶）
            $amounts = [
                [(int) $row->getAttr('uid1'), $back],
                [(int) $row->getAttr('uid2'), $back],
            ];
            $backed1 = '0';
            foreach ($amounts as [$uid, $money]) {
                if ($uid <= 0 || bccomp($money, '0', 4) <= 0) {
                    continue;
                }
                $r = MemberCommissionService::changeMoney(
                    $uid,
                    MemberCommission::T_COMMISSION_BACK,
                    MemberCommission::C_PAY,
                    $money,
                    $orderId,
                    '订单退款佣金回冲 ' . (string) Order::where('id', $orderId)->value('ordersn'),
                    'COMMISSIONBACK:' . $orderId . ':' . $row->getAttr('id') . ':' . $uid
                );
                $backed1 = bcadd($backed1, bcsub((string) $r['before'], (string) $r['after'], 4), 4);
            }
            $newBacked = bcadd($backed, $back, 4);
            $row->save([
                'back_commision_price' => $newBacked,
                'sure_status'          => bccomp($newBacked, $total, 4) >= 0 ? OrderCommission::SURE_REFUND : OrderCommission::SURE_PART,
            ]);
        }
        self::log($orderId, '退款佣金回冲 比例 ' . $backRate);
    }

    /**
     * 绑定上级（注册/补绑；防环：沿邀请链向上最多 10 层不得遇到自己）
     */
    public static function bindParent(int $uid, int $pid): void
    {
        if ($uid <= 0 || $pid <= 0) {
            throw_fail('参数错误', 1);
        }
        if ($uid === $pid) {
            throw_fail('不能绑定自己为上级', 1);
        }
        $member = Member::findOrEmpty($uid);
        if ($member->isEmpty()) {
            throw_fail('会员不存在', 1);
        }
        if ((int) $member->getAttr('pid') > 0) {
            throw_fail('已存在上级，不可重复绑定', 1);
        }
        $parent = Member::where('id', $pid)->where('status', 1)->where('delete_time', 0)->findOrEmpty();
        if ($parent->isEmpty()) {
            throw_fail('邀请人不存在或已禁用', 1);
        }
        // 防环：向上遍历邀请链
        $cur = $pid;
        for ($i = 0; $i < 10 && $cur > 0; $i++) {
            if ($cur === $uid) {
                throw_fail('不能绑定自己的下级为上级', 1);
            }
            $cur = (int) Member::where('id', $cur)->value('pid');
        }
        $member->save(['pid' => $pid]);
        Member::where('id', $pid)->inc('child_num')->update();
    }

    protected static function log(int $orderId, string $mark): void
    {
        $order = Order::findOrEmpty($orderId);
        if ($order->isEmpty()) {
            return;
        }
        try {
            OrderService::log($order, '[佣金] ' . $mark);
        } catch (\Throwable $ignore) {
        }
    }
}
