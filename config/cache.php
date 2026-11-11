<?php
// +----------------------------------------------------------------------
// | MKSHOP 缓存配置（默认 Redis；缓存/会话/队列/装修统一走 Redis）
// +----------------------------------------------------------------------
use think\facade\Env;

return [
    // 默认缓存驱动
    'default' => Env::get('cache.driver', 'redis'),

    // 缓存连接方式配置
    'stores'  => [
        'file' => [
            'type'       => 'File',
            'path'       => '',
            'prefix'     => '',
            'expire'     => 0,
            'tag_prefix' => 'tag:',
            'serialize'  => [],
        ],
        'redis' => [
            'type'       => 'redis',
            'host'       => Env::get('redis.hostname', '127.0.0.1'),
            'port'       => Env::get('redis.port', 6379),
            'password'   => Env::get('redis.password', ''),
            'select'     => (int) Env::get('redis.select', 0),
            'timeout'    => 0,
            'expire'     => 0,
            'persistent' => false,
            'prefix'     => 'mks:',
            'tag_prefix' => 'mks:tag:',
            'serialize'  => [],
        ],
    ],
];
