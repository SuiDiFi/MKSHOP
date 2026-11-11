<?php
// +----------------------------------------------------------------------
// | 后台 · 品牌/商品属性标签（对齐权限点 goods_tags/*）
// +----------------------------------------------------------------------
declare (strict_types = 1);

namespace app\controller\admin;

use app\common\model\GoodsTags;
use mks\traits\CurdTrait;

class GoodsTagsController extends Base
{
    use CurdTrait;

    protected string $crudModel = GoodsTags::class;

    protected array $crudSearchable = [
        'name'   => 'like',
        'status' => 'eq',
    ];

    protected array $crudModifiable = ['status', 'sort', 'name', 'color'];

    protected array $crudFields = ['name', 'short_name', 'sort', 'status', 'color'];

    protected string $crudOrder = 'sort desc, id asc';
}
