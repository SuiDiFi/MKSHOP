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
        $page = DiyPage::where('pid', DiyPage::PID_PC_HOME)
            ->where('status', 1)
            ->where('delete_time', 0)
            ->findOrEmpty();
        if ($page->isEmpty()) {
            return $this->ok(['title' => '', 'components' => []]);
        }
        return $this->ok([
            'title'      => (string) $page->getAttr('title'),
            'components' => $page->componentsValue(),
        ]);
    }
}
