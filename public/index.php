<?php
// +----------------------------------------------------------------------
// | MKSHOP 应用入口 [ front / h5 / admin / api / dockapi 多应用 ]
// +----------------------------------------------------------------------
declare (strict_types = 1);

// 检测 PHP 版本（与原系统一致，低于 8.0 直接终止）
if (version_compare(PHP_VERSION, '8.0.0', '<')) {
    die('MKSHOP requires PHP >= 8.0.0');
}

// 未安装时引导安装向导
if (!is_file(__DIR__ . '/install.lock') && !strpos($_SERVER['SCRIPT_NAME'] ?? '', 'install')) {
    header('Location: /index.php?s=/install/');
    exit;
}

require __DIR__ . '/../vendor/autoload.php';

// 执行 HTTP 应用并响应
$http     = (new think\App())->http;
$response = $http->run();

$response->send();
$http->end($response);
