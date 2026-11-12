<?php
// +----------------------------------------------------------------------
// | MKSHOP 控制台配置
// +----------------------------------------------------------------------

return [
    // 指令定义
    'commands' => [
        'mks:install'    => \app\common\commands\Install::class,
        'mks:upgrade'    => \app\common\commands\Upgrade::class,
        'mks:dockbu'     => \app\common\commands\DockBu::class,
        'mks:docksync'   => \app\common\commands\DockSync::class,
        'mks:timer'      => \app\common\commands\Timer::class,
        'mks:commission' => \app\common\commands\Commission::class,
    ],
];
