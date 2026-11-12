<?php
// +----------------------------------------------------------------------
// | 前台 · DIY 页面配置（M5b；仅返回已发布页面的组件数组）
// +----------------------------------------------------------------------
declare (strict_types = 1);

namespace app\controller\front;

use app\common\model\DiyPage;
use mks\BaseController;

class DiyController extends BaseController
{
    /**
     * PC 首页装修配置（未发布/无组件返回空，前台回退默认布局）
     */
    public function pcHome()
    {
        // preview=1 用于后台装修预览：返回草稿组件（不校验发布状态）
        $preview = (int) $this->request->param('preview', 0) === 1;
        $query = DiyPage::where('pid', DiyPage::PID_PC_HOME)->where('delete_time', 0);
        if (!$preview) {
            $query = $query->where('status', 1);
        }
        $page = $query->findOrEmpty();
        if ($page->isEmpty()) {
            return $this->ok(['title' => '', 'components' => []]);
        }
        return $this->ok([
            'title'      => (string) $page->getAttr('title'),
            'components' => $page->componentsValue(),
        ]);
    }
}
