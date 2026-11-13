<?php
// +----------------------------------------------------------------------
// | 后台 · 定价模板管理（M7 对齐 KASUSHOU pricereadydata 页面）
// | 列：ID/名称/类型/加价规则/权重；类型 1=按比例加价% 2=固定加价 3=固定售价
// +----------------------------------------------------------------------
declare (strict_types = 1);

namespace app\controller\admin;

use app\common\model\GoodsReadyPrice;
use mks\traits\CurdTrait;

class PriceReadyController extends Base
{
    use CurdTrait;

    protected string $crudModel = GoodsReadyPrice::class;

    protected array $crudSearchable = [
        'name'   => 'like',
        'type'   => 'eq',
        'status' => 'eq',
    ];

    protected array $crudModifiable = ['name', 'type', 'cha_price', 'start_price', 'end_price', 'price_arr', 'sort', 'status'];

    protected array $crudFields = ['name', 'type', 'cha_price', 'start_price', 'end_price', 'price_arr', 'sort', 'status', 'is_default'];

    protected string $crudOrder = 'sort desc, id desc';

    /**
     * 类型字典（对齐 KASUSHOU「类型」下拉）
     */
    public function type_list()
    {
        return $this->ok([
            ['code' => 1, 'label' => '按比例加价（%）'],
            ['code' => 2, 'label' => '固定加价（元）'],
            ['code' => 3, 'label' => '固定售价（元）'],
        ]);
    }

    /**
     * 新建/更新：price_arr 归一化为 JSON
     */
    public function create()
    {
        $data = $this->crudFormData();
        $data['price_arr'] = $this->normalizePriceArr($data['price_arr'] ?? null);
        return $this->crudCreate($data);
    }

    public function save(int $id)
    {
        $row = GoodsReadyPrice::findOrEmpty($id);
        if ($row->isEmpty()) {
            return $this->fail('定价模板不存在');
        }
        $data = $this->crudFormData();
        $data['price_arr'] = $this->normalizePriceArr($data['price_arr'] ?? null);
        $row->save($data);
        return $this->ok([], '保存成功');
    }

    /**
     * 归一化等级价格档：{level:price} 数值映射
     */
    protected function normalizePriceArr(mixed $arr): array
    {
        $in = is_array($arr) ? $arr : (json_decode((string) $arr, true) ?: []);
        $out = [];
        foreach ($in as $k => $v) {
            $level = (int) $k;
            if ($level <= 0 || !is_numeric($v)) {
                continue;
            }
            $out[(string) $level] = (string) $v;
        }
        return $out;
    }
}
