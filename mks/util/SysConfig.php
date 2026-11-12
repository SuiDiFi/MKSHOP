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
     * 写配置（存在则更新，不存在则插入分站覆盖行；M6 修复写不进去的问题）
     */
    public static function set(string $name, string $value, int $vipId = 0): void
    {
        $row = Config::where('name', $name)->where('vip_id', $vipId)->findOrEmpty();
        if ($row->isEmpty()) {
            // 分站覆盖行不存在：以主站同名为模板插入（唯一键 name+vip_id）
            $base = Config::where('name', $name)->where('vip_id', 0)->findOrEmpty();
            $row  = new Config();
            $row->save([
                'name'        => $name,
                'vip_id'      => $vipId,
                'value'       => $value,
                'info'        => $base->isEmpty() ? $name : (string) $base->getAttr('info'),
                'type'        => $base->isEmpty() ? 'text' : (string) $base->getAttr('type'),
                'input_type'  => $base->isEmpty() ? 'input' : (string) $base->getAttr('input_type'),
                'group'       => $base->isEmpty() ? 'vip' : (string) $base->getAttr('group'),
                'parameter'   => $base->isEmpty() ? '' : (string) $base->getAttr('parameter'),
                'upload_type' => $base->isEmpty() ? 1 : (int) $base->getAttr('upload_type'),
                'required'    => $base->isEmpty() ? '' : (string) $base->getAttr('required'),
                'width'       => 0,
                'desc'        => '',
                'high'        => 0,
                'sort'        => $base->isEmpty() ? 0 : (int) $base->getAttr('sort'),
                'props'       => $base->isEmpty() ? '' : (string) $base->getAttr('props'),
                'status'      => 1,
                'delete_time' => 0,
                'son_names'   => $base->isEmpty() ? '' : (string) $base->getAttr('son_names'),
            ]);
        } else {
            $row->save(['value' => $value]);
        }
        // 主站配置变更会影响分站回落值，统一按 name 清所有 vip 维度
        self::flush($name);
    }

    /**
     * 失效缓存（单键 + 全量两档）
     */
    public static function flush(?string $name = null, ?int $vipId = null): void
    {
        if ($name !== null) {
            if ($vipId !== null) {
                Cache::store('redis')->delete('cfg:' . $name . ':' . $vipId);
                return;
            }
            $vipIds = Config::where('name', $name)->column('vip_id');
            $vipIds[] = 0;
            foreach (array_unique(array_map('intval', $vipIds)) as $id) {
                Cache::store('redis')->delete('cfg:' . $name . ':' . $id);
            }
            return;
        }
        // 全清：按 name + vip_id 逐键删除（配置项有限，可接受）
        $rows = Config::field('name,vip_id')->select();
        foreach ($rows as $r) {
            Cache::store('redis')->delete('cfg:' . (string) $r->getAttr('name') . ':' . (int) $r->getAttr('vip_id'));
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
