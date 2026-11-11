<?php
// +----------------------------------------------------------------------
// | 订单模型（M2）
// +----------------------------------------------------------------------
declare (strict_types = 1);

namespace app\common\model;

use think\model\concern\SoftDelete;

class Order extends BaseModel
{
    use SoftDelete;

    protected $name = 'order';

    protected $deleteTime = 'delete_time';
    protected $defaultSoftDelete = 0;

    protected $hidden = ['docking_order_array', 'price_set_content', 'vip_set_content', 'user_agent'];

    // 订单状态（对齐 mks_sys_dict order_status）
    public const ST_CANCEL_PAY = -1;  // 取消支付
    public const ST_WAIT_PAY   = 0;   // 等待支付
    public const ST_WAIT_DEAL  = 1;   // 等待处理
    public const ST_DEALING    = 2;   // 正在处理
    public const ST_SUCCESS    = 3;   // 交易成功
    public const ST_CANCEL     = 4;   // 取消交易
    public const ST_REFUNDED   = 5;   // 已经退款
}
