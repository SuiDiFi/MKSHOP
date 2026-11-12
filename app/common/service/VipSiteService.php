<?php
// +----------------------------------------------------------------------
// | MKSHOP 分站服务（M6）
// | 域名解析：mks_sys_url（域名/二级目录）→ 分站；主站 vip_id=0
// | 分站售价：vip_goods_price 商品级覆盖 > vip_site.rate 全局比例 > 基准价
// | 抽成口径：分站毛利 =（分站售价 − 基准价）× 数量；平台抽成 = 毛利 × share_rate%；
// |           分站主得 毛利 ×（1 − share_rate%），先进冻结佣金，到期解冻
// | 红线：资金变动走 MemberCommissionService（同事务流水+幂等）
// +----------------------------------------------------------------------
declare (strict_types = 1);

namespace app\common\service;

use app\common\model\MemberCommission;
use app\common\model\Order;
use app\common\model\OrderVip;
use app\common\model\SysUrl;
use app\common\model\VipSite;
use mks\util\SysConfig;
use think\facade\Cache;

class VipSiteService
{
    /** 分站可覆盖配置白名单（D13） */
    public const CONFIG_WHITELIST = [
        'site_name'       => '',
        'site_logo'       => '',
        'theme_color'     => '#409eff',
        'seo_title'       => '',
        'seo_keywords'    => '',
        'seo_description' => '',
        'kefu_qq'         => '',
        'kefu_wx'         => '',
        'close_site'      => '0',
        'close_tip'       => '站点维护中，请稍后再试',
    ];

    /** 主站保留路径前缀（不参与二级目录分站解析） */
    protected const RESERVED_PREFIX = [
        'front', 'h5', 'api', 'admin', 'dockapi', 'install', 'merchant', 'vip',
        'front-web', 'h5-web', 'admin-web', 'static', 'uploads', 'favicon.ico', 'index.php',
    ];

    /**
     * 按域名/二级目录解析分站（未绑定返回 null = 主站）
     */
    public static function resolveByHost(string $host, string $path = ''): ?VipSite
    {
        $host = strtolower(trim(preg_replace('/:\d+$/', '', $host)));
        if ($host !== '') {
            $cacheKey = 'vip_site:host:' . md5($host);
            $cached   = Cache::store('redis')->get($cacheKey);
            if ($cached !== null) {
                $site = self::byId((int) $cached);
                if ($site === null || $site->isValid()) {
                    return $site;
                }
            }
            $url = SysUrl::where('url', $host)->findOrEmpty();
            if (!$url->isEmpty()) {
                $vipId = (int) $url->getAttr('vip_id');
                if ($vipId <= 0) {
                    Cache::store('redis')->set($cacheKey, 0, 300);
                    return null; // 显式主站域名
                }
                $site = self::activeById($vipId);
                Cache::store('redis')->set($cacheKey, $site === null ? 0 : $vipId, 300);
                return $site;
            }
        }
        return self::resolveByPath($path);
    }

    /**
     * 二级目录解析：/shop/xxx → path_prefix=shop
     */
    public static function resolveByPath(string $path): ?VipSite
    {
        $prefix = strtolower(trim(explode('/', trim($path, '/'))[0] ?? ''));
        if ($prefix === '' || in_array($prefix, self::RESERVED_PREFIX, true)) {
            return null;
        }
        $cacheKey = 'vip_site:path:' . md5($prefix);
        $cached   = Cache::store('redis')->get($cacheKey);
        if ($cached !== null) {
            $site = self::byId((int) $cached);
            if ($site === null || $site->isValid()) {
                return $site;
            }
        }
        $site = VipSite::where('path_prefix', $prefix)->where('status', 1)->findOrEmpty();
        Cache::store('redis')->set($cacheKey, $site->isEmpty() ? 0 : (int) $site->getAttr('id'), 300);
        return $site->isEmpty() || !$site->isValid() ? null : $site;
    }

    protected static function byId(int $vipId): ?VipSite
    {
        if ($vipId <= 0) {
            return null;
        }
        $site = VipSite::findOrEmpty($vipId);
        return $site->isEmpty() ? null : $site;
    }

    /**
     * 按分站ID取启用中的分站
     */
    public static function activeById(int $vipId): ?VipSite
    {
        $site = self::byId($vipId);
        return $site !== null && $site->isValid() ? $site : null;
    }

    /**
     * 商品可见范围条件（allow_main_goods=0 时只看本站商品）
     */
    public static function visibleScope($query, int $vipId)
    {
        if ($vipId <= 0) {
            return $query; // 主站看全部
        }
        $site = self::byId($vipId);
        if ($site === null) {
            return $query->where('vip_id', $vipId);
        }
        if ((int) $site->getAttr('allow_main_goods') === 1) {
            return $query->where(function ($q) use ($vipId) {
                $q->whereOr('vip_id', 0)->whereOr('vip_id', $vipId);
            });
        }
        return $query->where('vip_id', $vipId);
    }

    /**
     * 站点前台信息（主题/配置/关站）
     */
    public static function siteInfo(?VipSite $site): array
    {
        if ($site === null) {
            return [
                'vip_id'          => 0,
                'name'            => (string) SysConfig::get('site_name', 'MKSHOP'),
                'logo'            => (string) SysConfig::get('site_logo', ''),
                'theme_color'     => (string) SysConfig::get('theme_color', '#409eff'),
                'seo_title'       => (string) SysConfig::get('seo_title', ''),
                'seo_description' => (string) SysConfig::get('site_desc', ''),
                'kefu_qq'         => (string) SysConfig::get('kefu_qq', ''),
                'kefu_wx'         => (string) SysConfig::get('kefu_wx', ''),
                'close_site'      => 0,
                'close_tip'       => '',
                'allow_main_goods' => 1,
                'domain'          => '',
            ];
        }
        $vipId = (int) $site->getAttr('id');
        $cfg   = self::configRead($vipId);
        return [
            'vip_id'           => $vipId,
            'name'             => $cfg['site_name'] !== '' ? $cfg['site_name'] : (string) $site->getAttr('name'),
            'logo'             => $cfg['site_logo'],
            'theme_color'      => $cfg['theme_color'] !== '' ? $cfg['theme_color'] : '#409eff',
            'seo_title'        => $cfg['seo_title'],
            'seo_description'  => $cfg['seo_description'],
            'kefu_qq'          => $cfg['kefu_qq'],
            'kefu_wx'          => $cfg['kefu_wx'],
            'close_site'       => (int) $cfg['close_site'] === 1 ? 1 : 0,
            'close_tip'        => $cfg['close_tip'],
            'allow_main_goods' => (int) $site->getAttr('allow_main_goods') === 1 ? 1 : 0,
            'domain'           => (string) $site->getAttr('domain'),
        ];
    }

    public static function configRead(int $vipId): array
    {
        $out = [];
        foreach (self::CONFIG_WHITELIST as $name => $default) {
            $v = SysConfig::get($name, $default, $vipId);
            if (is_bool($v)) {
                $v = $v ? '1' : '0';
            } elseif (is_array($v)) {
                $v = implode(',', array_map('strval', $v));
            } elseif (!is_scalar($v)) {
                $v = '';
            }
            $out[$name] = (string) $v;
        }
        return $out;
    }

    public static function configWrite(int $vipId, array $data): void
    {
        foreach (self::CONFIG_WHITELIST as $name => $default) {
            if (!array_key_exists($name, $data)) {
                continue;
            }
            $value = $data[$name];
            if (is_bool($value)) {
                $value = $value ? '1' : '0';
            } elseif (!is_scalar($value) && $value !== null) {
                continue;
            }
            SysConfig::set($name, mb_substr((string) $value, 0, 2000, 'UTF-8'), $vipId);
        }
    }

    /**
     * 订单分站抽成（订单成功挂载；幂等：order_vip 已存在跳过）
     * 毛利 =（分站售价 − 基准价）× 数量；平台抽成 = 毛利 × share_rate%；站长得剩余毛利
     */
    public static function makeShare(Order $order): void
    {
        $vipId = (int) $order->getAttr('vip_id');
        if ($vipId <= 0) {
            return;
        }
        // 分站主自购不计抽成
        if ((int) $order->getAttr('vip_uid') > 0
            && (int) $order->getAttr('vip_uid') === (int) $order->getAttr('uid')) {
            return;
        }
        if (OrderVip::where('order_id', (int) $order->getAttr('id'))->count() > 0) {
            return; // 幂等
        }
        $site = self::byId($vipId);
        if ($site === null) {
            return;
        }
        $add    = (string) $order->getAttr('vip_add_price');
        $qty    = (string) max(1, (int) $order->getAttr('quantity'));
        $gross  = bcmul($add, $qty, 4);
        if (bccomp($gross, '0.01', 4) < 0) {
            return; // 无毛利
        }
        $shareRate   = (string) $site->getAttr('share_rate');
        $platformCut = bcmul($gross, bcdiv($shareRate, '100', 6), 4);
        $vipShare    = bcsub($gross, $platformCut, 4);
        if (bccomp($vipShare, '0.01', 4) < 0) {
            // 平台拿走全部毛利，仅记录平台利润
            Order::where('id', (int) $order->getAttr('id'))->inc('vip_profit_price', (float) $gross)->update();
            return;
        }
        $minutes = $site->settleMinutes('order_vip_sure_time', 1440);
        $frozen  = $minutes > 0 ? time() + $minutes * 60 : 0;
        $uid     = (int) $site->getAttr('uid');
        OrderVip::create([
            'order_id'              => (int) $order->getAttr('id'),
            'vip_id'                => $vipId,
            'uid'                   => $uid,
            'commision_price'       => $vipShare,
            'total_commision_price' => $vipShare,
            'back_commision_price'  => '0.0000',
            'status'                => 1,
            'sure_status'           => $frozen > 0 ? OrderVip::SURE_WAIT : OrderVip::SURE_SETTLED,
            'create_time'           => time(),
            'frozen_time'           => $frozen,
        ]);
        if ($uid <= 0) {
            return;
        }
        $ordersn = (string) $order->getAttr('ordersn');
        if ($frozen > 0) {
            MemberCommissionService::freeze(
                $uid,
                MemberCommission::T_VIP_SHARE_FREEZE,
                $vipShare,
                (int) $order->getAttr('id'),
                '分站抽成冻结 ' . $ordersn,
                'VIPFREEZE:' . $ordersn
            );
        } else {
            MemberCommissionService::changeMoney(
                $uid,
                MemberCommission::T_VIP_SHARE,
                MemberCommission::C_INCOME,
                $vipShare,
                (int) $order->getAttr('id'),
                '分站订单抽成 ' . $ordersn,
                'VIPSHARE:' . $ordersn
            );
        }
        // 平台留存抽成（对账用）
        Order::where('id', (int) $order->getAttr('id'))->inc('vip_profit_price', (float) $platformCut)->update();
    }

    /**
     * 到期解冻分站抽成（定时任务）
     * @return array{scanned:int, settled:int, failed:int}
     */
    public static function settleDue(int $limit = 200): array
    {
        $rows = OrderVip::where('sure_status', OrderVip::SURE_WAIT)
            ->where('frozen_time', '>', 0)
            ->where('frozen_time', '<=', time())
            ->order('id asc')
            ->limit($limit)
            ->select();
        $settled = 0;
        $failed  = 0;
        foreach ($rows as $row) {
            if (self::settleRow($row)) {
                $settled++;
            } else {
                $failed++;
            }
        }
        return ['scanned' => count($rows), 'settled' => $settled, 'failed' => $failed];
    }

    /**
     * 解冻单行（到期或后台手动）
     */
    public static function settleRow(OrderVip $row): bool
    {
        if ((int) $row->getAttr('sure_status') !== OrderVip::SURE_WAIT) {
            return false;
        }
        $total  = (string) $row->getAttr('total_commision_price');
        $backed = (string) $row->getAttr('back_commision_price');
        $amount = bcsub($total, $backed, 4);
        $uid    = (int) $row->getAttr('uid');
        $orderId = (int) $row->getAttr('order_id');
        $id     = (int) $row->getAttr('id');
        if (bccomp($amount, '0', 4) <= 0) {
            $row->save(['sure_status' => OrderVip::SURE_REFUND]);
            return true;
        }
        if ($uid > 0) {
            $ordersn = (string) Order::where('id', $orderId)->value('ordersn');
            MemberCommissionService::unfreeze(
                $uid,
                $amount,
                MemberCommission::T_VIP_SHARE,
                MemberCommission::T_VIP_SHARE_UNFREEZE,
                $orderId,
                '分站抽成解冻 ' . $ordersn,
                'VIPUNFREEZE:' . $id,
                'VIPSHARE:' . $id
            );
        }
        $row->save(['sure_status' => OrderVip::SURE_SETTLED]);
        return true;
    }

    /**
     * 退款回冲分站抽成（冻结中扣冻结，已结算扣可用）
     */
    public static function rollbackShare(int $orderId, string $backRate): void
    {
        $rows = OrderVip::where('order_id', $orderId)
            ->whereIn('sure_status', [OrderVip::SURE_WAIT, OrderVip::SURE_SETTLED, OrderVip::SURE_PART])
            ->select();
        $ordersn = (string) Order::where('id', $orderId)->value('ordersn');
        foreach ($rows as $row) {
            $total  = (string) $row->getAttr('total_commision_price');
            $backed = (string) $row->getAttr('back_commision_price');
            $remain = bcsub($total, $backed, 4);
            if (bccomp($remain, '0', 4) <= 0) {
                continue;
            }
            $back = bcmul($total, $backRate, 4);
            if (bccomp($back, $remain, 4) > 0) {
                $back = $remain;
            }
            if (bccomp($back, '0', 4) <= 0) {
                continue;
            }
            $uid = (int) $row->getAttr('uid');
            $key = 'VIPSHAREBACK:' . $orderId . ':' . (int) $row->getAttr('id');
            if ($uid > 0) {
                if ((int) $row->getAttr('sure_status') === OrderVip::SURE_WAIT) {
                    MemberCommissionService::deductFrozen(
                        $uid,
                        MemberCommission::T_VIP_SHARE_BACK,
                        $back,
                        $orderId,
                        '分站抽成回冲 ' . $ordersn,
                        $key
                    );
                } else {
                    MemberCommissionService::changeMoney(
                        $uid,
                        MemberCommission::T_VIP_SHARE_BACK,
                        MemberCommission::C_PAY,
                        $back,
                        $orderId,
                        '分站抽成回冲 ' . $ordersn,
                        $key
                    );
                }
            }
            $newBacked = bcadd($backed, $back, 4);
            $full      = bccomp($newBacked, $total, 4) >= 0;
            $row->save([
                'back_commision_price' => $newBacked,
                'sure_status'          => $full
                    ? OrderVip::SURE_REFUND
                    : ((int) $row->getAttr('sure_status') === OrderVip::SURE_WAIT ? OrderVip::SURE_WAIT : OrderVip::SURE_PART),
            ]);
        }
    }
}
