<?php
// +----------------------------------------------------------------------
// | 前台 · 站点信息（M6：域名/二级目录 → 分站主题与配置）
// +----------------------------------------------------------------------
declare (strict_types = 1);

namespace app\controller\front;

use app\common\service\VipSiteService;
use mks\BaseController;

class SiteController extends BaseController
{
    /**
     * 当前访问站点信息（主站或分站）
     */
    public function info()
    {
        $site = $this->request->vipSite ?? null;
        return $this->ok(VipSiteService::siteInfo($site));
    }
}
