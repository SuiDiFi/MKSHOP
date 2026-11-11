<?php
// +----------------------------------------------------------------------
// | MKSHOP 开放 API 路由（/api/*，下游商家/机器人）— M2/M3 逐步填充
// +----------------------------------------------------------------------
use think\facade\Route;

Route::group('api', function () {
    Route::get('ping', function () {
        return json_ok(['app' => 'api', 'time' => time()], 'pong');
    });
})->middleware([\mks\middleware\Cors::class]);
