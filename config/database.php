<?php
// +----------------------------------------------------------------------
// | MKSHOP 数据库配置（MySQL 8 · utf8mb4 · 前缀 mks_ · int 时间戳）
// +----------------------------------------------------------------------
use think\facade\Env;

return [
    // 默认使用的数据库连接
    'default'         => Env::get('database.driver', 'mysql'),

    // 自定义时间查询规则
    'time_query_rule' => [],

    // 自动写入时间戳字段（int 秒；与建库 DDL create_time/update_time int 一致）
    'auto_timestamp'  => true,
    'datetime_format' => 'Y-m-d H:i:s',

    // 数据库连接配置
    'connections'     => [
        'mysql' => [
            // 数据库类型
            'type'            => Env::get('database.type', 'mysql'),
            // 服务器地址
            'hostname'        => Env::get('database.hostname', '127.0.0.1'),
            // 数据库名
            'database'        => Env::get('database.database', 'mkshop'),
            // 用户名
            'username'        => Env::get('database.username', 'root'),
            // 密码
            'password'        => Env::get('database.password', ''),
            // 端口
            'hostport'        => Env::get('database.hostport', '3306'),
            // 连接 DSN（优先级高于 hostname/hostport）
            'dsn'             => '',
            // 参数
            'params'          => [],
            // 编码（全库统一 utf8mb4）
            'charset'         => Env::get('database.charset', 'utf8mb4'),
            // 表前缀
            'prefix'          => Env::get('database.prefix', 'mks_'),
            // 部署方式:0=集中式,1=分布式
            'deploy'          => 0,
            // 读写分离
            'rw_separate'     => false,
            // 主从服务器数量
            'master_num'      => 1,
            // 读服务器密码
            'slave_no'        => '',
            // 是否严格检查字段类型（红线：开启，杜绝脏写入）
            'fields_strict'   => true,
            // 是否需要断线重连
            'break_reconnect' => true,
            // 监听 SQL（调试用）
            'trigger_sql'     => Env::get('app.debug', false),
            // 字段缓存（生产开启）
            'fields_cache'    => !Env::get('app.debug', false),
        ],
    ],
];
