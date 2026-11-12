<?php
// +----------------------------------------------------------------------
// | 商户/供货商店铺模型（M6-B）
// +----------------------------------------------------------------------
declare (strict_types = 1);

namespace app\common\model;

use think\model\concern\SoftDelete;

class Merchant extends BaseModel
{
    use SoftDelete;

    protected $name = 'merchant';

    protected $deleteTime = 'delete_time';
    protected $defaultSoftDelete = 0;

    /** 结算方式 */
    public const SETTLE_COST = 1; // 按成本结算（MVP）
    public const SETTLE_SALE = 2; // 按销售价结算（预留）

    public static function statusText(int $status): string
    {
        return $status === 1 ? '正常' : '停用';
    }
}
