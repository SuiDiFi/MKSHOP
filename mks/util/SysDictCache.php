<?php
// +----------------------------------------------------------------------
// | MKSHOP 字典缓存载体（独立封装便于整体失效）
// +----------------------------------------------------------------------
declare (strict_types = 1);

namespace mks\util;

use think\facade\Cache;

class SysDictCache
{
    public static function get(string $key): mixed
    {
        return Cache::store('redis')->get($key, null);
    }

    public static function set(string $key, mixed $value, int $ttl = 3600): void
    {
        Cache::store('redis')->set($key, $value, $ttl);
    }

    public static function flush(): void
    {
        // 字典类型有限，按已知类型清键
        Cache::store('redis')->delete(array_map(
            fn ($t) => 'dict:' . $t . ':0',
            ['order_status', 'goods_type', 'goods_status', 'provide_type', 'pay_type', 'card_status',
             'after_sale_status', 'settle_status', 'withdraw_status', 'money_type', 'commission_type',
             'supply_type', 'recharge_type', 'invoice_type', 'invoice_kind', 'invoice_status', 'member_status',
             'audit_status', 'notice_receiver', 'platform']
        ));
    }
}
