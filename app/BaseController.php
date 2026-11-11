<?php
// +----------------------------------------------------------------------
// | MKSHOP 基础控制器
// +----------------------------------------------------------------------
declare (strict_types = 1);

namespace app;

use think\App;
use think\Request;

/**
 * 控制器基础类
 */
abstract class BaseController
{
    protected App $app;

    protected Request $request;

    public function __construct(App $app)
    {
        $this->app     = $app;
        $this->request = $app->request;

        // 控制器初始化
        $this->initialize();
    }

    protected function initialize(): void
    {
    }
}
