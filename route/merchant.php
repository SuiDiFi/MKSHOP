<?php
// +----------------------------------------------------------------------
// | MKSHOP 商户端路由（/merchant/*）
// | 入驻申请：Cors → MemberToken（未开通商户也可提交）
// | 商户控制台：Cors → MemberToken → MerchantAuth（has_merchant + merchant.status）
// +----------------------------------------------------------------------
use think\facade\Route;

Route::group('merchant', function () {
    Route::get('apply_info', 'merchant.ConsoleController/apply_info');
    Route::post('apply_submit', 'merchant.ConsoleController/apply_submit');
})->middleware([
    \mks\middleware\Cors::class,
    \mks\middleware\MemberToken::class,
]);

Route::group('merchant', function () {
    Route::get('overview', 'merchant.ConsoleController/overview');
    Route::get('order_index', 'merchant.ConsoleController/order_index');
    Route::get('order_read/:id', 'merchant.ConsoleController/order_read');
    Route::get('goods_index', 'merchant.ConsoleController/goods_index');
    Route::get('wallet', 'merchant.ConsoleController/wallet');
    Route::get('supply_flow', 'merchant.ConsoleController/supply_flow');
    Route::get('withdraw_config', 'merchant.ConsoleController/withdraw_config');
    Route::post('withdraw_apply', 'merchant.ConsoleController/withdraw_apply');
    Route::get('withdraw_index', 'merchant.ConsoleController/withdraw_index');
})->middleware([
    \mks\middleware\Cors::class,
    \mks\middleware\MemberToken::class,
    \mks\middleware\MerchantAuth::class,
]);
