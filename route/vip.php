<?php
// +----------------------------------------------------------------------
// | MKSHOP 分站主控制台路由（/vip/*）
// | 鉴权：Cors → MemberToken（会员登录态）→ VipOwnerAuth（分站主绑定校验）
// +----------------------------------------------------------------------
use think\facade\Route;

Route::group('vip', function () {

    Route::get('overview', 'vip.ConsoleController/overview');
    Route::get('goods_index', 'vip.ConsoleController/goods_index');
    Route::post('goods_price_save', 'vip.ConsoleController/goods_price_save');
    Route::post('goods_price_clear', 'vip.ConsoleController/goods_price_clear');
    Route::get('order_index', 'vip.ConsoleController/order_index');
    Route::get('order_read/:id', 'vip.ConsoleController/order_read');
    Route::get('settlement_index', 'vip.ConsoleController/settlement_index');
    Route::get('commission_logs', 'vip.ConsoleController/commission_logs');
    Route::get('config_read', 'vip.ConsoleController/config_read');
    Route::post('config_save', 'vip.ConsoleController/config_save');
    Route::get('domain_index', 'vip.ConsoleController/domain_index');
})->middleware([
    \mks\middleware\Cors::class,
    \mks\middleware\MemberToken::class,
    \mks\middleware\VipOwnerAuth::class,
]);
