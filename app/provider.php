<?php
// +----------------------------------------------------------------------
// | MKSHOP 容器服务提供者
// +----------------------------------------------------------------------
use app\ExceptionHandle;
use app\Request;

// 容器 Provider 定义文件
return [
    'think\Request'          => Request::class,
    'think\exception\Handle' => ExceptionHandle::class,
];
