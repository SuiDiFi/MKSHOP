<?php
// +----------------------------------------------------------------------
// | 提现申请单模型（M4；对齐 mks_member_collect）
// +----------------------------------------------------------------------
declare (strict_types = 1);

namespace app\common\model;

class MemberCollect extends BaseModel
{
    protected $name = 'member_collect';

    /** 提现状态 */
    public const ST_WAIT     = 1; // 待审核
    public const ST_APPROVED = 2; // 已通过（已打款）
    public const ST_REJECTED = 3; // 已拒绝

    /** 提现来源 */
    public const T_BALANCE    = 1; // 余额
    public const T_COMMISSION = 2; // 佣金
    public const T_SUPPLY     = 3; // 商户货款

    /**
     * 状态文案（后台/前台共用）
     */
    public static function statusText(int $status): string
    {
        return [self::ST_WAIT => '待审核', self::ST_APPROVED => '已通过', self::ST_REJECTED => '已拒绝'][$status] ?? '未知';
    }

    /**
     * 来源文案
     */
    public static function typeText(int $type): string
    {
        return [self::T_BALANCE => '余额', self::T_COMMISSION => '佣金', self::T_SUPPLY => '商户货款'][$type] ?? '未知';
    }
}
