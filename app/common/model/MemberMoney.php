<?php
// +----------------------------------------------------------------------
// | 会员余额流水模型
// +----------------------------------------------------------------------
declare (strict_types = 1);

namespace app\common\model;

class MemberMoney extends BaseModel
{
    protected $name = 'member_money';

    protected $updateTime = false; // 表无 update_time

    /** trade_type 枚举（MKSHOP 自研，原库为开放枚举） */
    public const T_ORDER_PAY      = 1;    // 下单扣款
    public const T_ORDER_REFUND   = 2;    // 订单退款
    public const T_WITHDRAW_APPLY  = 3;    // 余额提现冻结（申请）
    public const T_WITHDRAW_REFUND = 4;    // 余额提现驳回退回
    public const T_RECHARGE_ONLINE = 1000; // 在线充值
    public const T_ADMIN_ADD      = 1001; // 后台加款
    public const T_ADMIN_SUB      = 1002; // 后台扣款

    /** change_type */
    public const C_INCOME = 1; // 收入
    public const C_PAY    = 2; // 支出

    public const TRADE_TYPE_TEXT = [
        self::T_ORDER_PAY       => '订单扣款',
        self::T_ORDER_REFUND    => '订单退款',
        self::T_WITHDRAW_APPLY  => '提现冻结',
        self::T_WITHDRAW_REFUND => '提现退回',
        self::T_RECHARGE_ONLINE => '在线充值',
        self::T_ADMIN_ADD       => '后台加款',
        self::T_ADMIN_SUB       => '后台扣款',
    ];
}
