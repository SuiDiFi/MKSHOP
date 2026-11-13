<?php
// +----------------------------------------------------------------------
// | MKSHOP 小程序端路由（/mp/*）— M6-E
// | 公开接口：启动配置；微信授权登录/支付/回调为预留位（默认关闭）
// | 小程序端不直接持有 AppSecret、支付密钥与证书路径
// +----------------------------------------------------------------------
use think\facade\Route;

Route::group('mp', function () {
    Route::get('config', 'mp.ConfigController/index');          // 启动配置（非敏感）
    Route::post('login/wechat', 'mp.ConfigController/wechatLogin'); // 微信授权登录（预留）
    Route::post('pay/wechat', 'mp.ConfigController/wechatPay');     // 微信支付下单（预留）
    Route::post('pay/notify', 'mp.ConfigController/wechatNotify');  // 微信支付回调（预留）
})->middleware([\mks\middleware\Cors::class]);
