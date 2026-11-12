<?php
// +----------------------------------------------------------------------
// | 客服会话模型（M5）
// +----------------------------------------------------------------------
declare (strict_types = 1);

namespace app\common\model;

use think\model\concern\SoftDelete;

class KefuSession extends BaseModel
{
    use SoftDelete;

    protected $name = 'kefu_session';

    protected $deleteTime = 'delete_time';
    protected $defaultSoftDelete = 0;

    public const ST_OPEN   = 1; // 进行中
    public const ST_CLOSED = 2; // 已关闭
}
