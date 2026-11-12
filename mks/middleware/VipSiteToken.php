<?php
// +----------------------------------------------------------------------
// | 分站上下文中间件（M6：按 Host 解析分站，注入 request->vipSite）
// +----------------------------------------------------------------------
declare (strict_types = 1);

namespace mks\middleware;

use app\common\service\VipSiteService;
use think\Request;

class VipSiteToken
{
    public function handle(Request $request, \Closure $next)
    {
        $site = VipSiteService::resolveByHost((string) $request->host(), (string) $request->pathinfo());
        $request->vipSite   = $site;
        $request->vipId     = $site ? (int) $site->getAttr('id') : 0;
        return $next($request);
    }
}
