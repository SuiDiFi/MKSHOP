<?php
// +----------------------------------------------------------------------
// | MKSHOP 移动端路由（/uni/*）— M5 逐步填充
// +----------------------------------------------------------------------
use think\facade\Route;

Route::group('uni', function () {
    Route::get('ping', function () {
        return json_ok(['app' => 'h5', 'time' => time()], 'pong');
    })->middleware([\mks\middleware\Cors::class]);
})->middleware([\mks\middleware\Cors::class]);
