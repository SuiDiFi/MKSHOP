<?php
// +----------------------------------------------------------------------
// | 分站商品级定价模型（M6）：(vip_id, goods_id) 唯一
// | mode 1=比例加价 2=固定加价 3=固定售价
// +----------------------------------------------------------------------
declare (strict_types = 1);

namespace app\common\model;

class VipGoodsPrice extends BaseModel
{
    protected $name = 'vip_goods_price';

    public const MODE_RATE = 1;
    public const MODE_ADD  = 2;
    public const MODE_FIX  = 3;
}
