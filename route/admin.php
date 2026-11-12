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

        // ── M1 商品模块 ──────────────────────────────────────
        // 商品（权限点 goods/*）
        Route::get('goods/index', 'admin.GoodsController/index');
        Route::get('goods/read/:id', 'admin.GoodsController/read');
        Route::post('goods/list', 'admin.GoodsController/list');
        Route::post('goods/create', 'admin.GoodsController/create');
        Route::post('goods/save/:id', 'admin.GoodsController/save');
        Route::post('goods/delete/:id', 'admin.GoodsController/delete');
        Route::post('goods/destroy/:id', 'admin.GoodsController/destroy');
        Route::post('goods/restore/:id', 'admin.GoodsController/restore');
        Route::post('goods/modify/:id', 'admin.GoodsController/modify');
        Route::post('goods/sort', 'admin.GoodsController/sort');
        Route::post('goods/save_goods_stock', 'admin.GoodsController/save_goods_stock');
        Route::post('goods/status_type', 'admin.GoodsController/status_type');
        Route::post('goods/goods_type', 'admin.GoodsController/goods_type');
        Route::post('goods/provide_type', 'admin.GoodsController/provide_type');
        Route::post('goods/default_tags_list', 'admin.GoodsController/default_tags_list');

        // 商品分类（权限点 category/*）
        Route::get('category/index', 'admin.GoodsCategoryController/index');
        Route::post('category/list', 'admin.GoodsCategoryController/list');
        Route::post('category/create', 'admin.GoodsCategoryController/create');
        Route::post('category/save/:id', 'admin.GoodsCategoryController/save');
        Route::post('category/delete/:id', 'admin.GoodsCategoryController/delete');
        Route::post('category/modify/:id', 'admin.GoodsCategoryController/modify');

        // 卡密库存（权限点 card/* card_cate/* card_history/*）
        Route::get('card/index', 'admin.CardController/index');
        Route::post('card/create', 'admin.CardController/create');
        Route::post('card/save', 'admin.CardController/save');
        Route::post('card/delete/:id', 'admin.CardController/delete');
        Route::post('card/delete_all', 'admin.CardController/delete_all');
        Route::get('card_cate/index', 'admin.CardCateController/index');
        Route::post('card_cate/list', 'admin.CardCateController/list');
        Route::post('card_cate/create', 'admin.CardCateController/create');
        Route::post('card_cate/save/:id', 'admin.CardCateController/save');
        Route::post('card_cate/delete/:id', 'admin.CardCateController/delete');
        Route::get('card_history/index', 'admin.CardHistoryController/index');

        // 品牌标签（权限点 goods_tags/*）
        Route::get('goods_tags/index', 'admin.GoodsTagsController/index');
        Route::post('goods_tags/list', 'admin.GoodsTagsController/list');
        Route::post('goods_tags/create', 'admin.GoodsTagsController/create');
        Route::post('goods_tags/save/:id', 'admin.GoodsTagsController/save');
        Route::post('goods_tags/delete/:id', 'admin.GoodsTagsController/delete');
        Route::post('goods_tags/modify/:id', 'admin.GoodsTagsController/modify');

        // ── M2 交易模块 ──────────────────────────────────────
        // 订单管理（权限点 order/*）
        Route::get('order/index', 'admin.OrderController/index');
        Route::get('order/info/:id', 'admin.OrderController/info');
        Route::get('order/goods/:id', 'admin.OrderController/goods');
        Route::post('order/dual/:id', 'admin.OrderController/dual');
        Route::post('order/delete/:id', 'admin.OrderController/delete');
        Route::post('order/modify/:id', 'admin.OrderController/modify');
        Route::post('order/status_type', 'admin.OrderController/status_type');
        Route::post('order/pay_type', 'admin.OrderController/pay_type');
        Route::post('order/sync_order/:id', 'admin.OrderController/sync_order');
        Route::post('order/do_get_order', 'admin.OrderController/do_get_order'); // 后台补单
        Route::post('order/no_bu_order', 'admin.OrderController/no_bu_order');   // 停止补单

        // 对接渠道（权限点 docking/*；M3 上游 v2api）
        Route::get('docking/index', 'admin.DockingController/index');
        Route::post('docking/list', 'admin.DockingController/list');
        Route::post('docking/create', 'admin.DockingController/create');
        Route::post('docking/save/:id', 'admin.DockingController/save');
        Route::post('docking/delete/:id', 'admin.DockingController/delete');
        Route::post('docking/modify/:id', 'admin.DockingController/modify');
        Route::post('docking/user_money', 'admin.DockingController/user_money');
        Route::post('docking/goods_list', 'admin.DockingController/goods_list');
        Route::post('docking/goods_dual', 'admin.DockingController/goods_dual');
        Route::post('docking/sync_stock', 'admin.DockingController/sync_stock'); // 渠道库存/成本补货同步
        Route::get('docking/log_index', 'admin.DockingController/log_index');

        // ── M4 分销模块 ──────────────────────────────────────
        // 佣金记录（权限点 commission/*）
        Route::get('commission/index', 'admin.CommissionController/index');
        Route::post('commission/settle/:id', 'admin.CommissionController/settle');

        // ── M5 装修模块 ──────────────────────────────────────
        // DIY 页面（权限点 diy/*）
        Route::get('diy/index', 'admin.DiyController/index');
        Route::get('diy/read/:id', 'admin.DiyController/read');
        Route::post('diy/save/:id', 'admin.DiyController/save');
        Route::get('diy/components', 'admin.DiyController/components');

        // ── M6 分站模块 ──────────────────────────────────────
        // 分站管理（权限点 vip/*）
        Route::get('vip/index', 'admin.VipSiteController/index');
        Route::post('vip/create', 'admin.VipSiteController/create');
        Route::post('vip/save/:id', 'admin.VipSiteController/save');
        Route::post('vip/delete/:id', 'admin.VipSiteController/delete');
    })->middleware([
        \mks\middleware\AdminToken::class,
        \mks\middleware\AdminAuth::class,
        \mks\middleware\AdminLog::class,
    ]);
})->middleware([\mks\middleware\Cors::class]);
