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
}
