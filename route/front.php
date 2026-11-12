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
        Route::get('site/info', 'front.SiteController/info');            // 分站/主站信息与主题（M6）
        Route::post('member/login', 'front.MemberController/login');     // 会员登录
        Route::post('member/register', 'front.MemberController/register'); // 注册（M4：支持邀请码）
        Route::get('goods/index', 'front.GoodsController/index');        // 在售商品列表
        Route::get('goods/read/:id', 'front.GoodsController/read');      // 商品详情
        Route::get('goods/cates', 'front.GoodsController/cates');        // 分类导航
        Route::get('diy/pc_home', 'front.DiyController/pcHome');         // DIY 首页配置
        // M5：首页聚合 / 分类树 / 公告 / 帮助中心
        Route::get('index/index', 'front.IndexController/index');        // 首页聚合数据
        Route::get('category/index', 'front.CategoryController/index');  // 分类树
        Route::get('notice/index', 'front.ArticleController/noticeIndex'); // 公告列表
        Route::get('notice/info/:id', 'front.ArticleController/info');   // 公告详情
        Route::get('support/list', 'front.ArticleController/helpList');  // 帮助中心列表
        Route::get('support/detail/:id', 'front.ArticleController/info'); // 帮助详情
    });

    // ── 登录组 ──
    Route::group(function () {
        Route::get('member/info', 'front.MemberController/info');        // 我的信息
        Route::get('member/money', 'front.MemberController/money');      // 余额明细（M5）
        Route::post('member/logout', 'front.MemberController/logout');   // 退出
        // 在线客服（M5）
        Route::post('kefu/start', 'front.KefuController/start');          // 发起/续接会话
        Route::get('kefu/messages', 'front.KefuController/messages');     // 拉取消息
        Route::post('kefu/send', 'front.KefuController/send');            // 发送消息
        Route::post('member/bind_parent', 'front.MemberController/bind_parent'); // 绑定上级（M4）
        Route::post('order/buy', 'front.OrderController/buy');           // 下单（余额支付）
        Route::post('order/pay', 'front.OrderController/pay');           // 待支付单继续支付
        Route::get('order/index', 'front.OrderController/index');        // 我的订单
        Route::get('order/query', 'front.OrderController/query');        // 查单/提卡
        // 分销中心（M4）
        Route::get('commission/index', 'front.CommissionController/index'); // 概览
        Route::get('commission/teams', 'front.CommissionController/teams'); // 我的团队
        Route::get('commission/logs', 'front.CommissionController/logs');   // 佣金明细
        // 提现（M4）
        Route::get('member_collect/index', 'front.MemberCollectController/index');   // 我的提现记录
        Route::get('member_collect/config', 'front.MemberCollectController/config');  // 提现配置
        Route::post('member_collect/config', 'front.MemberCollectController/config'); // 提现配置（原协议 POST）
        Route::post('member_collect/save', 'front.MemberCollectController/save');     // 申请提现
        Route::get('member_collect_way/index', 'front.MemberCollectController/ways'); // 我的提现方式
        Route::post('member_collect_way/list', 'front.MemberCollectController/ways'); // 提现方式选择
        Route::post('member_collect_way/save', 'front.MemberCollectController/way_save');
        Route::post('member_collect_way/delete', 'front.MemberCollectController/way_delete');
    })->middleware([
        \mks\middleware\MemberToken::class,
        \mks\middleware\VipSiteToken::class, // 分站上下文（M6：Host → vipSite）
    ]);
})->middleware([
    \mks\middleware\Cors::class,
    \mks\middleware\VipSiteToken::class, // 公开组同样注入分站上下文（商品列表/详情调价）
]);
