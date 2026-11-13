<?php
// +----------------------------------------------------------------------
// | 商品下单输入项模板（M7 对齐 KASUSHOU goods_tpl）
// | inputs 为 JSON 数组：[{label:"卡号",placeholder:"请输入卡号",required:1}, ...]
// | 红线：模板删除为软删；商品引用的 tpl_id 由商品侧维护，此处不联动
// +----------------------------------------------------------------------
declare (strict_types = 1);

namespace app\common\model;

use think\model\concern\SoftDelete;

class GoodsTpl extends BaseModel
{
    use SoftDelete;

    protected $name = 'goods_tpl';

    protected $deleteTime = 'delete_time';
    protected $defaultSoftDelete = 0;

    protected $type = [
        'inputs' => 'array', // JSON 自动编解码
    ];

    /**
     * 输入项数量（列表展示用）
     */
    public function getInputCountAttr($value, $data): int
    {
        try {
            return is_array($data['inputs'] ?? null) ? count($data['inputs']) : 0;
        } catch (\Throwable $e) {
            return 0;
        }
    }
}
