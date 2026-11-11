<?php
// +----------------------------------------------------------------------
// | 支付方式基础定义
// +----------------------------------------------------------------------
declare (strict_types = 1);

namespace app\common\model;

use think\model\concern\SoftDelete;

class PayList extends BaseModel
{
    use SoftDelete;

    protected $name = 'pay_list';
    protected $deleteTime = 'delete_time';
    protected $defaultSoftDelete = 0;
}
