<?php
// +----------------------------------------------------------------------
// | MKSHOP 日志配置（默认 runtime 日志；JSON 通道供采集）
// +----------------------------------------------------------------------
use think\facade\Env;

return [
    // 默认日志通道
    'default'      => Env::get('log.channel', 'runtime'),

    // 日志通道列表
    'channels'     => [
        'runtime' => [
            'type'           => 'File',
            'path'           => app()->getRuntimePath() . 'log',
            'single'         => false,
            'file_size'      => 92 * 1024 * 1024,
            'time_format'    => 'Y-m-d H:i:s',
            'format'         => '[%s][%s] %s',
            'use_lock'       => false,
            'close'          => false,
            'json'           => false,
            'processor'      => null,
            'close_in_day'   => 0,
        ],
        'json' => [
            'type'        => 'File',
            'path'        => app()->getRuntimePath() . 'log' . DIRECTORY_SEPARATOR . 'json',
            'single'      => true,
            'file_name'   => 'mks-' . date('Ymd') . '.json',
            'json'        => true,
            'close'       => false,
        ],
    ],

    // 关闭全局日志写入（按需开启）
    'close'        => false,
    // 是否记录 trace
    'json_trace'   => false,
];
