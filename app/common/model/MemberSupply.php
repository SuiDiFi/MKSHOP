<?php
// +----------------------------------------------------------------------
// | 供货商货款流水模型（M6）
// +----------------------------------------------------------------------
declare (strict_types = 1);

namespace app\common\model;

class MemberSupply extends BaseModel
{
    protected $name = 'member_supply_money';

    protected $updateTime = false;

    /** trade_type */
    public const T_ORDER_SUPPLY = 1;    // 订单货款入账
    public const T_SUPPLY_BACK  = 2;    // 退款货款回冲
    public const T_ADMIN_ADD    = 1001;
    public const T_ADMIN_SUB    = 1002;

    public const C_INCOME = 1;
    public const C_PAY    = 2;
}
