<?php
// +----------------------------------------------------------------------
// | MKSHOP 插件路由（/addons/:addon/:controller/:action）
// | 仅分发到已安装且启用的插件；控制器位于 addons/<name>/controller/
// +----------------------------------------------------------------------
use think\facade\Route;

Route::group('addons', function () {
    Route::any(':addon', 'addon.DispatchController/index');
    Route::any(':addon/:controller', 'addon.DispatchController/index');
    Route::any(':addon/:controller/:action', 'addon.DispatchController/index');
})->middleware([\mks\middleware\Cors::class]);
