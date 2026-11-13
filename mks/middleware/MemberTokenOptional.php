<?php
// +----------------------------------------------------------------------
// | 前台会员登录态中间件（可选版）：有 token 且有效 → 注入 member；缺失/失效 → 静默以游客放行
// | 用途：商品列表/详情/首页聚合等"登录可见会员密价、游客正常浏览"的公开接口
// | 红线：与 MemberToken 同源校验（Jwt scene=member + member status/close 校验），只是不强制
// +----------------------------------------------------------------------
declare (strict_types = 1);

namespace mks\middleware;

use app\common\model\Member;
use mks\util\Jwt;
use think\Request;
use think\Response;

class MemberTokenOptional
{
    public function handle(Request $request, \Closure $next): Response
    {
        $auth  = (string) $request->header('authorization', '');
        $token = str_starts_with(strtolower($auth), 'bearer ') ? trim(substr($auth, 7)) : (string) $request->param('token', '');

        if ($token !== '') {
            try {
                $claims = Jwt::verify($token, 'member');
                $member = Member::where('id', (int) ($claims['sub'] ?? 0))
                    ->where('status', 1)
                    ->where('delete_time', 0)
                    ->where('close', 0)
                    ->findOrEmpty();
                if (!$member->isEmpty()) {
                    $request->memberId   = (int) $member->getAttr('id');
                    $request->memberInfo = $member->toArray();
                    $request->jti        = (string) ($claims['jti'] ?? '');
                }
            } catch (\Throwable $e) {
                // 令牌缺失/失效一律按游客处理（浏览类接口不因过期 token 报错）
            }
        }

        return $next($request);
    }
}
