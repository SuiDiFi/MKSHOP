<?php
// +----------------------------------------------------------------------
// | MKSHOP 控制台配置
// +----------------------------------------------------------------------

return [
    // 指令定义
    'commands' => [
        'mks:install' => \app\common\commands\Install::class,
        'mks:dockbu'  => \app\common\commands\DockBu::class,
    ],
];
