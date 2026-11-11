<?php
// +----------------------------------------------------------------------
// | MKSHOP 上游对接路由（/dockapi/*，供货商对接；v2api 协议兼容）— M3 实现
// +----------------------------------------------------------------------
use think\facade\Route;

Route::group('dockapi', function () {
    Route::get('ping', function () {
        return json_ok(['app' => 'dockapi', 'time' => time()], 'pong');
    });
})->middleware([\mks\middleware\Cors::class]);
