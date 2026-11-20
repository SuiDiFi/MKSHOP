<?php
// +----------------------------------------------------------------------
// | 登录暴力破解防护（§5.6）：Redis 失败计数 + 连败锁定，账号与 IP 双维度
// | 语义：check() 在密码验证前调用（超限抛错）；fail() 在密码错误时累计；
// |       clear() 在登录成功时清零。默认连续 5 次失败锁定 10 分钟。
// | 键：login_fail:{type}:{md5(key)}；type ∈ member_u/member_ip/admin_u/admin_ip
// +----------------------------------------------------------------------
declare (strict_types = 1);

namespace mks\util;

use think\facade\Cache;
use think\facade\Request;

class LoginThrottle
{
    /** 锁定前允许的最大连续失败次数 */
    public const MAX_FAILS = 5;
    /** 锁定时长（秒） */
    public const LOCK_TTL = 600;

    /** 登录前校验：账号维度 + IP 维度任一超限即拒绝（读改写必须同键：统一走 handler 裸键，不能用 Cache::get 的前缀键） */
    public static function check(string $type, string $username): void
    {
        $ip = (string) Request::ip();
        $handler = Cache::store('redis')->handler();
        if ((int) $handler->get(self::key($type . '_u', $username)) >= self::MAX_FAILS) {
            throw_fail('失败次数过多，账号已临时锁定，请 10 分钟后再试', 1);
        }
        if ((int) $handler->get(self::key($type . '_ip', $ip)) >= self::MAX_FAILS * 4) {
            throw_fail('当前来源失败次数过多，请 10 分钟后再试', 1);
        }
    }

    /** 密码错误时累计（账号 + IP 双键，首键设置 TTL） */
    public static function fail(string $type, string $username): void
    {
        $ip = (string) Request::ip();
        $handler = Cache::store('redis')->handler();
        foreach ([self::key($type . '_u', $username), self::key($type . '_ip', $ip)] as $i => $k) {
            $n = (int) $handler->incr($k);
            if ($n === 1) {
                $handler->expire($k, self::LOCK_TTL);
            }
            unset($i);
        }
    }

    /** 登录成功清零（账号 + IP） */
    public static function clear(string $type, string $username): void
    {
        $ip = (string) Request::ip();
        $handler = Cache::store('redis')->handler();
        $handler->del(self::key($type . '_u', $username), self::key($type . '_ip', $ip));
    }

    private static function key(string $scope, string $key): string
    {
        return 'login_fail:' . $scope . ':' . md5($key);
    }
}
