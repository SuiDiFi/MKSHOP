<?php
// +----------------------------------------------------------------------
// | 商户入驻申请模型（M6-B）
// +----------------------------------------------------------------------
declare (strict_types = 1);

namespace app\common\model;

class MerchantApply extends BaseModel
{
    protected $name = 'merchant_apply';

    /** 审核状态 */
    public const ST_WAIT     = 1; // 待审核
    public const ST_APPROVED = 2; // 通过
    public const ST_REJECTED = 3; // 拒绝

    public static function statusText(int $status): string
    {
        return [self::ST_WAIT => '待审核', self::ST_APPROVED => '已通过', self::ST_REJECTED => '已拒绝'][$status] ?? '未知';
    }
}
