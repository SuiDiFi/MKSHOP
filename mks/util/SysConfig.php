<?php
// +----------------------------------------------------------------------
// | MKSHOP 系统配置服务（mks_config“配置即表单”；Redis 缓存 + 版本失效）
// +----------------------------------------------------------------------
declare (strict_types = 1);

namespace mks\util;

use app\common\model\Config;
use think\facade\Cache;

class SysConfig
{
    private const CACHE_TAG = 'sys_config';

    /**
     * 读取配置（vip_id 覆盖：分站配置优先）
     */
    public static function get(string $name, mixed $default = null, int $vipId = 0): mixed
    {
        $key = 'cfg:' . $name . ':' . $vipId;
        $hit = Cache::store('redis')->get($key, null);
        if ($hit !== null && array_key_exists('__v', (array) $hit)) {
            return $hit['v'];
        }
        $row = Config::where('name', $name)->where('vip_id', $vipId)->findOrEmpty();
        if ($row->isEmpty() && $vipId > 0) {
            $row = Config::where('name', $name)->where('vip_id', 0)->findOrEmpty();
        }
        $value = $row->isEmpty() ? $default : self::cast($row);
        Cache::store('redis')->set($key, ['__v' => 1, 'v' => $value], 3600);
        return $value;
    }

    /**
     * 写配置（原系统是安装时灌入 + 后台改值；这里提供改值入口）
     */
    public static function set(string $name, string $value, int $vipId = 0): void
    {
        Config::update(['value' => $value], ['name' => $name, 'vip_id' => $vipId]);
        self::flush($name, $vipId);
    }

    /**
     * 失效缓存（单键 + 全量两档）
     */
    public static function flush(?string $name = null, ?int $vipId = null): void
    {
        if ($name !== null) {
            Cache::store('redis')->delete('cfg:' . $name . ':' . ($vipId ?? 0));
            return;
        }
        // 全清：按前缀扫删（配置项有限，可接受）
        $rows = Config::column('name');
        foreach (array_unique($rows) as $n) {
            Cache::store('redis')->delete('cfg:' . $n . ':0');
        }
    }

    /**
     * 按控件类型反解值（红线：配置类型化，避免业务侧到处 json_decode 猜类型）
     */
    protected static function cast(object $row): mixed
    {
        $v = (string) $row->getAttr('value');
        return match ((string) $row->getAttr('type')) {
            'switch', 'checkbox' => $v === '' ? [] : ($v === '1' || $v === 'true' ? true : (str_contains($v, ',') ? explode(',', $v) : $v)),
            'number'             => is_numeric($v) ? $v + 0 : $v,
            'select', 'radio'    => $v,
            'diyTable', 'editor' => self::tryJson($v),
            default              => $v,
        };
    }

    protected static function tryJson(string $v): mixed
    {
        if ($v === '') {
            return $v;
        }
        $d = json_decode($v, true);
        return json_last_error() === JSON_ERROR_NONE ? $d : $v;
    }
}
