<?php
// +----------------------------------------------------------------------
// | MKSHOP 应用配置（ThinkPHP 6 多应用模式）
// +----------------------------------------------------------------------
use think\facade\Env;

return [
    // 应用地址
    'app_host'         => Env::get('app.host', ''),
    // 应用的命名空间
    'app_namespace'    => 'app',
    // 是否启用路由
    'with_route'       => true,
    // 默认应用（单应用模式：所有端通过路由分组区分，控制器在 app/controller/{端}/ 下）
    'default_app'      => 'front',
    // 默认时区
    'default_timezone' => 'Asia/Shanghai',

    // 应用映射（单应用模式，不启用自动多应用）
    'auto_multi_app'   => false,
    // 域名绑定
    'domain_bind'      => [],
    // 禁止URL访问的应用列表（保护目录型应用）
    'deny_app_list'    => ['common', 'mks'],

    // 异常页面的模板文件
    'exception_tmpl'   => app()->getThinkPath() . 'tpl' . DIRECTORY_SEPARATOR . 'think_exception.tpl',

    // 错误显示信息（非调试模式）
    'error_message'    => '页面错误！请稍后再试～',
    // 显示错误信息
    'show_error_msg'   => false,
];
