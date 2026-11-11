<?php
// +----------------------------------------------------------------------
// | MKSHOP 对接签名（兼容 KASUSHOU 2.0 官方协议：sha1(timestamp + body + key)）
// +----------------------------------------------------------------------
declare (strict_types = 1);

namespace mks\util;

use think\facade\Config;

class DockSign
{
    /**
     * 生成请求头（我们调用上游 / 对下游开放时的响应侧参考实现）
     * @param array $body body 参数（已按参数名字典升序处理由内部完成）
     * @return array{json: string, headers: array<string,string>}
     */
    public static function makeHeaders(array $body, string $apikey, string $userid): array
    {
        $json = $body ? self::canonicalJson($body) : '{}';
        $time = (string) (intdiv((int) (microtime(true) * 1000), 1)); // 13 位毫秒
        return [
            'json'       => $json,
            'headers'    => [
                'Content-Type' => 'application/json; charset=utf-8',
                'Sign'         => sha1($time . $json . $apikey),
                'Timestamp'    => $time,
                'UserId'       => $userid,
            ],
        ];
    }

    /**
     * 验签（上游/下游请求进入我们时）
     * @param string $rawBody 原始请求体（必须原样参与验签）
     */
    public static function verify(string $rawBody, string $sign, string $timestamp, string $apikey, int $leeway = 300): bool
    {
        if ($rawBody === '') {
            $rawBody = '{}';
        }
        $ts = (int) $timestamp;
        if ($ts <= 0 || abs(time() * 1000 - $ts) > $leeway * 1000) {
            return false; // 时间容差（红线：修正原系统“未来时间戳”设计）
        }
        return hash_equals(sha1($timestamp . $rawBody . $apikey), (string) $sign);
    }

    /**
     * 参数名字典升序 + JSON_UNESCAPED_SLASHES|UNESCAPED_UNICODE（协议要求）
     */
    public static function canonicalJson(array $body): string
    {
        ksort($body);
        return json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * 取本站对接密钥（/dockapi 下游验证用）
     */
    public static function siteKey(): string
    {
        return (string) Config::get('mks.sign_key', '');
    }
}
