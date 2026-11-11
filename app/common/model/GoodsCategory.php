<?php
// +----------------------------------------------------------------------
// | 商品分类模型（多级树；表无 delete_time → 硬删）
// +----------------------------------------------------------------------
declare (strict_types = 1);

namespace app\common\model;

class GoodsCategory extends BaseModel
{
    protected $name = 'goods_category';

    /**
     * 子级冗余维护：写 has_child 由控制器处理，这里只做名称裁剪
     */
    public function setNameAttr($value): string
    {
        return mb_substr((string) $value, 0, 100, 'UTF-8');
    }
}
