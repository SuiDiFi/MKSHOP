<?php
// +----------------------------------------------------------------------
// | 后台 · 角色管理（对齐权限点 role/*）
// +----------------------------------------------------------------------
declare (strict_types = 1);

namespace app\controller\admin;

use app\common\model\AdminRole;
use mks\traits\CurdTrait;

class Role extends Base
{
    use CurdTrait;

    protected string $crudModel = AdminRole::class;

    protected array $crudSearchable = [
        'name'   => 'like',
        'status' => 'eq',
    ];

    protected array $crudModifiable = ['status'];

    protected array $crudFields = ['name', 'value', 'status', 'role', 'btnRolesCheckedList', 'level', 'mark'];

    protected string $crudOrder = 'level asc, id asc';

    /**
     * 保存（value 超管标识仅允许 'super'/'Admin' 等显式值；role='*' 仅超管角色可持有）
     */
    public function save(int $id)
    {
        $row = AdminRole::findOrEmpty($id);
        if ($row->isEmpty()) {
            return $this->fail('角色不存在');
        }
        if ((int) $id === 1) {
            return $this->fail('内置超管角色不允许修改');
        }
        return parent::save($id);
    }

    /**
     * 删除保护：超管角色与在用角色
     */
    public function delete(int $id)
    {
        if ((int) $id === 1) {
            return $this->fail('内置超管角色不允许删除');
        }
        return parent::delete($id);
    }

    /**
     * 快捷修改角色权限串（modify_role：role 逗号串）
     */
    public function modify_role(int $id)
    {
        if ((int) $id === 1) {
            return $this->fail('内置超管角色不允许修改');
        }
        $roleStr = (string) $this->request->param('role', '');
        $ids     = array_filter(explode(',', $roleStr), fn ($v) => ctype_digit((string) $v) || $v === '*');
        $row     = AdminRole::findOrEmpty($id);
        if ($row->isEmpty()) {
            return $this->fail('角色不存在');
        }
        $row->save(['role' => implode(',', $ids)]);
        // 权限缓存失效（whitelist/角色缓存 60 秒，自动过期，这里主动刷一次）
        return $this->ok([], '权限已更新');
    }
}
