<?php
// +----------------------------------------------------------------------
// | MKSHOP 开放 API 路由（/api/*，下游商家/机器人）— M2/M3 逐步填充
// +----------------------------------------------------------------------
use think\facade\Route;

Route::group('api', function () {
    Route::get('ping', function () {
        return json_ok(['app' => 'api', 'time' => time()], 'pong');
    });

    // 渠道订单异步回调（上游 → 我方；自带验签，无鉴权中间件）
    Route::post('v2/order/notify', 'api.Notify/order');
})->middleware([\mks\middleware\Cors::class]);
