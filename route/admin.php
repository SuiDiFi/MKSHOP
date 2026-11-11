<?php
// +----------------------------------------------------------------------
// | MKSHOP 后台路由（/admin/*）
// 鉴权链：Cors → [公开] / [AdminToken → AdminAuth → AdminLog]
// 红线：接口必须同时在 mks_admin_router 登记（auth_type=2）或加入 mks_api_permission 白名单，否则默认拒绝
// +----------------------------------------------------------------------
use think\facade\Route;

Route::group('admin', function () {

    // ── 公开组（无需登录态）─────────────────────────────
    Route::group(function () {
        Route::post('login/login', 'admin/Login/login');       // 登录
    });

    // ── 鉴权组（需登录 + RBAC + 操作日志）─────────────────
    Route::group(function () {
        Route::post('login/logout', 'admin/Login/logout');     // 登出（白名单：会话管理）
        Route::get('login/getInfo', 'admin/Login/getInfo');    // 我的信息（白名单）
        Route::get('login/getMenus', 'admin/Login/getMenus');  // 菜单树（白名单）
    })->middleware([
        \mks\middleware\AdminToken::class,
        \mks\middleware\AdminAuth::class,
        \mks\middleware\AdminLog::class,
    ]);
})->middleware([\mks\middleware\Cors::class]);
