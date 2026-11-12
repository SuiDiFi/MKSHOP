<?php
// +----------------------------------------------------------------------
// | 分站主鉴权（M6）：会员账号 + vip_site.uid 绑定
// | 依赖 MemberToken 先执行；校验 has_vip、vip_site.uid、启用/到期
// +----------------------------------------------------------------------
declare (strict_types = 1);

namespace mks\middleware;

use app\common\model\Member;
use app\common\model\VipSite;
use think\Request;

class VipOwnerAuth
{
    public function handle(Request $request, \Closure $next)
    {
        $memberId = (int) ($request->memberId ?? 0);
        if ($memberId <= 0) {
            return json_fail('未登录或令牌缺失', 401, 401);
        }
        $member = Member::findOrEmpty($memberId);
        if ($member->isEmpty()) {
            return json_fail('账号不存在或已禁用', 401, 401);
        }
        $vipId = (int) $member->getAttr('vip_id');
        if ((int) $member->getAttr('has_vip') !== 1 || $vipId <= 0) {
            return json_fail('当前账号未开通分站', 403, 403);
        }
        $site = VipSite::findOrEmpty($vipId);
        if ($site->isEmpty() || (int) $site->getAttr('uid') !== $memberId) {
            return json_fail('无权访问该分站', 403, 403);
        }
        if (!$site->isValid()) {
            return json_fail('分站已停用或已到期', 403, 403);
        }
        $request->vipSite = $site;
        $request->vipId   = $vipId;
        return $next($request);
    }
}
