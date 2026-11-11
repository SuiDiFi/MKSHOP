<?php
// +----------------------------------------------------------------------
// | 后台菜单/接口权限模型（一表两用：auth_type=1 菜单节点，=2 接口权限点）
// +----------------------------------------------------------------------
declare (strict_types = 1);

namespace app\common\model;

class AdminRouter extends BaseModel
{
    protected $name = 'admin_router';
}
