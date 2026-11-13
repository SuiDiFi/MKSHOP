<?php
// +----------------------------------------------------------------------
// | 售后/投诉工单（M7 对齐 KASUSHOU 投诉管理）
// | status：1=待处理 2=已处理 3=已撤销；supplier_type：1=自营 2=商家 3=渠道
// | 红线：处理售后触发退款走 OrderService::refund（余额原路退/卡密冻结/佣金回冲），不绕过资金链路
// +----------------------------------------------------------------------
declare (strict_types = 1);

namespace app\common\model;

class Complain extends BaseModel
{
    public const ST_WAIT    = 1;
    public const ST_DONE    = 2;
    public const ST_CANCELED = 3;

    protected $name = 'complain';

    /**
     * 来源文案（自营/商家/渠道）
     */
    public function getSupplierTypeTextAttr($value, $data): string
    {
        return [1 => '自营', 2 => '商家', 3 => '渠道'][(int) ($data['supplier_type'] ?? 0)] ?? '未知';
    }

    /**
     * 售后状态文案
     */
    public function getStatusTextAttr($value, $data): string
    {
        return [1 => '待处理', 2 => '已处理', 3 => '已撤销'][(int) ($data['status'] ?? 0)] ?? (string) ($data['status'] ?? '');
    }
}
