<?php
// +----------------------------------------------------------------------
// | MKSHOP 通用 CRUD Trait（对齐原系统 CurdTrait 端点族）
// 端点：index(分页列表) read(详情) list(全量/下拉) create(新建表单数据) save(更新)
//       delete(删除) modify(快捷改值)
// 安全红线：查询字段白名单 + 操作符白名单 + 写入字段白名单，杜绝任意列读写
// +----------------------------------------------------------------------
declare (strict_types = 1);

namespace mks\traits;

use think\Model;

/**
 * 通用 CRUD Trait（对齐原系统 CurdTrait 端点族）
 * 端点：index(分页列表) read(详情) list(全量/下拉) create(新建表单数据) save(更新)
 *       delete(删除) modify(快捷改值)
 * 安全红线：查询字段白名单 + 操作符白名单 + 写入字段白名单，杜绝任意列读写
 *
 * 使用方（控制器）须自行声明以下属性（不可在 Trait 内声明，避免组合冲突）：
 * @property string $crudModel      模型类名，如 Admin::class
 * @property array  $crudSearchable 可查询字段白名单 [field => 默认op]
 * @property array  $crudModifiable 可快捷修改字段白名单
 * @property array  $crudFields     创建/更新允许写入的字段白名单（空=不过滤）
 * @property string $crudOrder      默认排序
 * @property array  $crudOrderable  允许的排序字段
 */
trait CurdTrait
{
    protected function crudModel(): Model
    {
        if ($this->crudModel === '' || !class_exists($this->crudModel)) {
            throw_fail('控制器未配置crudModel', 500);
        }
        /** @var Model $m */
        $m = new $this->crudModel();
        return $m;
    }

    /**
     * 构造查询（where DSL + 排序）
     * where 参数：JSON 数组 [{"field":"username","op":"like","value":"a"}]
     */
    protected function crudQuery(Model $model)
    {
        $query = $model->db();
        $where = $this->request->param('where');
        $rows  = is_string($where) ? (json_decode($where, true) ?: []) : (array) ($where ?: []);
        foreach ($rows as $w) {
            $field = (string) ($w['field'] ?? '');
            $op    = strtolower((string) ($w['op'] ?? ''));
            if ($field === '' || !array_key_exists($field, $this->crudSearchable)) {
                continue; // 白名单外字段直接丢弃
            }
            $op  = $op !== '' && in_array($op, ['eq', 'neq', 'gt', 'lt', 'ge', 'le', 'like', 'in', 'notin', 'between', 'null', 'notnull'], true)
                ? $op : (string) $this->crudSearchable[$field];
            $val = $w['value'] ?? null;
            $query = match ($op) {
                'eq'      => $query->where($field, $val),
                'neq'     => $query->where($field, '<>', $val),
                'gt'      => $query->where($field, '>', $val),
                'lt'      => $query->where($field, '<', $val),
                'ge'      => $query->where($field, '>=', $val),
                'le'      => $query->where($field, '<=', $val),
                'like'    => $query->whereLike($field, '%' . (string) $val . '%'),
                'in'      => $query->whereIn($field, array_map('strval', (array) $val)),
                'notin'   => $query->whereNotIn($field, array_map('strval', (array) $val)),
                'between' => $query->whereBetween($field, [(string) ($val[0] ?? ''), (string) ($val[1] ?? '')]),
                'null'    => $query->whereNull($field),
                'notnull' => $query->whereNotNull($field),
                default   => $query,
            };
        }

        // 排序（白名单）
        $orderField = (string) $this->request->param('orderField', '');
        $orderBy    = strtolower((string) $this->request->param('orderBy', 'desc')) === 'asc' ? 'asc' : 'desc';
        if ($orderField !== '' && in_array($orderField, $this->crudOrderable, true)) {
            $query = $query->order($orderField, $orderBy);
        } else {
            foreach (array_filter(explode(',', $this->crudOrder)) as $o) {
                $query = $query->orderRaw(trim($o));
            }
        }
        return $query;
    }

    /**
     * 分页列表
     */
    public function index()
    {
        $page  = max(1, (int) $this->request->param('page', 1));
        $limit = min(200, max(1, (int) $this->request->param('limit', 15)));
        $query = $this->crudQuery($this->crudModel());
        $total = (clone $query)->count();
        $list  = $query->page($page, $limit)->select();
        return $this->ok(['list' => $list, 'count' => $total]);
    }

    /**
     * 全量列表（下拉选择用，强制 limit 500）
     */
    public function list()
    {
        $list = $this->crudQuery($this->crudModel())->limit(500)->select();
        return $this->ok($list);
    }

    /**
     * 详情
     */
    public function read(int $id)
    {
        $row = $this->crudModel()->findOrEmpty($id);
        if ($row->isEmpty()) {
            return $this->fail('数据不存在');
        }
        return $this->ok($row);
    }

    /**
     * 新建（表单数据放 form 参数或直接平铺）
     */
    public function create()
    {
        $data = $this->crudFormData();
        if ($data === []) {
            return $this->fail('没有需要保存的数据');
        }
        $model = $this->crudModel();
        $row   = $model->allowField($this->crudFields ?: true)->save($data);
        return $this->ok($row, '创建成功');
    }

    /**
     * 更新
     */
    public function save(int $id)
    {
        $row = $this->crudModel()->findOrEmpty($id);
        if ($row->isEmpty()) {
            return $this->fail('数据不存在');
        }
        $data = $this->crudFormData();
        if ($data === []) {
            return $this->fail('没有需要保存的数据');
        }
        $row->allowField($this->crudFields ?: true)->save($data);
        return $this->ok($row, '保存成功');
    }

    /**
     * 删除（模型启用 SoftDelete 即软删）
     */
    public function delete(int $id)
    {
        $row = $this->crudModel()->findOrEmpty($id);
        if ($row->isEmpty()) {
            return $this->fail('数据不存在');
        }
        $row->delete();
        return $this->ok([], '删除成功');
    }

    /**
     * 快捷改值（状态/排序开关等）
     */
    public function modify(int $id)
    {
        $field = (string) $this->request->param('field', '');
        if ($field === '' || !in_array($field, $this->crudModifiable, true)) {
            return $this->fail('字段不可修改');
        }
        $value = $this->request->param('value');
        $row   = $this->crudModel()->findOrEmpty($id);
        if ($row->isEmpty()) {
            return $this->fail('数据不存在');
        }
        $row->force()->save([$field => $value]);
        return $this->ok([], '修改成功');
    }

    /**
     * 收集表单数据（form 平铺参数，按 crudFields 过滤）
     */
    protected function crudFormData(): array
    {
        $form = (array) ($this->request->param('form') ?: []);
        if ($form === []) {
            $all   = $this->request->param();
            $form  = array_diff_key($all, array_flip(['where', 'page', 'limit', 'orderField', 'orderBy', 'form']));
        }
        if ($this->crudFields !== []) {
            $form = array_intersect_key($form, array_flip($this->crudFields));
        }
        // 剔除空串主键与空数据
        unset($form['id']);
        return array_filter($form, fn ($v) => $v !== null);
    }
}
