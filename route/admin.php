<?php
// +----------------------------------------------------------------------
// | MKSHOP 后台路由（/admin/*）
// 鉴权链：Cors → [公开] / [AdminToken → AdminAuth → AdminLog]
// 红线：接口必须同时在 mks_admin_router 登记（auth_type=2）或加入 mks_api_permission 白名单，否则默认拒绝
// 注意：规则值的多级控制器用点号（admin.Login/login），斜杠会被解析为参数（与原系统 v1.index.Index 风格一致）；
//       控制器统一在 app/controller/admin/ 下，规则值首段固定为 admin
// +----------------------------------------------------------------------
use think\facade\Route;

Route::group('admin', function () {

    // ── 公开组（无需登录态）─────────────────────────────
    Route::group(function () {
        Route::post('login/login', 'admin.Login/login');       // 登录
    });

    // ── 鉴权组（需登录 + RBAC + 操作日志）─────────────────
    Route::group(function () {

        // 会话管理（白名单豁免 RBAC）
        Route::post('login/logout', 'admin.Login/logout');     // 登出
        Route::get('login/getInfo', 'admin.Login/getInfo');    // 我的信息
        Route::get('login/getMenus', 'admin.Login/getMenus');  // 菜单树

        // 管理员管理（权限点 admin/*）
        Route::get('admin/index', 'admin.Admin/index');
        Route::get('admin/read/:id', 'admin.Admin/read');
        Route::post('admin/create', 'admin.Admin/create');
        Route::post('admin/save/:id', 'admin.Admin/save');
        Route::post('admin/delete/:id', 'admin.Admin/delete');
        Route::post('admin/modify/:id', 'admin.Admin/modify');
        Route::get('admin/login_log', 'admin.Admin/login_log');
        Route::get('admin/do_log', 'admin.Admin/do_log');
        Route::post('admin/do_log_type', 'admin.Admin/do_log_type');
        Route::get('admin/login_status', 'admin.Admin/login_status');
        Route::post('admin/del_login_status', 'admin.Admin/del_login_status');

        // 角色管理（权限点 role/*）
        Route::get('role/index', 'admin.Role/index');
        Route::get('role/read/:id', 'admin.Role/read');
        Route::post('role/list', 'admin.Role/list');
        Route::post('role/save/:id', 'admin.Role/save');
        Route::post('role/create', 'admin.Role/create');
        Route::post('role/delete/:id', 'admin.Role/delete');
        Route::post('role/modify_role/:id', 'admin.Role/modify_role');

        // 菜单/接口权限管理（权限点 router/*）
        Route::get('router/index', 'admin.Router/index');
        Route::get('router/read/:id', 'admin.Router/read');
        Route::post('router/list', 'admin.Router/list');
        Route::post('router/create', 'admin.Router/create');
        Route::post('router/save/:id', 'admin.Router/save');
        Route::post('router/delete/:id', 'admin.Router/delete');

        // 系统配置中心 + 字典（权限点 sys/*；字典管理为 MKSHOP 新增权限点）
        Route::get('sys/config_group_form', 'admin.Sys/config_group_form');
        Route::post('sys/save_config_group', 'admin.Sys/save_config_group');
        Route::get('sys/config_group_list', 'admin.Sys/config_group_list');
        Route::get('sys/dict_options', 'admin.Sys/dict_options');
        Route::get('sys/dict_index', 'admin.Sys/dict_index');
        Route::post('sys/dict_save', 'admin.Sys/dict_save');
        Route::post('sys/dict_delete', 'admin.Sys/dict_delete');

        // 文件（权限点 file/*）
        Route::get('file/index', 'admin.FileController/index');
        Route::post('file/upload', 'admin.FileController/upload');
        Route::post('file/delete', 'admin.FileController/delete');

        // 定时任务（权限点 sys_timer/*）
        Route::get('sys_timer/index', 'admin.SysTimerController/index');
        Route::get('sys_timer/read/:id', 'admin.SysTimerController/read');
        Route::post('sys_timer/task_name', 'admin.SysTimerController/task_name');
        Route::post('sys_timer/save/:id', 'admin.SysTimerController/save');
        Route::post('sys_timer/create', 'admin.SysTimerController/create');
        Route::post('sys_timer/delete/:id', 'admin.SysTimerController/delete');
        Route::post('sys_timer/modify/:id', 'admin.SysTimerController/modify');
    })->middleware([
        \mks\middleware\AdminToken::class,
        \mks\middleware\AdminAuth::class,
        \mks\middleware\AdminLog::class,
    ]);
})->middleware([\mks\middleware\Cors::class]);
