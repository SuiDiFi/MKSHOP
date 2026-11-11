<?php
// +----------------------------------------------------------------------
// | MKSHOP 会话配置（存储走 Redis 缓存驱动；Cookie 名 MKSSID）
// +----------------------------------------------------------------------
use think\facade\Env;

return [
    // session name
    'name'           => 'MKSSID',
    // SESSION_ID 的提交变量,解决flash上传跨域
    'var_session_id' => '',
    // 驱动方式（cache=使用缓存驱动，即 Redis）
    'type'           => 'cache',
    // 存储连接句柄（对应 cache.stores 键）
    'store'          => 'redis',
    // 过期时间（秒）
    'expire'         => 7200,
    // 前缀
    'prefix'         => '',
];
