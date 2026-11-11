<?php
// +----------------------------------------------------------------------
// | 后台控制器基类
// +----------------------------------------------------------------------
declare (strict_types = 1);

namespace app\controller\admin;

use mks\BaseController;

abstract class Base extends BaseController
{
    /**
     * 当前管理员 ID（AdminToken 中间件注入）
     */
    protected function adminId(): int
    {
        return (int) ($this->request->adminId ?? 0);
    }

    /**
     * 当前管理员信息
     */
    protected function adminInfo(): array
    {
        return (array) ($this->request->adminInfo ?? []);
    }
}
