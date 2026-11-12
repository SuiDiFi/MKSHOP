<?php
// +----------------------------------------------------------------------
// | 客服消息模型（M5）
// +----------------------------------------------------------------------
declare (strict_types = 1);

namespace app\common\model;

class KefuMessage extends BaseModel
{
    protected $name = 'kefu_message';

    public const FROM_MEMBER = 1; // 会员
    public const FROM_ADMIN  = 2; // 客服
    public const FROM_SYSTEM = 3; // 系统
}
