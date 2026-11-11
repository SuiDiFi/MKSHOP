<?php
// +----------------------------------------------------------------------
// | MKSHOP JWT 工具（firebase/php-jwt；标准时钟容差；Redis 会话托管支持登出/踢下线）
// +----------------------------------------------------------------------
declare (strict_types = 1);

namespace mks\util;

use Firebase\JWT\ExpiredException;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use think\facade\Cache;
use think\facade\Config;

class Jwt
{
    /**
     * 签发令牌
     * @return array{token: string, jti: string, expires_at: int}
     */
    public static function issue(string $scene, int $uid, int $ttl = 0): array
    {
        $now    = time();
        $ttl    = $ttl > 0 ? $ttl : (int) Config::get('mks.auth.admin_ttl', 604800);
        $jti    = bin2hex(random_bytes(16));
        $claims = [
            'iss'  => Config::get('mks.auth.issuer', 'mkshop'),
            'sub'  => (string) $uid,
            'jti'  => $jti,
            'scene'=> $scene,
            'iat'  => $now,
            'nbf'  => $now,
            'exp'  => $now + $ttl,
        ];
        $token = JWT::encode($claims, self::secret(), 'HS256');

        // Redis 会话登记（登出/踢线 = 删除该键）
        Cache::store('redis')->set(self::cacheKey($scene, $jti), $uid, $ttl);

        return ['token' => $token, 'jti' => $jti, 'expires_at' => $now + $ttl];
    }

    /**
     * 校验令牌（签名 + 有效期 + Redis 会话存在性）
     * @return array claims
     */
    public static function verify(string $token, string $scene): array
    {
        $leeway = (int) Config::get('mks.auth.sign_leeway', 300);
        JWT::$leeway = $leeway;
        $claims = (array) JWT::decode($token, new Key(self::secret(), 'HS256'));

        if (($claims['scene'] ?? '') !== $scene) {
            throw_fail('令牌场景不匹配', 401);
        }
        $jti = (string) ($claims['jti'] ?? '');
        if ($jti === '' || !Cache::store('redis')->has(self::cacheKey($scene, $jti))) {
            throw_fail('登录已失效，请重新登录', 401);
        }
        return $claims;
    }

    /**
     * 注销
     */
    public static function revoke(string $scene, string $jti): void
    {
        Cache::store('redis')->delete(self::cacheKey($scene, $jti));
    }

    protected static function secret(): string
    {
        $key = (string) Config::get('mks.app_key', '');
        if ($key === '') {
            throw_fail('未配置站点密钥（MKS.APP_KEY），请完成安装', 500);
        }
        return hash('sha256', 'jwt:' . $key, true);
    }

    protected static function cacheKey(string $scene, string $jti): string
    {
        return 'token:' . $scene . ':' . $jti;
    }
}
