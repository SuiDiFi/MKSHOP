<?php
// +----------------------------------------------------------------------
// | 会员佣金流水模型（M4；结构对齐 member_money，账户=member.commission_money）
// +----------------------------------------------------------------------
declare (strict_types = 1);

namespace app\common\model;

class MemberCommission extends BaseModel
{
    protected $name = 'member_commission';

    protected $updateTime = false; // 表无 update_time

    /** trade_type 枚举（MKSHOP 自研） */
    public const T_ORDER_COMMISSION = 1;    // 订单佣金入账
    public const T_COMMISSION_BACK  = 2;    // 订单退款佣金回冲
    public const T_VIP_SHARE        = 3;    // 分站订单抽成（M6）
    public const T_VIP_SHARE_BACK   = 4;    // 分站抽成回冲（M6）
    public const T_WITHDRAW_APPLY   = 5;    // 佣金提现冻结（申请）
    public const T_WITHDRAW_REFUND  = 6;    // 佣金提现驳回退回
    public const T_VIP_SHARE_FREEZE   = 7;  // 分站抽成冻结（入冻结佣金）
    public const T_VIP_SHARE_UNFREEZE = 8;  // 分站抽成解冻（冻结转可用）
    public const T_ADMIN_ADD        = 1001; // 后台加佣金
    public const T_ADMIN_SUB        = 1002; // 后台扣佣金

    /** change_type */
    public const C_INCOME = 1; // 收入
    public const C_PAY    = 2; // 支出
}
