<?php
// +----------------------------------------------------------------------
// | MKSHOP 后台权限中间件（修正版 RBAC）
// 修正点（对照原系统缺陷，红线 4）：
//   1. 默认拒绝：接口未登记（admin_router.auth_type=2）→ 一律 403，杜绝“忘登记=裸奔”
//   2. 方法级校验：api_url + methods 二元组匹配，不再只比 URL
//   3. 超管显式标记：admin_role.role='*' 或 value='super'，不再硬编码 id==1
//   4. mks_api_permission 作为“显式白名单豁免表”（公共接口登记处）
// +----------------------------------------------------------------------
declare (strict_types = 1);

namespace mks\middleware;

use app\common\model\AdminRole;
use app\common\model\AdminRouter;
use app\common\model\ApiPermission;
use think\facade\Cache;
use think\Request;
use think\Response;

class AdminAuth
{
    public function handle(Request $request, \Closure $next): Response
    {
        $adminId   = (int) ($request->adminId ?? 0);
        $adminInfo = (array) ($request->adminInfo ?? []);
        if ($adminId <= 0) {
            return json_fail('未登录', 401, 401);
        }

        // 当前接口三元组：path（去掉 admin 组前缀）+ method
        $pathInfo = ltrim((string) $request->pathinfo(), '/');
        $segments = explode('/', $pathInfo, 2);
        $group    = $segments[0] ?? '';
        $path     = $segments[1] ?? $segments[0];
        if ($group !== 'admin') {
            return json_fail('非法访问', 403, 403);
        }
        $method = strtoupper((string) $request->method());
        if ($method === 'OPTIONS') {
            return $next($request);
        }

        // 0) 超管判定（显式标记，非 id 硬编码）
        if (self::isSuper($adminInfo)) {
            return $next($request);
        }

        // 1) 白名单豁免（公共接口显式登记：path+methods）
        if (self::inWhitelist($path, $method)) {
            return $next($request);
        }

        // 2) 接口登记表：未登记 → 默认拒绝（修正点 1）
        $permission = AdminRouter::where('auth_type', 2)
            ->where('delete_time', 0)
            ->where('api_url', $path)
            ->findOrEmpty();
        if ($permission->isEmpty()) {
            return json_fail('接口未授权访问（未登记）', 403, 403);
        }

        // 3) 方法级校验（修正点 2）
        $allowedMethods = array_map('strtoupper', array_filter(explode(',', (string) $permission->getAttr('methods'))));
        if ($allowedMethods !== [] && !in_array($method, $allowedMethods, true)) {
            return json_fail('请求方法不被允许', 403, 403);
        }

        // 4) 角色授权：admin.roles → admin_role.role（router id 逗号串）
        $routerId = (int) $permission->getAttr('id');
        if (!self::roleHas($adminInfo, $routerId)) {
            return json_fail('无权执行该操作', 403, 403);
        }

        return $next($request);
    }

    /**
     * 超管：role='*' 或 role.value='super'
     */
    public static function isSuper(array $adminInfo): bool
    {
        static $cache = [];
        $adminId = (int) ($adminInfo['id'] ?? 0);
        if (isset($cache[$adminId])) {
            return $cache[$adminId];
        }
        $roles = self::rolesOf($adminInfo);
        $super = false;
        foreach ($roles as $role) {
            if ((string) $role->getAttr('role') === '*' || (string) $role->getAttr('value') === 'super') {
                $super = true;
                break;
            }
        }
        return $cache[$adminId] = $super;
    }

    /**
     * 白名单（60 秒缓存）
     */
    protected static function inWhitelist(string $path, string $method): bool
    {
        $rows = Cache::store('redis')->remember('rbac:whitelist', function () {
            return ApiPermission::where('status', 1)->column('methods', 'path');
        }, 60);
        $methods = (string) ($rows[$path] ?? '');
        if ($methods === '') {
            return false;
        }
        return in_array($method, array_map('strtoupper', array_filter(explode(',', $methods))), true);
    }

    /**
     * 角色是否拥有 router id（60 秒缓存）
     */
    protected static function roleHas(array $adminInfo, int $routerId): bool
    {
        $roles   = self::rolesOf($adminInfo);
        $allowed = [];
        foreach ($roles as $role) {
            $str = (string) $role->getAttr('role');
            if ($str === '*') {
                return true;
            }
            foreach (explode(',', $str) as $id) {
                if ($id !== '') {
                    $allowed[(int) $id] = true;
                }
            }
        }
        return isset($allowed[$routerId]);
    }

    /**
     * 展开管理员角色模型
     * @return \think\model\Collection|array
     */
    public static function rolesOf(array $adminInfo)
    {
        $roleIds = array_filter(explode(',', (string) ($adminInfo['roles'] ?? '')));
        if ($roleIds === []) {
            return [];
        }
        return AdminRole::where('status', 1)
            ->where('delete_time', 0)
            ->whereIn('id', $roleIds)
            ->select();
    }

    /**
     * 菜单树（getMenus 用）：auth_type=1 节点按角色过滤
     */
    public static function menusOf(array $adminInfo): array
    {
        $super = self::isSuper($adminInfo);
        if ($super) {
            $rows = AdminRouter::where('auth_type', 1)->where('delete_time', 0)->order('sort', 'desc')->select();
        } else {
            $roles   = self::rolesOf($adminInfo);
            $allowed = [];
            foreach ($roles as $role) {
                $str = (string) $role->getAttr('role');
                if ($str === '*') {
                    $allowed = [];
                    break;
                }
                foreach (explode(',', $str) as $id) {
                    if ($id !== '') {
                        $allowed[] = (int) $id;
                    }
                }
            }
            $query = AdminRouter::where('auth_type', 1)->where('delete_time', 0);
            if ($allowed !== []) {
                $query->whereIn('id', array_values(array_unique($allowed)));
            } elseif ($allowed === [] && !$super) {
                return [];
            }
            $rows = $query->order('sort', 'desc')->select();
        }

        // 组树（parentId）
        $items = [];
        foreach ($rows as $r) {
            $items[(int) $r->getAttr('id')] = $r->toArray();
        }
        $tree = [];
        foreach ($items as $id => &$node) {
            $pid = (int) ($node['parentId'] ?? 0);
            if ($pid > 0 && isset($items[$pid])) {
                $items[$pid]['children'][] = &$node;
            } else {
                $tree[] = &$node;
            }
        }
        unset($node);
        return array_values($tree);
    }
}
