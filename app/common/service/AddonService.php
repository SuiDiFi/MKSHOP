<?php
// +----------------------------------------------------------------------
// | 插件服务（M6-C）：本地目录扫描 / 安装 / 启停 / 卸载 / 菜单注入 / 配置
// | 约定：addons/<name>/plugin.json 为唯一清单来源；安装仅允许平台管理员操作
// | 安全：插件标识白名单正则 + 目录真实路径校验 + 菜单快照删除
// +----------------------------------------------------------------------
declare (strict_types = 1);

namespace app\common\service;

use app\common\model\Addon;
use mks\util\SqlImport;
use think\facade\Cache;
use think\facade\Db;
use think\facade\Log;

class AddonService
{
    /** 钩子缓存键（安装/启停/卸载后必须失效） */
    public const HOOK_CACHE_KEY = 'addon:hooks:v1';

    /** 框架内置钩子清单（供后台展示与插件声明参考） */
    public const HOOKS = [
        'order.created'     => '订单创建',
        'order.paid'        => '订单支付成功',
        'order.success'     => '订单完成（结算后）',
        'order.refund'      => '订单退款',
        'member.registered' => '会员注册',
        'vip.share_settled' => '分站抽成结算',
        'timer.tick'        => '定时任务心跳',
    ];

    /**
     * 插件根目录（项目根/addons/）
     */
    public static function dir(): string
    {
        return app()->getRootPath() . 'addons' . DIRECTORY_SEPARATOR;
    }

    /**
     * 扫描本地插件目录（含安装状态）
     */
    public static function scan(): array
    {
        $out = [];
        $dir = self::dir();
        if (!is_dir($dir)) {
            return $out;
        }
        foreach (scandir($dir) ?: [] as $name) {
            if ($name === '.' || $name === '..') {
                continue;
            }
            $manifest = self::manifest($name);
            if ($manifest === null) {
                continue;
            }
            $row = Addon::where('name', $name)->where('delete_time', 0)->findOrEmpty();
            $manifest['installed']   = !$row->isEmpty();
            $manifest['db_id']       = $row->isEmpty() ? 0 : (int) $row->getAttr('id');
            $manifest['status']      = $row->isEmpty() ? 0 : (int) $row->getAttr('status');
            $manifest['status_text'] = $row->isEmpty() ? '未安装' : Addon::statusText((int) $row->getAttr('status'));
            $out[] = $manifest;
        }
        usort($out, fn ($a, $b) => strcmp((string) $a['name'], (string) $b['name']));
        return $out;
    }

    /**
     * 读取插件清单（不存在/非法返回 null）
     */
    public static function manifest(string $name): ?array
    {
        $name = strtolower(trim($name));
        if (!self::validName($name)) {
            return null;
        }
        $base = realpath(self::dir() . $name);
        $root = realpath(self::dir());
        if ($base === false || $root === false || !str_starts_with($base, $root)) {
            return null;
        }
        $file = $base . DIRECTORY_SEPARATOR . 'plugin.json';
        if (!is_file($file)) {
            return null;
        }
        $data = json_decode((string) file_get_contents($file), true);
        if (!is_array($data) || strtolower((string) ($data['name'] ?? '')) !== $name) {
            return null;
        }
        $data['name']        = $name;
        $data['title']       = (string) ($data['title'] ?? $name);
        $data['version']     = (string) ($data['version'] ?? '1.0.0');
        $data['author']      = (string) ($data['author'] ?? '');
        $data['description'] = (string) ($data['description'] ?? '');
        $data['hooks']       = array_values(array_intersect((array) ($data['hooks'] ?? []), array_keys(self::HOOKS)));
        $data['menus']       = is_array($data['menus'] ?? null) ? $data['menus'] : [];
        return $data;
    }

    /**
     * 插件标识白名单（目录名）
     */
    public static function validName(string $name): bool
    {
        return (bool) preg_match('/^[a-z][a-z0-9_]{1,39}$/', $name);
    }

    /**
     * 安装插件（默认安装后即启用）
     * @return array 插件注册行
     */
    public static function install(string $name, bool $enable = true): array
    {
        $name = strtolower(trim($name));
        if (!self::validName($name)) {
            throw_fail('插件标识不合法（仅小写字母/数字/下划线，且以字母开头）');
        }
        $manifest = self::manifest($name);
        if ($manifest === null) {
            throw_fail('插件目录或 plugin.json 不存在');
        }
        if (Addon::where('name', $name)->where('delete_time', 0)->count() > 0) {
            throw_fail('插件已安装，请勿重复安装');
        }

        $config = self::schemaDefaults($name);
        $addon  = null;
        Db::startTrans();
        try {
            // 建表 SQL（DDL 隐式提交，失败时由人工清理，注册行不落库）
            $installSql = self::dir() . $name . DIRECTORY_SEPARATOR . 'sql' . DIRECTORY_SEPARATOR . 'install.sql';
            if (is_file($installSql)) {
                self::runSqlFile($installSql);
            }
            $addon = Addon::create([
                'name'         => $name,
                'title'        => (string) $manifest['title'],
                'version'      => (string) $manifest['version'],
                'author'       => (string) $manifest['author'],
                'description'  => (string) $manifest['description'],
                'hooks'        => json_encode($manifest['hooks'], JSON_UNESCAPED_UNICODE),
                'menus'        => '[]',
                'config'       => json_encode($config, JSON_UNESCAPED_UNICODE),
                'status'       => $enable ? Addon::ST_ENABLE : Addon::ST_DISABLE,
                'install_time' => time(),
                'update_time'  => time(),
            ]);
            $menus = self::injectMenus($name, $addon->getAttr('id'), $manifest['menus']);
            $addon->save(['menus' => json_encode($menus, JSON_UNESCAPED_UNICODE)]);
            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            throw $e;
        }
        self::flushHookCache();
        Log::info('[addon] install ' . $name);
        return $addon->toArray();
    }

    /**
     * 启用插件
     */
    public static function enable(int $id): void
    {
        $addon = self::findOrFail($id);
        $name  = (string) $addon->getAttr('name');
        if (!is_dir(self::dir() . $name)) {
            throw_fail('插件目录不存在，无法启用');
        }
        $addon->save(['status' => Addon::ST_ENABLE, 'update_time' => time()]);
        self::flushHookCache();
    }

    /**
     * 停用插件（保留菜单与数据；钩子立即失效）
     */
    public static function disable(int $id): void
    {
        $addon = self::findOrFail($id);
        $addon->save(['status' => Addon::ST_DISABLE, 'update_time' => time()]);
        self::flushHookCache();
    }

    /**
     * 卸载插件（默认保留数据表；$dropData=true 时执行 sql/uninstall.sql）
     */
    public static function uninstall(int $id, bool $dropData = false): void
    {
        $addon = self::findOrFail($id);
        $name  = (string) $addon->getAttr('name');
        Db::startTrans();
        try {
            // 按快照删除注入的菜单
            $snap = json_decode((string) $addon->getAttr('menus'), true);
            $ids  = [];
            foreach ((array) $snap as $m) {
                if ((int) ($m['id'] ?? 0) > 0) {
                    $ids[] = (int) $m['id'];
                }
            }
            if ($ids !== []) {
                Db::name('admin_router')->whereIn('id', $ids)->delete();
            }
            if ($dropData) {
                $uninstallSql = self::dir() . $name . DIRECTORY_SEPARATOR . 'sql' . DIRECTORY_SEPARATOR . 'uninstall.sql';
                if (is_file($uninstallSql)) {
                    self::runSqlFile($uninstallSql);
                }
            }
            // 硬删除注册行，允许重新安装
            Db::name('addon')->where('id', $id)->delete();
            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            throw $e;
        }
        self::flushHookCache();
        Log::info('[addon] uninstall ' . $name . ($dropData ? ' (drop data)' : ''));
    }

    /**
     * 保存插件配置（仅允许 config.php 声明的字段）
     */
    public static function saveConfig(int $id, array $form): array
    {
        $addon  = self::findOrFail($id);
        $name   = (string) $addon->getAttr('name');
        $schema = self::schema($name);
        $allow  = array_flip(array_map(fn ($f) => (string) ($f['name'] ?? ''), $schema));
        $old    = json_decode((string) $addon->getAttr('config'), true) ?: [];
        $new    = $old;
        foreach ($form as $key => $value) {
            if (isset($allow[$key])) {
                $new[$key] = is_scalar($value) ? (string) $value : json_encode($value, JSON_UNESCAPED_UNICODE);
            }
        }
        $addon->save(['config' => json_encode($new, JSON_UNESCAPED_UNICODE), 'update_time' => time()]);
        return $new;
    }

    /**
     * 插件配置值（含默认值）
     */
    public static function config(string $name): array
    {
        $row = Addon::where('name', $name)->where('delete_time', 0)->findOrEmpty();
        $val = $row->isEmpty() ? [] : (json_decode((string) $row->getAttr('config'), true) ?: []);
        return array_merge(self::schemaDefaults($name), $val);
    }

    /**
     * 配置表单 schema（addons/<name>/config.php）
     */
    public static function schema(string $name): array
    {
        $file = self::dir() . $name . DIRECTORY_SEPARATOR . 'config.php';
        if (!is_file($file)) {
            return [];
        }
        $schema = include $file;
        return is_array($schema) ? $schema : [];
    }

    /**
     * 配置默认值
     */
    public static function schemaDefaults(string $name): array
    {
        $out = [];
        foreach (self::schema($name) as $field) {
            $key = (string) ($field['name'] ?? '');
            if ($key !== '') {
                $out[$key] = (string) ($field['default'] ?? '');
            }
        }
        return $out;
    }

    /**
     * 启用插件中监听某钩子的插件名
     */
    public static function listeners(string $hook): array
    {
        $map = Cache::store('redis')->remember(self::HOOK_CACHE_KEY, function () {
            $map = [];
            $rows = Addon::where('status', Addon::ST_ENABLE)->where('delete_time', 0)
                ->field('name,hooks')->select();
            foreach ($rows as $row) {
                $hooks = json_decode((string) $row->getAttr('hooks'), true) ?: [];
                foreach ((array) $hooks as $h) {
                    $map[(string) $h][] = (string) $row->getAttr('name');
                }
            }
            return $map;
        }, 60);
        return (array) ($map[$hook] ?? []);
    }

    /**
     * 插件实例（手动 require，避免 composer autoload 依赖部署脚本）
     */
    public static function instance(string $name): ?object
    {
        static $pool = [];
        if (array_key_exists($name, $pool)) {
            return $pool[$name];
        }
        if (!self::validName($name)) {
            return $pool[$name] = null;
        }
        $file = self::dir() . $name . DIRECTORY_SEPARATOR . 'Plugin.php';
        if (!is_file($file)) {
            return $pool[$name] = null;
        }
        require_once $file;
        $class = 'addons\\' . $name . '\\Plugin';
        if (!class_exists($class)) {
            return $pool[$name] = null;
        }
        try {
            $pool[$name] = new $class();
        } catch (\Throwable $e) {
            Log::error('[addon] instance ' . $name . ': ' . $e->getMessage());
            $pool[$name] = null;
        }
        return $pool[$name];
    }

    /**
     * 失效钩子缓存
     */
    public static function flushHookCache(): void
    {
        Cache::store('redis')->delete(self::HOOK_CACHE_KEY);
    }

    /**
     * 注入后台菜单，返回快照 [{id,name}]
     */
    protected static function injectMenus(string $addonName, int $addonId, array $menus): array
    {
        $snap = [];
        // 默认挂到"系统设置"分组（MksDictList 同级），找不到则顶级
        $defaultParent = (int) Db::name('admin_router')
            ->where('name', 'MksDictList')->where('auth_type', 1)->value('parentId');
        $i = 0;
        foreach ($menus as $menu) {
            $i++;
            $title = trim((string) ($menu['title'] ?? ''));
            $path  = trim((string) ($menu['path'] ?? ''));
            if ($title === '' || $path === '') {
                continue;
            }
            $parentId = $defaultParent;
            if (!empty($menu['parent'])) {
                $pid = Db::name('admin_router')->where('name', (string) $menu['parent'])
                    ->where('auth_type', 1)->value('parentId');
                if ($pid !== null) {
                    $parentId = (int) $pid;
                }
            }
            $nodeName = 'MksAddon' . ucfirst($addonName) . $i;
            if (Db::name('admin_router')->where('name', $nodeName)->count() > 0) {
                continue;
            }
            $id = Db::name('admin_router')->insertGetId([
                'parentId'   => $parentId,
                'title'      => mb_substr($title, 0, 50, 'UTF-8'),
                'sort'       => (int) ($menu['sort'] ?? 90),
                'name'       => $nodeName,
                'path'       => ltrim($path, '/'),
                'icon'       => (string) ($menu['icon'] ?? 'menu'),
                'component'  => (string) ($menu['component'] ?? '@/views/placeholder'),
                'auth_type'  => 1,
                'api_url'    => '',
                'methods'    => '',
                'delete_time' => 0,
            ]);
            $snap[] = ['id' => $id, 'name' => $nodeName];
        }
        return $snap;
    }

    /**
     * 执行插件 SQL 文件（按分号切分，逐语句执行）
     */
    protected static function runSqlFile(string $file): void
    {
        $sql = (string) file_get_contents($file);
        foreach (SqlImport::split($sql) as $stmt) {
            $stmt = trim($stmt);
            if ($stmt !== '') {
                Db::execute($stmt);
            }
        }
    }

    /**
     * 按 ID 取启用/停用插件
     */
    protected static function findOrFail(int $id): Addon
    {
        $addon = Addon::where('id', $id)->where('delete_time', 0)->findOrEmpty();
        if ($addon->isEmpty()) {
            throw_fail('插件不存在');
        }
        return $addon;
    }
}
