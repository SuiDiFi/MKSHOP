<?php
// +----------------------------------------------------------------------
// | 接口权限白名单模型（修正版 RBAC：显式豁免登记处）
// +----------------------------------------------------------------------
declare (strict_types = 1);

namespace app\common\model;

class ApiPermission extends BaseModel
{
    protected $name = 'api_permission';
}
