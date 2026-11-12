<?php
// +----------------------------------------------------------------------
// | 分站定价服务（M6）：三级定价
// | 商品级覆盖 vip_goods_price > 分站全局比例 vip_site.rate > 平台基准价
// | 红线：列表/详情/下单三处同源，前端不传价
// +----------------------------------------------------------------------
declare (strict_types = 1);

namespace app\common\service;

use app\common\model\Goods;
use app\common\model\VipGoodsPrice;
use app\common\model\VipSite;

class VipPriceService
{
    /**
     * 商品在分站上下文的价格（含可见性）
     * @return array{visible:bool, base:string, price:string, add:string, mode:int, value:string, override:bool}
     */
    public static function resolve(Goods $goods, ?VipSite $site): array
    {
        $base = (string) $goods->getAttr('real_price');
        if ($site === null) {
            return self::result(true, $base, $base, 0, '0', false);
        }
        $vipId = (int) $site->getAttr('id');
        if ($vipId <= 0 || !$site->isValid()) {
            return self::result(false, $base, $base, 0, '0', false);
        }
        $goodsVip   = (int) $goods->getAttr('vip_id');
        $allowMain  = (int) $site->getAttr('allow_main_goods') === 1;
        if ($goodsVip !== 0 && $goodsVip !== $vipId) {
            return self::result(false, $base, $base, 0, '0', false);
        }
        if ($goodsVip === 0 && !$allowMain) {
            return self::result(false, $base, $base, 0, '0', false);
        }
        $override = VipGoodsPrice::where('vip_id', $vipId)
            ->where('goods_id', (int) $goods->getAttr('id'))
            ->where('status', 1)
            ->findOrEmpty();
        return self::price($base, $site, $override->isEmpty() ? null : $override);
    }

    /**
     * 列表批量用：基于已查出的覆盖行计算价格
     */
    public static function price(string $base, VipSite $site, ?VipGoodsPrice $override): array
    {
        $price = $base;
        $mode  = 0;
        $value = '0';
        if ($override !== null) {
            $mode  = (int) $override->getAttr('mode');
            $value = (string) $override->getAttr('value');
            $price = match ($mode) {
                VipGoodsPrice::MODE_RATE => bcadd($base, bcmul($base, bcdiv($value, '100', 6), 4), 4),
                VipGoodsPrice::MODE_ADD  => bcadd($base, $value, 4),
                VipGoodsPrice::MODE_FIX  => $value,
                default                  => $base,
            };
        } else {
            $rate = (string) $site->getAttr('rate');
            if (bccomp($rate, '0', 2) > 0) {
                $price = bcadd($base, bcmul($base, bcdiv($rate, '100', 6), 4), 4);
                $mode  = VipGoodsPrice::MODE_RATE;
                $value = $rate;
            }
        }
        if (bccomp($price, '0', 4) < 0) {
            $price = '0.0000';
        }
        return self::result(true, $base, $price, $mode, $value, $override !== null);
    }

    /**
     * 商品可见范围（分站）
     */
    public static function applyVisibleScope($query, ?VipSite $site)
    {
        if ($site === null) {
            return $query;
        }
        $vipId = (int) $site->getAttr('id');
        if ($vipId <= 0) {
            return $query;
        }
        if ((int) $site->getAttr('allow_main_goods') === 1) {
            return $query->where(function ($q) use ($vipId) {
                $q->whereOr('vip_id', 0)->whereOr('vip_id', $vipId);
            });
        }
        return $query->where('vip_id', $vipId);
    }

    protected static function result(bool $visible, string $base, string $price, int $mode, string $value, bool $override): array
    {
        return [
            'visible'  => $visible,
            'base'     => $base,
            'price'    => $price,
            'add'      => bcsub($price, $base, 4),
            'mode'     => $mode,
            'value'    => $value,
            'override' => $override,
        ];
    }
}
