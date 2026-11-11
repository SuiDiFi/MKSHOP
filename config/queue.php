<?php
// +----------------------------------------------------------------------
// | MKSHOP 队列配置（Redis 队列；红线：失败任务必须落库可重放）
// +----------------------------------------------------------------------
use think\facade\Env;

return [
    // 默认队列通道
    'default'     => 'redis',

    // 通道别名
    'connections' => [
        'sync'     => [
            'type' => 'sync',
        ],
        'database' => [
            'type'       => 'database',
            'queue'      => 'default',
            'table'      => 'jobs',
            'connection' => 'mysql',
        ],
        'redis'    => [
            'type'       => 'redis',
            'queue'      => Env::get('mks.queue_prefix', 'mks_queue') . ':default',
            'host'       => Env::get('redis.hostname', '127.0.0.1'),
            'port'       => Env::get('redis.port', 6379),
            'password'   => Env::get('redis.password', ''),
            'select'     => (int) Env::get('redis.select', 0),
            'timeout'    => 2,
            'persistent' => false,
            // 任务重试窗口（秒）
            'retry_after' => 300,
            // 前缀
            'prefix'     => Env::get('mks.queue_prefix', 'mks_queue') . ':',
            // 红线修正：失败任务写入 failed 表（原系统 none，异常任务直接丢失）
            'failed'     => [
                'type'  => 'mysql',
                'table' => 'failed_jobs',
                'connection' => 'mysql',
            ],
        ],
    ],

    // 失败任务重试次数提示（进程层由 supervisor/queue:work --tries 控制）
    'failed'      => [
        'type'  => 'mysql',
        'table' => 'failed_jobs',
        'connection' => 'mysql',
    ],
];
