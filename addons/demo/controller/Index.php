<?php
// +----------------------------------------------------------------------
// | 演示插件控制器（通过 /addons/demo/index/index 访问）
// +----------------------------------------------------------------------
declare (strict_types = 1);

namespace addons\demo\controller;

use app\common\service\AddonService;

class Index
{
    /**
     * 插件自检信息
     */
    public function index(): array
    {
        return [
            'addon'   => 'demo',
            'title'   => '演示插件',
            'version' => '1.0.0',
            'hooks'   => ['order.paid', 'order.success', 'order.refund'],
            'config'  => AddonService::config('demo'),
            'time'    => date('Y-m-d H:i:s'),
        ];
    }

    /**
     * 心跳
     */
    public function ping(): array
    {
        return ['pong' => true, 'time' => time()];
    }
}
