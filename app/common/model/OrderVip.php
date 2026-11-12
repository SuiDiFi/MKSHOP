<?php
// +----------------------------------------------------------------------
// | 订单分站抽成模型（M6）
// +----------------------------------------------------------------------
declare (strict_types = 1);

namespace app\common\model;

class OrderVip extends BaseModel
{
    protected $name = 'order_vip';

    /** sure_status */
    public const SURE_WAIT    = 1;
    public const SURE_SETTLED = 2;
    public const SURE_REFUND  = 3;
    public const SURE_PART    = 4;
}
