<?php
// +----------------------------------------------------------------------
// | 后台 · 商品下单输入项模板（M7 对齐 KASUSHOU 商品模板页）
// +----------------------------------------------------------------------
declare (strict_types = 1);

namespace app\controller\admin;

use app\common\model\GoodsTpl;
use mks\traits\CurdTrait;

class GoodsTplController extends Base
{
    use CurdTrait;

    protected string $crudModel = GoodsTpl::class;

    protected array $crudSearchable = [
        'name'   => 'like',
        'status' => 'eq',
    ];

    protected array $crudModifiable = ['name', 'inputs', 'sort', 'status'];

    protected array $crudFields = ['name', 'inputs', 'sort', 'status'];

    protected string $crudOrder = 'sort desc, id desc';

    /**
     * 列表补充：输入项条数（前端展示）
     */
    public function index()
    {
        $result = $this->crudIndex();
        $data   = $result->getData();
        $list   = (array) ($data['data']['list'] ?? []);
        foreach ($list as &$row) {
            $arr = is_array($row['inputs'] ?? null) ? $row['inputs'] : json_decode((string) ($row['inputs'] ?? '[]'), true);
            $row['input_count']   = is_array($arr) ? count($arr) : 0;
            $row['input_labels']  = is_array($arr) ? implode('、', array_column($arr, 'label')) : '';
            if (is_array($arr)) {
                $row['inputs'] = json_encode($arr, JSON_UNESCAPED_UNICODE);
            }
        }
        unset($row);
        $data['data']['list'] = $list;
        return json_ok($data['data'], $data['msg']);
    }

    /**
     * 新建/更新：inputs 统一转 JSON 串入库
     */
    public function create()
    {
        $data = $this->crudFormData();
        $data['inputs'] = $this->normalizeInputs($data['inputs'] ?? null);
        return $this->crudCreate($data);
    }

    public function save(int $id)
    {
        $row = GoodsTpl::findOrEmpty($id);
        if ($row->isEmpty()) {
            return $this->fail('模板不存在');
        }
        $data = $this->crudFormData();
        $data['inputs'] = $this->normalizeInputs($data['inputs'] ?? null);
        $data['update_time'] = time();
        $row->save($data);
        return $this->ok([], '保存成功');
    }

    /**
     * 归一化输入项：接受数组或 JSON 串；剔除空 label 行
     * 返回数组（模型 type=array 负责编解码存储）
     */
    protected function normalizeInputs(mixed $inputs): array
    {
        $arr = is_array($inputs) ? $inputs : (json_decode((string) $inputs, true) ?: []);
        $out = [];
        foreach ($arr as $item) {
            $label = trim((string) ($item['label'] ?? ''));
            if ($label === '') {
                continue;
            }
            $out[] = [
                'label'       => mb_substr($label, 0, 50, 'UTF-8'),
                'placeholder' => mb_substr(trim((string) ($item['placeholder'] ?? '')), 0, 100, 'UTF-8'),
                'required'    => !empty($item['required']) ? 1 : 0,
            ];
        }
        return $out;
    }
}
