<?php
// +----------------------------------------------------------------------
// | 会员余额服务（资金红线：balance 增减与 member_money 流水同事务；幂等防重）
// +----------------------------------------------------------------------
declare (strict_types = 1);

namespace app\common\service;

use app\common\model\Member;
use app\common\model\MemberMoney;
use think\facade\Db;

class MemberMoneyService
{
    /**
     * 余额变动（正=加 负=减）
     * @param int    $uid         会员ID
     * @param int    $tradeType   MemberMoney::T_* 业务类型
     * @param int    $changeType  MemberMoney::C_INCOME/C_PAY
     * @param string $money       变动金额（恒为正数，方向由 changeType 决定）
     * @param int    $orderId     关联订单ID
     * @param string $mark        备注
     * @param string $idempotentKey 幂等键（only_order_no，非空时重复入账直接跳过）
     * @param int    $adminId     操作管理员
     * @return array{before:string, after:string}
     */
    public static function changeMoney(
        int $uid,
        int $tradeType,
        int $changeType,
        string $money,
        int $orderId = 0,
        string $mark = '',
        string $idempotentKey = '',
        int $adminId = 0
    ): array {
        if ($uid <= 0 || bccomp($money, '0', 4) <= 0) {
            throw_fail('金额参数错误', 1);
        }
        // 幂等：同一 only_order_no 只入账一次
        if ($idempotentKey !== '') {
            $exists = MemberMoney::where('only_order_no', $idempotentKey)->findOrEmpty();
            if (!$exists->isEmpty()) {
                $uid0 = (int) $exists->getAttr('uid');
                $after = (string) Member::where('id', $uid0)->value('balance');
                return ['before' => $after, 'after' => $after];
            }
        }

        Db::startTrans();
        try {
            // SELECT ... FOR UPDATE 行锁
            $member = Member::lock(true)->findOrEmpty($uid);
            if ($member->isEmpty()) {
                throw_fail('会员不存在', 1);
            }
            $before = (string) $member->getAttr('balance');
            $delta  = $changeType === MemberMoney::C_INCOME ? $money : bcmul($money, '-1', 4);
            $after  = bcadd($before, $delta, 4);
            if (bccomp($after, '0', 4) < 0) {
                throw_fail('用户余额不足', 1);
            }
            $member->force()->save(['balance' => $after]);

            MemberMoney::create([
                'uid'           => $uid,
                'money'         => $money,
                'trade_type'    => $tradeType,
                'change_type'   => $changeType,
                'before'        => $before,
                'after'         => $after,
                'create_time'   => time(),
                'extend_id'     => $idempotentKey,
                'mark'          => mb_substr($mark, 0, 250, 'UTF-8'),
                'order_id'      => $orderId,
                'admin_id'      => $adminId,
                'only_order_no' => $idempotentKey,
            ]);
            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            throw $e;
        }
        return ['before' => $before, 'after' => $after];
    }
}
