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
        'name'     => 'like',
        'type'     => 'eq',
        'status'   => 'eq',
        'goods_id' => 'eq',
    ];

    protected array $crudModifiable = ['name', 'type', 'cha_price', 'start_price', 'end_price', 'price_arr', 'sort', 'status', 'goods_id'];

    protected array $crudFields = ['name', 'type', 'cha_price', 'start_price', 'end_price', 'price_arr', 'sort', 'status', 'is_default', 'goods_id', 'vip_id'];

    protected string $crudOrder = 'sort desc, id desc';

    /**
     * 列表：补充绑定商品名称（goods_id=0 表示未绑定，不参与取价）
     */
    public function index()
    {
        $result = $this->crudIndex();
        $data   = $result->getData();
        $list   = (array) ($data['data']['list'] ?? []);
        $gids   = array_values(array_unique(array_filter(array_map('intval', array_column($list, 'goods_id')))));
        $names  = $gids === [] ? [] : \think\facade\Db::name('goods')->whereIn('id', $gids)->column('goods_name', 'id');
        foreach ($list as &$row) {
            $gid = (int) ($row['goods_id'] ?? 0);
            $row['goods_name'] = $gid > 0 ? (string) ($names[$gid] ?? '') : '';
        }
        unset($row);
        $data['data']['list'] = $list;
        return json_ok($data['data'], $data['msg']);
    }

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
        if (($err = $this->checkGoodsId($data)) !== null) {
            return $this->fail($err);
        }
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
        if (($err = $this->checkGoodsId($data)) !== null) {
            return $this->fail($err);
        }
        $data['price_arr'] = $this->normalizePriceArr($data['price_arr'] ?? null);
        $row->save($data);
        return $this->ok([], '保存成功');
    }

    /**
     * 绑定商品校验（goods_id=0 表示不绑定、不参与取价）
     */
    protected function checkGoodsId(array $data): ?string
    {
        $gid = (int) ($data['goods_id'] ?? 0);
        if ($gid > 0 && \app\common\model\Goods::findOrEmpty($gid)->isEmpty()) {
            return '绑定商品不存在';
        }
        return null;
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
