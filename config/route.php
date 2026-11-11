<?php
// +----------------------------------------------------------------------
// | MKSHOP 路由配置
// +----------------------------------------------------------------------

return [
    // pathinfo分隔符
    'pathinfo_depr'         => '/',
    // URL伪静态后缀
    'url_html_suffix'       => 'html',
    // URL普通方式参数
    'url_common_param'      => true,
    // URL解析方式（0=强制小写,1=自动识别）
    'url_denied_string'     => '',
    // 是否开启路由缓存
    'route_check_cache'     => false,
    // 是否强制使用路由（红线：全站路由必须显式定义，不允许直接 Controller 映射）
    'url_route_must'        => true,
    // 合并路由规则（关闭：与同前缀规则存在匹配缺陷，稳定性优先）
    'route_rule_merge'      => false,
    // 路由完全匹配
    'route_complete_match'  => true,
    // 请求缓存
    'request_cache'         => false,
    // 请求缓存有效期
    'request_cache_expire'  => null,
    // 全局排除请求缓存规则
    'request_cache_except'  => [],
    // 路由缓存文件
    'route_cache_file'      => '',
];
