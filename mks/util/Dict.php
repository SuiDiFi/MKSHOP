<?php
// +----------------------------------------------------------------------
// | MKSHOP 字典服务（sys_dict：19 组枚举统一落点，前后端文案/标签色统一取此表）
// +----------------------------------------------------------------------
declare (strict_types = 1);

namespace mks\util;

use app\common\model\SysDict;

class Dict
{
    /**
     * 取某类型的全部码值（含 label/color/sort），Redis 缓存 1 小时
     * @return array<string, array{label:string,color:string,sort:int}>
     */
    public static function type(string $dictType, int $vipId = 0): array
    {
        $key = 'dict:' . $dictType . ':' . $vipId;
        $hit = SysDictCache::get($key);
        if (is_array($hit)) {
            return $hit;
        }
        $rows = SysDict::where('dict_type', $dictType)
            ->where('status', 1)
            ->whereIn('vip_id', [0, $vipId])
            ->order('sort', 'asc')
            ->select();
        $out = [];
        foreach ($rows as $r) {
            $out[(string) $r->getAttr('code')] = [
                'label' => (string) $r->getAttr('label'),
                'color' => (string) $r->getAttr('color'),
                'sort'  => (int) $r->getAttr('sort'),
            ];
        }
        SysDictCache::set($key, $out, 3600);
        return $out;
    }

    /**
     * 码值 → 中文名
     */
    public static function label(string $dictType, string|int $code, int $vipId = 0): string
    {
        $map = self::type($dictType, $vipId);
        return $map[(string) $code]['label'] ?? (string) $code;
    }

    /**
     * 前端选项格式（label/value）
     */
    public static function options(string $dictType, int $vipId = 0): array
    {
        $out = [];
        foreach (self::type($dictType, $vipId) as $code => $item) {
            $out[] = ['label' => $item['label'], 'value' => $code, 'color' => $item['color']];
        }
        return $out;
    }

    public static function flush(): void
    {
        SysDictCache::flush();
    }
}
