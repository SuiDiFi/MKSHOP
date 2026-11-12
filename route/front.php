<?php
// +----------------------------------------------------------------------
// | MKSHOP 前台路由（/front/*）— M2 交易闭环
// 鉴权链：公开（登录/商品浏览） + [MemberToken]（我的订单/支付/提卡）
// 注意：前台不做 RBAC 白名单表（front API 无权限点体系），鉴权仅登录态校验
// +----------------------------------------------------------------------
use think\facade\Route;

Route::group('front', function () {

    // ── 公开组 ──
    Route::group(function () {
        Route::post('member/login', 'front.MemberController/login');     // 会员登录
        Route::post('member/register', 'front.MemberController/register'); // 注册（M4：支持邀请码）
        Route::get('goods/index', 'front.GoodsController/index');        // 在售商品列表
        Route::get('goods/read/:id', 'front.GoodsController/read');      // 商品详情
        Route::get('goods/cates', 'front.GoodsController/cates');        // 分类导航
        Route::get('diy/pc_home', 'front.DiyController/pcHome');         // DIY 首页配置
    });

    // ── 登录组 ──
    Route::group(function () {
        Route::get('member/info', 'front.MemberController/info');        // 我的信息
        Route::post('member/logout', 'front.MemberController/logout');   // 退出
        Route::post('member/bind_parent', 'front.MemberController/bind_parent'); // 绑定上级（M4）
        Route::post('order/buy', 'front.OrderController/buy');           // 下单（余额支付）
        Route::post('order/pay', 'front.OrderController/pay');           // 待支付单继续支付
        Route::get('order/index', 'front.OrderController/index');        // 我的订单
        Route::get('order/query', 'front.OrderController/query');        // 查单/提卡
        // 分销中心（M4）
        Route::get('commission/index', 'front.CommissionController/index'); // 概览
        Route::get('commission/teams', 'front.CommissionController/teams'); // 我的团队
        Route::get('commission/logs', 'front.CommissionController/logs');   // 佣金明细
    })->middleware([
        \mks\middleware\MemberToken::class,
        \mks\middleware\VipSiteToken::class, // 分站上下文（M6：Host → vipSite）
    ]);
})->middleware([
    \mks\middleware\Cors::class,
    \mks\middleware\VipSiteToken::class, // 公开组同样注入分站上下文（商品列表/详情调价）
]);
