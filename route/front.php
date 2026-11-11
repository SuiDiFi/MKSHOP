<?php
// +----------------------------------------------------------------------
// | MKSHOP PC 前台路由（/front/*）— M1/M5 逐步填充
// +----------------------------------------------------------------------
use think\facade\Route;

Route::group('front', function () {
    Route::get('ping', function () {
        return json_ok(['app' => 'front', 'time' => time()], 'pong');
    })->middleware([\mks\middleware\Cors::class]);
})->middleware([\mks\middleware\Cors::class]);
