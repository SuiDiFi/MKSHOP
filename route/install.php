<?php
// +----------------------------------------------------------------------
// | MKSHOP 安装向导路由（安装完成后由 InstallService 锁文件禁用）
// +----------------------------------------------------------------------
use think\facade\Route;

Route::get('install', 'install.IndexController/index');
Route::get('install/', 'install.IndexController/index');
Route::get('install/index', 'install.IndexController/index');
Route::post('install/check', 'install.IndexController/check');
Route::post('install/run', 'install.IndexController/run');
