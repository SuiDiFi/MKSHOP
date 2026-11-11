<?php
// +----------------------------------------------------------------------
// | MKSHOP 对外供货 API 路由（/dockapi/v1/*；供货商端，下游商户调用我们）
// | 协议兼容 KASUSHOU 2.0 v2api：Header Sign/Timestamp/UserId
// +----------------------------------------------------------------------
use think\facade\Route;

Route::group('dockapi', function () {
    Route::get('ping', function () {
        return json_ok(['app' => 'dockapi', 'time' => time()], 'pong');
    });

    Route::group('v1', function () {
        Route::post('user/money', 'dockapi.DockApiController/userMoney');
        Route::post('goods/list', 'dockapi.DockApiController/goodsList');
        Route::post('goods/detail', 'dockapi.DockApiController/goodsDetail');
        Route::post('order/buy', 'dockapi.DockApiController/buy');
        Route::post('order/info', 'dockapi.DockApiController/queryOrder');
    });
})->middleware([\mks\middleware\Cors::class]);
