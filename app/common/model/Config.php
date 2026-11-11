<?php
// +----------------------------------------------------------------------
// | 系统配置模型
// +----------------------------------------------------------------------
declare (strict_types = 1);

namespace app\common\model;

class Config extends BaseModel
{
    protected $name = 'config';

    /** config 表无 delete_time（原库即如此），不做软删 */
}
