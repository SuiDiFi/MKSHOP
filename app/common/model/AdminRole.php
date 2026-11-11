<?php
// +----------------------------------------------------------------------
// | 后台角色模型
// +----------------------------------------------------------------------
declare (strict_types = 1);

namespace app\common\model;

use think\model\concern\SoftDelete;

class AdminRole extends BaseModel
{
    use SoftDelete;

    protected $name = 'admin_role';

    protected $deleteTime = 'delete_time';
    protected $defaultSoftDelete = 0;
}
