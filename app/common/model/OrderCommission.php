<?php
// +----------------------------------------------------------------------
// | 订单分销佣金模型（M4；对齐 mks_order_commission）
// +----------------------------------------------------------------------
declare (strict_types = 1);

namespace app\common\model;

class OrderCommission extends BaseModel
{
    protected $name = 'order_commission';

    /** sure_status 结算状态 */
    public const SURE_WAIT    = 1; // 待结算（冻结期）
    public const SURE_SETTLED = 2; // 已结算（已入账）
    public const SURE_REFUND  = 3; // 已退款（全额回冲）
    public const SURE_PART    = 4; // 部分退款
}
