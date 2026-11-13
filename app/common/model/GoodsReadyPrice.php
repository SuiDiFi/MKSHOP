<?php
// +----------------------------------------------------------------------
// | 定价模板（M7 对齐 KASUSHOU pricereadydata）
// | 语义：按面值区间定义加价规则；price_arr 为可选的等级价格档 JSON {level:price}
// | 红线：模板仅存储；商品应用模板生成售价的链路后续接入，不影响现有取价
// +----------------------------------------------------------------------
declare (strict_types = 1);

namespace app\common\model;

class GoodsReadyPrice extends BaseModel
{
    protected $name = 'goods_ready_price';

    protected $type = [
        'price_arr' => 'array',
    ];
}
