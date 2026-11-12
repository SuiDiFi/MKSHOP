<?php
// +----------------------------------------------------------------------
// | MKSHOP 分站服务（M6）
// | 语义：域名（Host）→ 分站上下文；分站售价=基础价×(1+rate/100)；
// | 商品可见范围 = goods.vip_id IN (0, 分站id)；订单抽成 = 实付 × share_rate/100 归分站主
// | 红线：抽成入账走 MemberCommissionService（同事务流水+幂等）
// +----------------------------------------------------------------------
declare (strict_types = 1);

namespace app\common\service;

use app\common\model\Goods;
use app\common\model\Order;
use app\common\model\OrderVip;
use app\common\model\VipSite;
use think\facade\Cache;

class VipSiteService
{
    /**
     * 按域名解析分站（未绑定域名返回 null；启用 + 未到期校验）
     */
    public static function resolveByHost(string $host): ?VipSite
    {
        $host = strtolower(trim(preg_replace('/:\d+$/', '', $host)));
        if ($host === '') {
            return null;
        }
        $cacheKey = 'vip_site:host:' . md5($host);
        $cached   = Cache::store('redis')->get($cacheKey);
        if ($cached !== null && $cached !== 0 && $cached !== '0') {
            $site = VipSite::findOrEmpty((int) $cached);
            if (!$site->isEmpty()) {
                return self::valid($site) ? $site : null;
            }
        }
        $site = VipSite::where('domain', $host)->where('status', 1)->findOrEmpty();
        if ($site->isEmpty()) {
            Cache::store('redis')->set($cacheKey, 0, 60);
            return null;
        }
        Cache::store('redis')->set($cacheKey, (int) $site->getAttr('id'), 300);
        return self::valid($site) ? $site : null;
    }

    /**
     * 按分站ID取启用中的分站（下单价上下文）
     */
    public static function activeById(int $vipId): ?VipSite
    {
        if ($vipId <= 0) {
            return null;
        }
        $site = VipSite::findOrEmpty($vipId);
        return self::valid($site) ? $site : null;
    }

    protected static function valid(VipSite $site): bool
    {
        if ((int) $site->getAttr('status') !== 1) {
            return false;
        }
        $expire = (int) $site->getAttr('expire_time');
        return $expire === 0 || $expire > time();
    }

    /**
     * 商品可见范围条件（分站可见 主站通用(goods.vip_id=0) + 本站商品）
     */
    public static function visibleScope($query, int $vipId)
    {
        if ($vipId <= 0) {
            return $query; // 主站看全部（含分站商品，MVP 语义）
        }
        return $query->where(function ($q) use ($vipId) {
            $q->whereOr('vip_id', 0)->whereOr('vip_id', $vipId);
        });
    }

    /**
     * 订单分站抽成结算（订单成功挂载；幂等：order_vip 已存在跳过）
     */
    public static function makeShare(Order $order): void
    {
        $vipId = (int) $order->getAttr('vip_id');
        if ($vipId <= 0) {
            return;
        }
        $exists = OrderVip::where('order_id', (int) $order->getAttr('id'))->count();
        if ($exists > 0) {
            return; // 幂等
        }
        $site = VipSite::findOrEmpty($vipId);
        $shareRate = (string) ($site->isEmpty() ? '0' : $site->getAttr('share_rate'));
        if ($site->isEmpty() || bccomp($shareRate, '0', 2) <= 0) {
            return;
        }
        $total = (string) $order->getAttr('total_price');
        $share = bcdiv(bcmul($total, $shareRate, 6), '100', 4);
        if (bccomp($share, '0.01', 4) < 0) {
            return;
        }
        OrderVip::create([
            'order_id'              => (int) $order->getAttr('id'),
            'vip_id'                => $vipId,
            'uid'                   => (int) $site->getAttr('uid'),
            'commision_price'       => $share,
            'total_commision_price' => $share,
            'back_commision_price'  => '0.00',
            'status'                => 1,
            'sure_status'           => OrderVip::SURE_SETTLED,
            'create_time'           => time(),
            'frozen_time'           => 0,
        ]);
        // 分站主佣金入账（同事务流水 + 幂等键）
        if ((int) $site->getAttr('uid') > 0) {
            MemberCommissionService::changeMoney(
                (int) $site->getAttr('uid'),
                \app\common\model\MemberCommission::T_VIP_SHARE,
                \app\common\model\MemberCommission::C_INCOME,
                $share,
                (int) $order->getAttr('id'),
                '分站订单抽成 ' . (string) $order->getAttr('ordersn'),
                'VIPSHARE:' . (string) $order->getAttr('ordersn')
            );
        }
        Order::where('id', (int) $order->getAttr('id'))->inc('vip_profit_price', (float) $share)->update();
    }

    /**
     * 退款回冲分站抽成（比例 backRate）
     */
    public static function rollbackShare(int $orderId, string $backRate): void
    {
        $rows = OrderVip::where('order_id', $orderId)
            ->whereIn('sure_status', [OrderVip::SURE_SETTLED, OrderVip::SURE_PART])
            ->select();
        foreach ($rows as $row) {
            $total  = (string) $row->getAttr('total_commision_price');
            $backed = (string) $row->getAttr('back_commision_price');
            $remain = bcsub($total, $backed, 4);
            if (bccomp($remain, '0', 4) <= 0) {
                continue;
            }
            $back = bccomp(bcmul($total, $backRate, 4), $remain, 4) > 0 ? $remain : bcmul($total, $backRate, 4);
            if (bccomp($back, '0', 4) <= 0) {
                continue;
            }
            $uid = (int) $row->getAttr('uid');
            if ($uid > 0) {
                MemberCommissionService::changeMoney(
                    $uid,
                    \app\common\model\MemberCommission::T_VIP_SHARE_BACK,
                    \app\common\model\MemberCommission::C_PAY,
                    $back,
                    $orderId,
                    '分站抽成回冲 ' . (string) Order::where('id', $orderId)->value('ordersn'),
                    'VIPSHAREBACK:' . $orderId . ':' . $row->getAttr('id')
                );
            }
            $newBacked = bcadd($backed, $back, 4);
            $row->save([
                'back_commision_price' => $newBacked,
                'sure_status'          => bccomp($newBacked, $total, 4) >= 0 ? OrderVip::SURE_REFUND : OrderVip::SURE_PART,
            ]);
        }
    }
}
