<?php
// +----------------------------------------------------------------------
// | 订单发货/库存流水
// +----------------------------------------------------------------------
declare (strict_types = 1);

namespace app\common\model;

class OrderDolog extends BaseModel
{
    protected $name = 'order_dolog';
    protected $updateTime = false;

    public const LOG_DEC = 0; // 扣减
    public const LOG_BACK = 1; // 回退
}
