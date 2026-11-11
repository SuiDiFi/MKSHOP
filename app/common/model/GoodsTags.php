<?php
// +----------------------------------------------------------------------
// | 品牌/商品属性标签模型（表无 delete_time → 硬删）
// +----------------------------------------------------------------------
declare (strict_types = 1);

namespace app\common\model;

class GoodsTags extends BaseModel
{
    protected $name = 'goods_tags';
}
