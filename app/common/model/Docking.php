<?php
// +----------------------------------------------------------------------
// | 对接供货渠道模型
// +----------------------------------------------------------------------
declare (strict_types = 1);

namespace app\common\model;

class Docking extends BaseModel
{
    protected $name = 'docking';

    protected $hidden = ['password', 'pay_password'];

    public const DRIVER_V2 = 'kasushou_v2'; // KASUSHOU 2.0 官方协议（M3 第一适配）
}
