<?php
// +----------------------------------------------------------------------
// | 会员佣金服务（资金红线：commission_money 增减与 member_commission 流水同事务；
// | SELECT...FOR UPDATE 行锁 + before/after 快照 + 幂等键防重复入账，对齐 MemberMoneyService）
// +----------------------------------------------------------------------
declare (strict_types = 1);

namespace app\common\service;

use app\common\model\Member;
use app\common\model\MemberCommission;
use think\facade\Db;

class MemberCommissionService
{
    /**
     * 佣金账户变动（正=加 负=减；同时累计 total_commission_money 仅在收入时）
     * @param int    $uid           会员ID
     * @param int    $tradeType     MemberCommission::T_*
     * @param int    $changeType    C_INCOME/C_PAY
     * @param string $money         变动金额（恒为正，方向由 changeType 决定）
     * @param int    $orderId       关联订单ID
     * @param string $mark          备注
     * @param string $idempotentKey 幂等键（extend_id；非空时重复入账直接跳过）
     * @param int    $adminId       操作管理员
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
        // 幂等：同一 extend_id 只入账一次
        if ($idempotentKey !== '') {
            $exists = MemberCommission::where('extend_id', $idempotentKey)->findOrEmpty();
            if (!$exists->isEmpty()) {
                $after = (string) Member::where('id', (int) $exists->getAttr('uid'))->value('commission_money');
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
            $before = (string) $member->getAttr('commission_money');
            $delta  = $changeType === MemberCommission::C_INCOME ? $money : bcmul($money, '-1', 4);
            $after  = bcadd($before, $delta, 4);
            if (bccomp($after, '0', 4) < 0) {
                // 佣金已消费导致不可全额回冲：按可扣金额封顶（回冲差额记 0，由调用方记录）
                $money  = $before;
                $after  = '0.0000';
                $delta  = bcmul($before, '-1', 4);
                if (bccomp($money, '0', 4) <= 0) {
                    Db::rollback();
                    return ['before' => '0.0000', 'after' => '0.0000'];
                }
            }
            $update = ['commission_money' => $after];
            if ($changeType === MemberCommission::C_INCOME) {
                $update['total_commission_money'] = bcadd((string) $member->getAttr('total_commission_money'), $money, 4);
            }
            $member->force()->save($update);

            MemberCommission::create([
                'uid'         => $uid,
                'money'       => $money,
                'trade_type'  => $tradeType,
                'change_type' => $changeType,
                'before'      => $before,
                'after'       => $after,
                'create_time' => time(),
                'extend_id'   => mb_substr($idempotentKey, 0, 250, 'UTF-8'),
                'mark'        => mb_substr($mark, 0, 250, 'UTF-8'),
                'order_id'    => $orderId,
            ]);
            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            throw $e;
        }
        return ['before' => $before, 'after' => $after];
    }
}
