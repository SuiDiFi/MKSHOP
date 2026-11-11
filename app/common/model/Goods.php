<?php
// +----------------------------------------------------------------------
// | 商品模型（M1）
// +----------------------------------------------------------------------
declare (strict_types = 1);

namespace app\common\model;

use think\model\concern\SoftDelete;

class Goods extends BaseModel
{
    use SoftDelete;

    protected $name = 'goods';

    /** 软删字段（0=未删，红线：统一 delete_time） */
    protected $deleteTime = 'delete_time';
    protected $defaultSoftDelete = 0;

    protected $hidden = ['sync_config', 'set_config', 'json_config'];

    /**
     * 多分类冗余串归一（逗号分隔，去重去空）
     */
    public function setCategoryIdsAttr($value): string
    {
        $ids = is_array($value) ? $value : explode(',', (string) $value);
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), fn ($v) => $v > 0)));
        return implode(',', $ids);
    }
}
