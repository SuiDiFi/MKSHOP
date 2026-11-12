<?php
// +----------------------------------------------------------------------
// | 供货商货款服务（资金红线：supply_money 增减与 member_supply_money 流水同事务；
// | FOR UPDATE 行锁 + before/after 快照 + 幂等键，对齐 MemberCommissionService）
// +----------------------------------------------------------------------
declare (strict_types = 1);

namespace app\common\service;

use app\common\model\Member;
use app\common\model\MemberSupply;
use think\facade\Db;

class MemberSupplyService
{
    /**
     * 货款账户变动
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
        if ($idempotentKey !== '') {
            $exists = MemberSupply::where('extend_id', $idempotentKey)->findOrEmpty();
            if (!$exists->isEmpty()) {
                $after = (string) Member::where('id', (int) $exists->getAttr('uid'))->value('supply_money');
                return ['before' => $after, 'after' => $after];
            }
        }

        Db::startTrans();
        try {
            $member = Member::lock(true)->findOrEmpty($uid);
            if ($member->isEmpty()) {
                throw_fail('会员不存在', 1);
            }
            $before = (string) $member->getAttr('supply_money');
            $delta  = $changeType === MemberSupply::C_INCOME ? $money : bcmul($money, '-1', 4);
            $after  = bcadd($before, $delta, 4);
            if (bccomp($after, '0', 4) < 0) {
                // 已消费导致不可全额回冲：按可扣金额封顶
                $money = $before;
                $after = '0.0000';
                $delta = bcmul($before, '-1', 4);
                if (bccomp($money, '0', 4) <= 0) {
                    Db::rollback();
                    return ['before' => '0.0000', 'after' => '0.0000'];
                }
            }
            $member->force()->save(['supply_money' => $after]);

            MemberSupply::create([
                'uid'         => $uid,
                'money'       => $money,
                'trade_type'  => $tradeType,
                'change_type' => $changeType,
                'before'      => $before,
                'after'       => $after,
                'create_time' => time(),
                'extend_id'   => mb_substr($idempotentKey, 0, 250, 'UTF-8'),
                'mark'        => mb_substr($mark, 0, 250, 'UTF-8'),
                'merchant_id' => (int) $member->getAttr('merchant_id'),
                'order_id'    => $orderId,
            ]);
            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            throw $e;
        }
        return ['before' => $before, 'after' => $after];
    }

    /**
     * 冻结货款入账（freez_supply_money += money）
     */
    public static function freeze(
        int $uid,
        int $tradeType,
        string $money,
        int $orderId = 0,
        string $mark = '',
        string $idempotentKey = ''
    ): array {
        if ($uid <= 0 || bccomp($money, '0', 4) <= 0) {
            return ['before' => '0.0000', 'after' => '0.0000'];
        }
        if ($idempotentKey !== '') {
            $exists = MemberSupply::where('extend_id', $idempotentKey)->findOrEmpty();
            if (!$exists->isEmpty()) {
                $after = (string) Member::where('id', $uid)->value('freez_supply_money');
                return ['before' => $after, 'after' => $after];
            }
        }
        Db::startTrans();
        try {
            $member = Member::lock(true)->findOrEmpty($uid);
            if ($member->isEmpty()) {
                throw_fail('会员不存在', 1);
            }
            $before = (string) $member->getAttr('freez_supply_money');
            $after  = bcadd($before, $money, 4);
            $member->force()->save(['freez_supply_money' => $after]);
            MemberSupply::create([
                'uid'         => $uid,
                'money'       => $money,
                'trade_type'  => $tradeType,
                'change_type' => MemberSupply::C_INCOME,
                'before'      => $before,
                'after'       => $after,
                'create_time' => time(),
                'extend_id'   => mb_substr($idempotentKey, 0, 250, 'UTF-8'),
                'mark'        => mb_substr($mark, 0, 250, 'UTF-8'),
                'merchant_id' => (int) $member->getAttr('merchant_id'),
                'order_id'    => $orderId,
            ]);
            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            throw $e;
        }
        return ['before' => $before, 'after' => $after];
    }

    /**
     * 从冻结货款扣减（退款回冲；不足封顶）
     */
    public static function deductFrozen(
        int $uid,
        int $tradeType,
        string $money,
        int $orderId = 0,
        string $mark = '',
        string $idempotentKey = ''
    ): array {
        if ($uid <= 0 || bccomp($money, '0', 4) <= 0) {
            return ['before' => '0.0000', 'after' => '0.0000'];
        }
        if ($idempotentKey !== '') {
            $exists = MemberSupply::where('extend_id', $idempotentKey)->findOrEmpty();
            if (!$exists->isEmpty()) {
                $after = (string) Member::where('id', $uid)->value('freez_supply_money');
                return ['before' => $after, 'after' => $after];
            }
        }
        Db::startTrans();
        try {
            $member = Member::lock(true)->findOrEmpty($uid);
            if ($member->isEmpty()) {
                throw_fail('会员不存在', 1);
            }
            $before = (string) $member->getAttr('freez_supply_money');
            $take   = bccomp($money, $before, 4) > 0 ? $before : $money;
            if (bccomp($take, '0', 4) <= 0) {
                Db::rollback();
                return ['before' => $before, 'after' => $before];
            }
            $after = bcsub($before, $take, 4);
            $member->force()->save(['freez_supply_money' => $after]);
            MemberSupply::create([
                'uid'         => $uid,
                'money'       => $take,
                'trade_type'  => $tradeType,
                'change_type' => MemberSupply::C_PAY,
                'before'      => $before,
                'after'       => $after,
                'create_time' => time(),
                'extend_id'   => mb_substr($idempotentKey, 0, 250, 'UTF-8'),
                'mark'        => mb_substr($mark, 0, 250, 'UTF-8'),
                'merchant_id' => (int) $member->getAttr('merchant_id'),
                'order_id'    => $orderId,
            ]);
            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            throw $e;
        }
        return ['before' => $before, 'after' => $after];
    }

    /**
     * 货款解冻：冻结 → 可用（同一事务两条流水）
     */
    public static function unfreeze(
        int $uid,
        string $money,
        int $orderId = 0,
        string $mark = '',
        string $idempotentKey = '',
        string $incomeKey = ''
    ): array {
        if ($uid <= 0 || bccomp($money, '0', 4) <= 0) {
            return ['before' => '0.0000', 'after' => '0.0000'];
        }
        if ($idempotentKey !== '') {
            $exists = MemberSupply::where('extend_id', $idempotentKey)->findOrEmpty();
            if (!$exists->isEmpty()) {
                $after = (string) Member::where('id', $uid)->value('supply_money');
                return ['before' => $after, 'after' => $after];
            }
        }
        Db::startTrans();
        try {
            $member = Member::lock(true)->findOrEmpty($uid);
            if ($member->isEmpty()) {
                throw_fail('会员不存在', 1);
            }
            $frozenBefore = (string) $member->getAttr('freez_supply_money');
            $availBefore  = (string) $member->getAttr('supply_money');
            $take = bccomp($money, $frozenBefore, 4) > 0 ? $frozenBefore : $money;
            if (bccomp($take, '0', 4) <= 0) {
                Db::rollback();
                return ['before' => $availBefore, 'after' => $availBefore];
            }
            $frozenAfter = bcsub($frozenBefore, $take, 4);
            $availAfter  = bcadd($availBefore, $take, 4);
            $member->force()->save([
                'freez_supply_money' => $frozenAfter,
                'supply_money'       => $availAfter,
            ]);
            MemberSupply::create([
                'uid'         => $uid,
                'money'       => $take,
                'trade_type'  => MemberSupply::T_SUPPLY_UNFREEZE,
                'change_type' => MemberSupply::C_PAY,
                'before'      => $frozenBefore,
                'after'       => $frozenAfter,
                'create_time' => time(),
                'extend_id'   => mb_substr($idempotentKey, 0, 250, 'UTF-8'),
                'mark'        => mb_substr('[解冻] ' . $mark, 0, 250, 'UTF-8'),
                'merchant_id' => (int) $member->getAttr('merchant_id'),
                'order_id'    => $orderId,
            ]);
            MemberSupply::create([
                'uid'         => $uid,
                'money'       => $take,
                'trade_type'  => MemberSupply::T_ORDER_SUPPLY,
                'change_type' => MemberSupply::C_INCOME,
                'before'      => $availBefore,
                'after'       => $availAfter,
                'create_time' => time(),
                'extend_id'   => mb_substr($incomeKey !== '' ? $incomeKey : $idempotentKey . ':IN', 0, 250, 'UTF-8'),
                'mark'        => mb_substr($mark, 0, 250, 'UTF-8'),
                'merchant_id' => (int) $member->getAttr('merchant_id'),
                'order_id'    => $orderId,
            ]);
            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            throw $e;
        }
        return ['before' => $availBefore, 'after' => $availAfter];
    }
}
