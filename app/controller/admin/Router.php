<?php
// +----------------------------------------------------------------------
// | 后台 · 菜单/接口权限管理（对齐权限点 router/*）
// +----------------------------------------------------------------------
declare (strict_types = 1);

namespace app\controller\admin;

use app\common\model\AdminRouter;
use mks\middleware\AdminAuth;
use mks\traits\CurdTrait;

class Router extends Base
{
    use CurdTrait;

    protected string $crudModel = AdminRouter::class;

    protected array $crudSearchable = [
        'title'     => 'like',
        'auth_type' => 'eq',
        'parentId'  => 'eq',
    ];

    protected array $crudModifiable = ['hidden', 'sort'];

    protected array $crudFields = [
        'parentId', 'title', 'sort', 'name', 'path', 'icon', 'component', 'redirect',
        'hidden', 'levelHidden', 'tabHidden', 'badge', 'noColumn', 'noClosable', 'target',
        'activeMenu', 'dot', 'dynamicNewTab', 'breadcrumbHidden', 'noKeepAlive', 'isCustomSvg',
        'auth_type', 'api_url', 'methods',
    ];

    protected string $crudOrder = 'sort desc, id asc';

    /**
     * 菜单树（前端路由注册用）
     */
    public function list()
    {
        $rows = AdminRouter::where('auth_type', 1)->order('sort', 'desc')->select()->toArray();
        return $this->ok(AdminAuth::menusOf($this->adminInfo()) ?: $this->buildTree($rows));
    }

    /**
     * 保存（菜单 name 唯一）
     */
    public function save(int $id)
    {
        $form = $this->crudFormData();
        if (isset($form['name']) && $form['name'] !== '') {
            $exists = AdminRouter::where('name', (string) $form['name'])->where('id', '<>', $id)->count();
            if ($exists > 0) {
                return $this->fail('路由 name 已存在');
            }
        }
        return parent::save($id);
    }

    /**
     * 新建（同上唯一性）
     */
    public function create()
    {
        $form = $this->crudFormData();
        if (($form['name'] ?? '') !== '') {
            $exists = AdminRouter::where('name', (string) $form['name'])->count();
            if ($exists > 0) {
                return $this->fail('路由 name 已存在');
            }
        }
        return parent::create();
    }

    /**
     * 平铺转树（兜底用）
     */
    protected function buildTree(array $rows): array
    {
        $items = [];
        foreach ($rows as $r) {
            $items[(int) $r['id']] = $r;
        }
        $tree = [];
        foreach ($items as $id => &$node) {
            $pid = (int) ($node['parentId'] ?? 0);
            if ($pid > 0 && isset($items[$pid])) {
                $items[$pid]['children'][] = &$node;
            } else {
                $tree[] = &$node;
            }
        }
        unset($node);
        return array_values($tree);
    }
}
