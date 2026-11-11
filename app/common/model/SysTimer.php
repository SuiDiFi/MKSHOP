<?php
// +----------------------------------------------------------------------
// | 定时任务模型
// +----------------------------------------------------------------------
declare (strict_types = 1);

namespace app\common\model;

use think\model\concern\SoftDelete;

class SysTimer extends BaseModel
{
    use SoftDelete;

    protected $name = 'sys_timer';

    protected $deleteTime = 'delete_time';
    protected $defaultSoftDelete = 0;
}
