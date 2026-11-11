<?php
// +----------------------------------------------------------------------
// | 支付单（支付中枢：充值/下单结算统一先建此单）
// +----------------------------------------------------------------------
declare (strict_types = 1);

namespace app\common\model;

class RechargeLog extends BaseModel
{
    protected $name = 'recharge_log';

    // 充值单类型（对齐原系统 recharge_log.type）
    public const T_RECHARGE = 1; // 充值
    public const T_ORDER    = 2; // 下单

    public const ST_WAIT   = 0; // 待支付
    public const ST_PAID   = 1; // 已支付
    public const ST_REFUND = 2; // 已退款
}
