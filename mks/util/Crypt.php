<?php
// +----------------------------------------------------------------------
// | MKSHOP 敏感字段加密（AES-256-GCM；替代原系统 think_encrypt 流密码）
// +----------------------------------------------------------------------
declare (strict_types = 1);

namespace mks\util;

use think\facade\Config;

class Crypt
{
    private const PREFIX = 'mksv1:';

    /**
     * 加密（卡密 / 支付密钥 / 上游账号 / 实名信息等）
     */
    public static function encrypt(string $plain): string
    {
        if ($plain === '') {
            return '';
        }
        if (str_starts_with($plain, self::PREFIX)) {
            return $plain; // 已加密，防二次加密
        }
        $key   = self::key();
        $iv    = random_bytes(12);
        $tag   = '';
        $cipher = openssl_encrypt($plain, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag, '', 16);
        if ($cipher === false) {
            throw_fail('加密失败', 500);
        }
        return self::PREFIX . base64_encode($iv . $tag . $cipher);
    }

    /**
     * 解密（兼容明文历史数据：无前缀原样返回）
     */
    public static function decrypt(string $data): string
    {
        if ($data === '') {
            return '';
        }
        if (!str_starts_with($data, self::PREFIX)) {
            return $data; // 明文（历史数据 / 空值）
        }
        $raw    = base64_decode(substr($data, strlen(self::PREFIX)), true);
        if ($raw === false || strlen($raw) < 29) {
            throw_fail('密文格式错误', 500);
        }
        $iv     = substr($raw, 0, 12);
        $tag    = substr($raw, 12, 16);
        $cipher = substr($raw, 28);
        $plain  = openssl_decrypt($cipher, 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, $iv, $tag);
        if ($plain === false) {
            throw_fail('解密失败（密钥不匹配或数据损坏）', 500);
        }
        return $plain;
    }

    protected static function key(): string
    {
        $appKey = (string) Config::get('mks.app_key', '');
        if ($appKey === '') {
            throw_fail('未配置站点密钥（MKS.APP_KEY），请完成安装', 500);
        }
        return hash('sha256', 'crypt:' . $appKey, true);
    }
}
