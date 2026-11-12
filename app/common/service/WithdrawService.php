<?php
// +----------------------------------------------------------------------
// | 会员提现服务（M4）
// | 流程：申请（余额/佣金 → 冻结额，写申请单+流水） → 后台审核
// |       通过：扣减冻结额（资金实际出账，线下打款）
// |       拒绝：冻结额退回可用额（写退回流水）
// | 红线：资金变动必须同事务 + SELECT...FOR UPDATE 行锁；申请/审核均幂等（状态机推进）
// +----------------------------------------------------------------------
declare (strict_types = 1);

namespace app\common\service;

use app\common\model\Member;
use app\common\model\MemberCollect;
use app\common\model\MemberCollectWay;
use app\common\model\MemberCommission;
use app\common\model\MemberMoney;
use mks\util\SysConfig;
use think\facade\Db;

class WithdrawService
{
    /**
     * 提现手续费率（%）：按会员提现费率档 member_collect_config [{min,max,rate}] 命中区间
     */
    public static function feeRateOf(string $money): string
    {
        $tiers = SysConfig::get('member_collect_config', []);
        if (is_string($tiers)) {
            $tiers = json_decode($tiers, true) ?: [];
        }
        foreach ((array) $tiers as $tier) {
            $min = (string) ($tier['min'] ?? '0');
            $max = (string) ($tier['max'] ?? '0');
            if (bccomp($money, $min, 4) >= 0 && (bccomp($max, '0', 4) <= 0 || bccomp($money, $max, 4) <= 0)) {
                return (string) ($tier['rate'] ?? '0');
            }
        }
        return '0';
    }

    /**
     * 最低提现金额（费率档首档 min；无档位不限制）
     */
    public static function minMoney(): string
    {
        $tiers = SysConfig::get('member_collect_config', []);
        if (is_string($tiers)) {
            $tiers = json_decode($tiers, true) ?: [];
        }
        $min = null;
        foreach ((array) $tiers as $tier) {
            $v = (string) ($tier['min'] ?? '0');
            if ($min === null || bccomp($v, $min, 4) < 0) {
                $min = $v;
            }
        }
        return $min ?? '0';
    }

    /**
     * 申请提现：可用额 → 冻结额，落申请单 + 流水
     * @param int    $uid       会员ID
     * @param int    $type      MemberCollect::T_BALANCE / T_COMMISSION
     * @param string $money     提现金额
     * @param int    $wayId     提现方式ID（0=未绑定，账户信息取表单）
     * @param array  $account   {account, true_name, way_name, collect_type} 账户快照
     * @param string $mark      备注
     */
    public static function apply(int $uid, int $type, string $money, int $wayId, array $account = [], string $mark = ''): MemberCollect
    {
        if (!in_array($type, [MemberCollect::T_BALANCE, MemberCollect::T_COMMISSION], true)) {
            throw_fail('提现类型错误', 1);
        }
        $money = bcadd($money, '0', 4);
        if (bccomp($money, '0.01', 4) < 0) {
            throw_fail('提现金额不正确', 1);
        }
        $min = self::minMoney();
        if (bccomp($min, '0', 4) > 0 && bccomp($money, $min, 4) < 0) {
            throw_fail('单笔提现最低 ' . $min . ' 元', 1);
        }
        $member = Member::where('id', $uid)->where('delete_time', 0)->findOrEmpty();
        if ($member->isEmpty()) {
            throw_fail('会员不存在', 1);
        }
        $way = null;
        if ($wayId > 0) {
            $way = MemberCollectWay::findOrEmpty($wayId);
            if ($way->isEmpty() || !in_array((int) $way->getAttr('uid'), [0, $uid], true)) {
                throw_fail('提现方式不存在', 1);
            }
        }
        $rate = self::feeRateOf($money);
        $fee  = bccomp($rate, '0', 2) > 0 ? bcdiv(bcmul($money, $rate, 6), '100', 4) : '0.0000';
        if (bccomp($fee, $money, 4) >= 0) {
            throw_fail('提现金额不足以支付手续费', 1);
        }
        $availField = $type === MemberCollect::T_COMMISSION ? 'commission_money' : 'balance';
        $freezField = $type === MemberCollect::T_COMMISSION ? 'freez_commission_money' : 'freez_money';

        Db::startTrans();
        try {
            $member = Member::lock(true)->findOrEmpty($uid);
            if ($member->isEmpty()) {
                throw_fail('会员不存在', 1);
            }
            $before = (string) $member->getAttr($availField);
            if (bccomp($before, $money, 4) < 0) {
                throw_fail($type === MemberCollect::T_COMMISSION ? '可提现佣金不足' : '账户余额不足', 1);
            }
            $after = bcsub($before, $money, 4);
            $member->force()->save([
                $availField => $after,
                $freezField => bcadd((string) $member->getAttr($freezField), $money, 4),
            ]);
            $collect = MemberCollect::create([
                'host'        => (string) request()->host(),
                'uid_type'    => 1,
                'collect_type' => (int) ($account['collect_type'] ?? 1),
                'apply_uid'   => $uid,
                'apply_uname' => (string) $member->getAttr('username'),
                'way_id'      => $wayId,
                'way_name'    => (string) (($account['way_name'] ?? '') !== '' ? $account['way_name'] : ($way ? $way->getAttr('name') : '')),
                'account'     => (string) (($account['account'] ?? '') !== '' ? $account['account'] : ($way ? $way->getAttr('account') : '')),
                'true_name'   => (string) (($account['true_name'] ?? '') !== '' ? $account['true_name'] : ($way ? $way->getAttr('true_name') : '')),
                'money'       => $money,
                'fee'         => $fee,
                'status'      => MemberCollect::ST_WAIT,
                'create_time' => time(),
                'update_time' => 0,
                'mark'        => mb_substr($mark, 0, 200, 'UTF-8'),
                'type'        => $type,
            ]);
            // 流水：申请即从可用额转冻结（资金未出账）
            if ($type === MemberCollect::T_COMMISSION) {
                MemberCommission::create([
                    'uid'         => $uid,
                    'money'       => $money,
                    'trade_type'  => MemberCommission::T_WITHDRAW_APPLY,
                    'change_type' => MemberCommission::C_PAY,
                    'before'      => $before,
                    'after'       => $after,
                    'create_time' => time(),
                    'extend_id'   => 'COLLECT:' . (int) $collect->getAttr('id'),
                    'mark'        => '佣金提现申请（' . MemberCollect::typeText($type) . '）',
                    'order_id'    => 0,
                ]);
            } else {
                MemberMoney::create([
                    'uid'           => $uid,
                    'money'         => $money,
                    'trade_type'    => MemberMoney::T_WITHDRAW_APPLY,
                    'change_type'   => MemberMoney::C_PAY,
                    'before'        => $before,
                    'after'         => $after,
                    'create_time'   => time(),
                    'extend_id'     => 'COLLECT:' . (int) $collect->getAttr('id'),
                    'mark'          => '余额提现申请',
                    'order_id'      => 0,
                    'admin_id'      => 0,
                    'only_order_no' => 'COLLECT:' . (int) $collect->getAttr('id'),
                ]);
            }
            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            throw $e;
        }
        return $collect;
    }

    /**
     * 后台审核（幂等：仅待审核可处理）
     * @param int    $id        申请单ID
     * @param int    $status    MemberCollect::ST_APPROVED / ST_REJECTED
     * @param int    $adminId   审核管理员
     * @param string $adminName 审核管理员名
     * @param string $mark      审核备注
     */
    public static function review(int $id, int $status, int $adminId, string $adminName, string $mark = ''): void
    {
        if (!in_array($status, [MemberCollect::ST_APPROVED, MemberCollect::ST_REJECTED], true)) {
            throw_fail('审核状态错误', 1);
        }
        Db::startTrans();
        try {
            $row = MemberCollect::lock(true)->findOrEmpty($id);
            if ($row->isEmpty()) {
                throw_fail('提现申请不存在', 1);
            }
            if ((int) $row->getAttr('status') !== MemberCollect::ST_WAIT) {
                throw_fail('该提现申请已处理', 1);
            }
            $uid   = (int) $row->getAttr('apply_uid');
            $type  = (int) $row->getAttr('type');
            $money = (string) $row->getAttr('money');
            $member = Member::lock(true)->findOrEmpty($uid);
            if ($member->isEmpty()) {
                throw_fail('会员不存在', 1);
            }
            $availField = $type === MemberCollect::T_COMMISSION ? 'commission_money' : 'balance';
            $freezField = $type === MemberCollect::T_COMMISSION ? 'freez_commission_money' : 'freez_money';
            $freez = (string) $member->getAttr($freezField);
            $used  = bccomp($freez, $money, 4) >= 0 ? $money : $freez; // 冻结额异常时按可用冻结封顶
            $update = [$freezField => bcsub($freez, $used, 4)];

            if ($status === MemberCollect::ST_REJECTED) {
                // 驳回：冻结额退回可用额 + 退回流水
                $before = (string) $member->getAttr($availField);
                $after  = bcadd($before, $used, 4);
                $update[$availField] = $after;
            }
            $member->force()->save($update);

            if ($status === MemberCollect::ST_REJECTED && bccomp($used, '0', 4) > 0) {
                if ($type === MemberCollect::T_COMMISSION) {
                    MemberCommission::create([
                        'uid'         => $uid,
                        'money'       => $used,
                        'trade_type'  => MemberCommission::T_WITHDRAW_REFUND,
                        'change_type' => MemberCommission::C_INCOME,
                        'before'      => $before,
                        'after'       => $after,
                        'create_time' => time(),
                        'extend_id'   => 'COLLECT:REFUND:' . $id,
                        'mark'        => '佣金提现驳回退回',
                        'order_id'    => 0,
                    ]);
                } else {
                    MemberMoney::create([
                        'uid'           => $uid,
                        'money'         => $used,
                        'trade_type'    => MemberMoney::T_WITHDRAW_REFUND,
                        'change_type'   => MemberMoney::C_INCOME,
                        'before'        => $before,
                        'after'         => $after,
                        'create_time'   => time(),
                        'extend_id'     => 'COLLECT:REFUND:' . $id,
                        'mark'          => '余额提现驳回退回',
                        'order_id'      => 0,
                        'admin_id'      => $adminId,
                        'only_order_no' => 'COLLECT:REFUND:' . $id,
                    ]);
                }
            }

            $row->force()->save([
                'status'      => $status,
                'check_time'  => time(),
                'update_time' => time(),
                'admin_id'    => $adminId,
                'admin_uname' => mb_substr($adminName, 0, 100, 'UTF-8'),
                'mark'        => mb_substr($mark !== '' ? $mark : (string) $row->getAttr('mark'), 0, 200, 'UTF-8'),
            ]);
            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            throw $e;
        }
    }
}
