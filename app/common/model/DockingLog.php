<?php
// +----------------------------------------------------------------------
// | 渠道接口日志模型
// +----------------------------------------------------------------------
declare (strict_types = 1);

namespace app\common\model;

class DockingLog extends BaseModel
{
    protected $name = 'docking_log';
    protected $updateTime = false;

    public const L_GOODS = 1; // 商品同步
    public const L_BUY   = 2; // 下单
    public const L_QUERY = 3; // 查单
}
