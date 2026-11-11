<?php
// +----------------------------------------------------------------------
// | 后台 · 商品分类管理（对齐权限点 category/*；树形，表无软删 → 硬删）
// +----------------------------------------------------------------------
declare (strict_types = 1);

namespace app\controller\admin;

use app\common\model\GoodsCategory;
use mks\traits\CurdTrait;
use think\facade\Db;

class GoodsCategoryController extends Base
{
    use CurdTrait;

    protected string $crudModel = GoodsCategory::class;

    protected array $crudSearchable = [
        'name'   => 'like',
        'status' => 'eq',
        'pid'    => 'eq',
    ];

    protected array $crudModifiable = ['status', 'sort', 'name', 'istop', 'color', 'badge'];

    protected array $crudFields = [
        'name', 'info', 'color', 'status', 'pid', 'sort', 'img', 'img2', 'short_name',
        'istop', 'ad_img', 'ad_url', 'badge', 'badge_color', 'tip', 'open_dir',
        'is_jump_url', 'jump_url', 'cate_type', 'level', 'path',
    ];

    protected string $crudOrder = 'sort desc, id asc';

    /**
     * 全量树（分类选择下拉/树表共用，对接权限点 category/list）：pid/sort 排序后组树
     */
    public function list()
    {
        $rows = GoodsCategory::order('sort desc, id asc')->select()->toArray();
        return $this->ok($this->buildTree($rows));
    }

    /**
     * 新建：层级冗余维护
     */
    public function create()
    {
        $data = $this->crudFormData();
        if ($data === []) {
            return $this->fail('没有需要保存的数据');
        }
        $pid   = (int) ($data['pid'] ?? 0);
        $level = 0;
        if ($pid > 0) {
            $parent = GoodsCategory::findOrEmpty($pid);
            if ($parent->isEmpty()) {
                return $this->fail('父级分类不存在');
            }
            $level = (int) $parent->getAttr('level') + 1;
            $parent->save(['has_child' => 1]);
        }
        $data['pid']        = $pid;
        $data['level']      = $level;
        $data['path']       = $pid > 0 ? trim((string) $parent->getAttr('path') . ',' . $pid, ',') : '';
        $data['has_child']  = 0;
        $result = $this->crudCreate($data);
        return $result instanceof \think\Response\Json ? $result : $this->ok($result, '创建成功');
    }

    /**
     * 更新：pid 变更时维护父子 has_child
     */
    public function save(int $id)
    {
        $row = GoodsCategory::findOrEmpty($id);
        if ($row->isEmpty()) {
            return $this->fail('分类不存在');
        }
        $data = $this->crudFormData();
        if ($data === []) {
            return $this->fail('没有需要保存的数据');
        }
        if (isset($data['pid'])) {
            $newPid = (int) $data['pid'];
            if ($newPid === (int) $id) {
                return $this->fail('父级不能是自身');
            }
            $oldPid = (int) $row->getAttr('pid');
            if ($newPid !== $oldPid) {
                if ($newPid > 0) {
                    if (str_contains(',' . (string) $row->getAttr('path') . ',' . $id . ',', ',' . $newPid . ',')) {
                        return $this->fail('不能移动到自己的子级下');
                    }
                    GoodsCategory::where('id', $newPid)->update(['has_child' => 1]);
                }
                // 旧父级若无其余子级则清 has_child
                if ($oldPid > 0) {
                    $rest = GoodsCategory::where('pid', $oldPid)->where('id', '<>', $id)->count();
                    if ($rest === 0) {
                        GoodsCategory::where('id', $oldPid)->update(['has_child' => 0]);
                    }
                }
                $parent = $newPid > 0 ? GoodsCategory::findOrEmpty($newPid) : null;
                $data['level'] = $parent !== null && !$parent->isEmpty() ? (int) $parent->getAttr('level') + 1 : 0;
                $data['path']  = $parent !== null && !$parent->isEmpty()
                    ? trim((string) $parent->getAttr('path') . ',' . $newPid, ',')
                    : '';
            }
        }
        $row->allowField($this->crudFields)->save(array_intersect_key($data, array_flip($this->crudFields)));
        return $this->ok($row, '保存成功');
    }

    /**
     * 删除（硬删）：有子级或挂有商品则拒绝
     */
    public function delete(int $id)
    {
        $row = GoodsCategory::findOrEmpty($id);
        if ($row->isEmpty()) {
            return $this->fail('分类不存在');
        }
        if ((int) $row->getAttr('has_child') === 1 || GoodsCategory::where('pid', $id)->count() > 0) {
            return $this->fail('请先删除子分类');
        }
        $goodsNum = Db::name('goods')->whereRaw("FIND_IN_SET({$id}, category_ids)")->where('delete_time', 0)->count();
        if ($goodsNum > 0) {
            return $this->fail('该分类下仍有商品，请先移除商品');
        }
        $pid = (int) $row->getAttr('pid');
        $row->delete();
        if ($pid > 0 && GoodsCategory::where('pid', $pid)->count() === 0) {
            GoodsCategory::where('id', $pid)->update(['has_child' => 0]);
        }
        return $this->ok([], '删除成功');
    }

    /**
     * 组树
     */
    protected function buildTree(array $rows): array
    {
        $items = [];
        foreach ($rows as $r) {
            $r['children'] = [];
            $items[(int) $r['id']] = $r;
        }
        $tree = [];
        foreach ($items as $id => &$node) {
            $pid = (int) $node['pid'];
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
