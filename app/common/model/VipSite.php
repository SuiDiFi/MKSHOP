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
     * 启用且未到期
     */
    public function isValid(): bool
    {
        if ((int) $this->getAttr('status') !== 1) {
            return false;
        }
        $expire = (int) $this->getAttr('expire_time');
        return $expire === 0 || $expire > time();
    }

    /**
     * 分站售价（基础价 × (1 + rate/100)；商品级覆盖走 VipPriceService）
     */
    public function priceFor(string $basePrice): string
    {
        $rate = (string) $this->getAttr('rate');
        if (bccomp($rate, '0', 2) <= 0) {
            return $basePrice;
        }
        return bcadd($basePrice, bcmul($basePrice, bcdiv($rate, '100', 6), 4), 4);
    }

    /**
     * 抽成/货款冻结分钟（0=立即结算）
     */
    public function settleMinutes(string $configName, int $default): int
    {
        $minutes = (int) $this->getAttr('settle_minutes');
        if ($minutes > 0) {
            return $minutes;
        }
        return max(0, (int) \mks\util\SysConfig::get($configName, $default));
    }
}
