<?php
// +----------------------------------------------------------------------
// | 小程序端公开配置与微信能力预留（M6-E）
// | 说明：只下发非敏感字段；AppSecret、APIv3 密钥、证书路径仅服务端读取。
// |       微信授权登录 / 微信支付默认关闭，接口先占位，开启后再接具体实现。
// +----------------------------------------------------------------------
declare (strict_types = 1);

namespace app\controller\mp;

use mks\BaseController;
use mks\util\SysConfig;

class ConfigController extends BaseController
{
    /**
     * 小程序启动配置（公开，不需要登录态）
     */
    public function index()
    {
        return $this->ok([
            'site_name'            => (string) SysConfig::get('site_name', 'MKSHOP 商城'),
            'appid'                => (string) SysConfig::get('mp_appid', ''),
            'login_mode'           => (string) SysConfig::get('mp_login_mode', 'account'),
            'wechat_login_enable'  => (bool) SysConfig::get('mp_wechat_login_enable', false),
            'wechat_pay_enable'    => (bool) SysConfig::get('mp_wechat_pay_enable', false),
            'subscribe_templates'  => $this->parseTemplates((string) SysConfig::get('mp_subscribe_templates', '')),
            'kefu_url'             => (string) SysConfig::get('mp_kefu_url', ''),
            'privacy_url'          => (string) SysConfig::get('mp_privacy_url', ''),
            'agreement_url'        => (string) SysConfig::get('mp_agreement_url', ''),
            'release_enable'       => (bool) SysConfig::get('mp_release_enable', false),
        ]);
    }

    /**
     * 微信授权登录（预留）：开关未开或登录方式不含微信时直接拒绝
     */
    public function wechatLogin()
    {
        if (!$this->wechatLoginEnabled()) {
            return $this->fail('微信授权登录未启用');
        }
        // 预留接口位：此处接入 jscode2session 换取 openid，并绑定/创建会员
        return $this->fail('微信授权登录接口待接入');
    }

    /**
     * 微信支付下单（预留）：开关未开时继续使用余额支付
     */
    public function wechatPay()
    {
        if (!(bool) SysConfig::get('mp_wechat_pay_enable', false)) {
            return $this->fail('微信支付未启用');
        }
        // 预留接口位：此处接入微信支付 v3 JSAPI 下单
        return $this->fail('微信支付接口待接入');
    }

    /**
     * 微信支付回调（预留）：默认关闭时不接受任何回调
     */
    public function wechatNotify()
    {
        if (!(bool) SysConfig::get('mp_wechat_pay_enable', false)) {
            return $this->fail('微信支付未启用');
        }
        // 预留接口位：此处校验回调签名并更新订单
        return $this->fail('微信支付回调待接入');
    }

    /**
     * 登录方式判断：开关开启且模式为 wechat/both 才算启用
     */
    private function wechatLoginEnabled(): bool
    {
        if (!(bool) SysConfig::get('mp_wechat_login_enable', false)) {
            return false;
        }
        $mode = (string) SysConfig::get('mp_login_mode', 'account');
        return in_array($mode, ['wechat', 'both'], true);
    }

    /**
     * 订阅消息模板解析：支持 JSON 对象、每行 场景=模板ID、逗号分隔
     */
    private function parseTemplates(string $raw): array
    {
        $raw = trim($raw);
        if ($raw === '') {
            return [];
        }
        $json = json_decode($raw, true);
        if (is_array($json)) {
            return $json;
        }
        $out = [];
        foreach (preg_split('/[\r\n,，]+/u', $raw) ?: [] as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            if (str_contains($line, '=')) {
                [$key, $value]        = explode('=', $line, 2);
                $out[trim($key)] = trim($value);
            } else {
                $out[] = $line;
            }
        }
        return $out;
    }
}
