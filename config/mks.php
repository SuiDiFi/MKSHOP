<?php
// +----------------------------------------------------------------------
// | MKSHOP 系统配置（品牌 / 版本 / 密钥 / 约束）
// +----------------------------------------------------------------------
use think\facade\Env;

return [
    // 版本
    'version'    => '0.1.0',
    // 品牌名
    'brand'      => 'MKSHOP',
    // 站点域名（环境变量优先）
    'site_url'   => Env::get('mks.site_url', 'https://mksup.top'),

    // 站点加密密钥（敏感字段 AES-256-GCM，安装向导生成）
    'app_key'    => Env::get('mks.app_key', ''),
    // 开放 API 鉴权密钥（/api 对下游）
    'auth_key'   => Env::get('mks.auth_key', ''),
    // 上游对接签名密钥（/dockapi，兼容 v2api sha1 协议）
    'sign_key'   => Env::get('mks.sign_key', ''),
    // 队列前缀
    'queue'      => [
        'prefix' => Env::get('mks.queue_prefix', 'mks_queue'),
    ],

    // 认证
    'auth'       => [
        // 后台 JWT 有效期（秒）
        'admin_ttl'   => 7 * 86400,
        // 会员 Token 有效期（秒）
        'user_ttl'    => 30 * 86400,
        // 签名时间容差（秒；修正原系统“未来时间戳”设计）
        'sign_leeway' => 300,
        // JWT 签发者
        'issuer'      => 'mkshop',
    ],

    // 上传约束（红线：白名单收紧，禁 svg/pem/key/zip/kss 等）
    'upload'     => [
        'max_size'   => 50 * 1024 * 1024,
        'image_ext'  => 'jpg,jpeg,png,gif,webp,bmp',
        'file_ext'   => 'txt,xls,xlsx,csv,doc,docx,pdf,mp3,mp4',
    ],

    // 金额约束（红线：decimal(16,4)，bcmath 舍入 HALF_UP，展示 2 位）
    'money'      => [
        'scale'      => 4,
        'cost_scale' => 5,
        'show_scale' => 2,
    ],

    // 分销佣金（M4：两级比例模式，比例取上级等级 member_level.one_rate/two_rate）
    'commission' => [
        // 分销总开关（goods.is_rebate 仍需逐商品开启）
        'enabled'   => true,
        // 佣金冻结期（分钟）：0=订单成功即时入账；>0 写入 order_commission 待 mks:commission 到期结算
        'sure_time' => 0,
    ],
];
