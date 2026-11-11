<?php
// +----------------------------------------------------------------------
// | 商品库存/SKU 行（卡密库存桥：goods → goods_stock(type=1) → card_cate）
// +----------------------------------------------------------------------
declare (strict_types = 1);

namespace app\common\model;

class GoodsStock extends BaseModel
{
    protected $name = 'goods_stock';

    protected $updateTime = false; // 表无 update_time 字段

    public const TYPE_CARD    = 1; // 卡密库存
    public const TYPE_DOCKING = 2; // 对接渠道库存（M3）
}
