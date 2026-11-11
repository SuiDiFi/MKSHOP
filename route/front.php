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
        Route::post('member/login', 'front.MemberController/login');   // 会员登录
        Route::get('goods/index', 'front.GoodsController/index');      // 在售商品列表
        Route::get('goods/read/:id', 'front.GoodsController/read');    // 商品详情
    });

    // ── 登录组 ──
    Route::group(function () {
        Route::get('member/info', 'front.MemberController/info');      // 我的信息
        Route::post('member/logout', 'front.MemberController/logout'); // 退出
        Route::post('order/buy', 'front.OrderController/buy');         // 下单（余额支付）
        Route::post('order/pay', 'front.OrderController/pay');         // 待支付单继续支付
        Route::get('order/index', 'front.OrderController/index');      // 我的订单
        Route::get('order/query', 'front.OrderController/query');      // 查单/提卡
    })->middleware([\mks\middleware\MemberToken::class]);
})->middleware([\mks\middleware\Cors::class]);
