<?php
// +----------------------------------------------------------------------
// | 商户端鉴权（M6-B）：会员账号 + member.has_merchant + merchant.status
// | 依赖 MemberToken 先执行
// +----------------------------------------------------------------------
declare (strict_types = 1);

namespace mks\middleware;

use app\common\model\Member;
use app\common\model\Merchant;
use think\Request;

class MerchantAuth
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
        if ((int) $member->getAttr('has_merchant') !== 1) {
            return json_fail('当前账号未开通商户', 403, 403);
        }
        $merchantId = (int) $member->getAttr('merchant_id');
        $merchant = $merchantId > 0
            ? Merchant::findOrEmpty($merchantId)
            : Merchant::where('uid', $memberId)->where('delete_time', 0)->findOrEmpty();
        if ($merchant->isEmpty() || (int) $merchant->getAttr('delete_time') !== 0) {
            return json_fail('商户资料不存在', 403, 403);
        }
        if ((int) $merchant->getAttr('status') !== 1) {
            return json_fail('商户已停用，请联系平台', 403, 403);
        }
        $request->merchant  = $merchant;
        $request->member    = $member;
        return $next($request);
    }
}
