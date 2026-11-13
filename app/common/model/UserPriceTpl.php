<?php
// +----------------------------------------------------------------------
// | 用户密价/限价/权限模板（M7 对齐 KASUSHOU 用户密价/限价/商品权限）
// | type：1=会员密价（按模板幅度取价） 2=销售限价（下单校验最低售价） 3=商品权限（指定用户可见指定商品）
// | 绑定关系：mks_user_price_tpl_user / mks_user_price_tpl_goods 全量替换
// | 红线：删除为软删，仅管理模板本身，不触碰资金链路
// +----------------------------------------------------------------------
declare (strict_types = 1);

namespace app\common\model;

use think\model\concern\SoftDelete;

class UserPriceTpl extends BaseModel
{
    use SoftDelete;

    public const TYPE_CLOSE_PRICE = 1; // 会员密价
    public const TYPE_LIMIT_PRICE = 2; // 销售限价
    public const TYPE_GOODS_RIGHT = 3; // 商品权限

    protected $name = 'user_price_tpl';

    protected $deleteTime = 'delete_time';
    protected $defaultSoftDelete = 0;

    /**
     * 类型文案
     */
    public function getTypeTextAttr($value, $data): string
    {
        return [1 => '会员密价', 2 => '销售限价', 3 => '商品权限'][(int) ($data['type'] ?? 0)] ?? '未知';
    }
}
