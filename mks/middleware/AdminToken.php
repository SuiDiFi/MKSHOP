<?php
// +----------------------------------------------------------------------
// | MKSHOP 后台登录态中间件（JWT 校验 → 注入 admin）
// +----------------------------------------------------------------------
declare (strict_types = 1);

namespace mks\middleware;

use app\common\model\Admin;
use mks\util\Jwt;
use think\Request;
use think\Response;

class AdminToken
{
    public function handle(Request $request, \Closure $next): Response
    {
        $auth  = (string) $request->header('authorization', '');
        $token = str_starts_with(strtolower($auth), 'bearer ') ? trim(substr($auth, 7)) : (string) $request->param('token', '');

        if ($token === '') {
            return json_fail('未登录或令牌缺失', 401, 401);
        }

        try {
            $claims = Jwt::verify($token, 'admin');
        } catch (\Throwable $e) {
            return json_fail($e->getMessage() ?: '登录已失效', 401, 401);
        }

        $admin = Admin::where('id', (int) ($claims['sub'] ?? 0))
            ->where('status', 1)
            ->where('delete_time', 0)
            ->findOrEmpty();
        if ($admin->isEmpty()) {
            return json_fail('账号不存在或已禁用', 401, 401);
        }

        // 注入请求对象：adminId / adminInfo / jti
        $request->adminId   = (int) $admin->getAttr('id');
        $request->adminInfo = $admin->toArray();
        $request->jti       = (string) ($claims['jti'] ?? '');

        return $next($request);
    }
}
