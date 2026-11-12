<?php
// +----------------------------------------------------------------------
// | 分站模型（M6）
// +----------------------------------------------------------------------
declare (strict_types = 1);

namespace app\common\model;

use think\model\concern\SoftDelete;

class VipSite extends BaseModel
{
    use SoftDelete;

    protected $name = 'vip_site';

    protected $deleteTime = 'delete_time';
    protected $defaultSoftDelete = 0;

    /**
     * 分站售价（基础价 × (1 + rate/100)）
     */
    public function priceFor(string $basePrice): string
    {
        $rate = (string) $this->getAttr('rate');
        if (bccomp($rate, '0', 2) <= 0) {
            return $basePrice;
        }
        return bcadd($basePrice, bcmul($basePrice, bcdiv($rate, '100', 6), 4), 4);
    }
}
