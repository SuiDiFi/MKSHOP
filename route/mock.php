<?php
// +----------------------------------------------------------------------
// | MKSHOP Mock 上游路由（E2E 用，公开无鉴权）
// s=/mockv2/api/v1/order/buy 等；DockingV2Service base 指向 http://nginx/index.php?s=/mockv2
// +----------------------------------------------------------------------
use think\facade\Route;

Route::group('mockv2', function () {
    Route::post('api/v1/order/buy', 'mock.MockV2Controller/buy');
    Route::post('api/v1/order/info', 'mock.MockV2Controller/info');
    Route::post('api/v1/goods/list', 'mock.MockV2Controller/goodsList');
    Route::post('api/v1/user/money', 'mock.MockV2Controller/money');
})->middleware([\mks\middleware\Cors::class]);
