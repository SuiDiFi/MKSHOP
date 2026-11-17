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
        // 幂等快路径：同一 only_order_no 只入账一次。
        // 权威判定是 uk_only_order_no 唯一索引 + 下面的 1062 捕获——本检查在事务外，挡不住并发。
        if ($idempotentKey !== '') {
            $hit = self::alreadyBooked($idempotentKey);
            if ($hit !== null) {
                return $hit;
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
                // 无幂等键写 NULL：唯一索引下空串会互相冲突，NULL 才允许无限多条
                'only_order_no' => $idempotentKey !== '' ? $idempotentKey : null,
            ]);
            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            // 并发同键：余额已随事务回滚，唯一索引拦下的这次按"已入账"幂等返回
            if ($idempotentKey !== '' && self::isDuplicateKey($e)) {
                $hit = self::alreadyBooked($idempotentKey);
                if ($hit !== null) {
                    return $hit;
                }
            }
            throw $e;
        }
        return ['before' => $before, 'after' => $after];
    }

    /**
     * 幂等命中：返回既有流水所属会员的当前余额（与入账成功返回同形）
     */
    protected static function alreadyBooked(string $key): ?array
    {
        $exists = MemberMoney::where('only_order_no', $key)->findOrEmpty();
        if ($exists->isEmpty()) {
            return null;
        }
        $balance = (string) Member::where('id', (int) $exists->getAttr('uid'))->value('balance');
        return ['before' => $balance, 'after' => $balance];
    }

    /**
     * 是否 MySQL 1062 唯一键冲突。think-orm 把驱动码塞进 debug data，getCode() 恒为 10501。
     */
    protected static function isDuplicateKey(\Throwable $e): bool
    {
        if (!$e instanceof \think\db\exception\PDOException) {
            return false;
        }
        $info = $e->getData()['PDO Error Info'] ?? [];
        return (int) ($info['Driver Error Code'] ?? 0) === 1062;
    }
}
