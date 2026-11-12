<?php
// +----------------------------------------------------------------------
// | 会员提现方式模型（M4；对齐 mks_member_collect_way）
// +----------------------------------------------------------------------
declare (strict_types = 1);

namespace app\common\model;

class MemberCollectWay extends BaseModel
{
    protected $name = 'member_collect_way';

    /** 审核状态 */
    public const ST_WAIT     = 1; // 待审核
    public const ST_APPROVED = 2; // 已通过
    public const ST_REJECTED = 3; // 已拒绝
}
