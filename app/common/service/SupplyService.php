<?php
// +----------------------------------------------------------------------
// | 供货商货款结算（M6）：goods.uid>0 的商品订单成功后按进价结算货款给供货商
// | 语义：supply_price = cost_value × quantity（平台赚差价；对齐 order_supply 表语义）
// | 红线：入账走 MemberSupplyService（同事务流水+幂等）
// +----------------------------------------------------------------------
declare (strict_types = 1);

namespace app\common\service;

use app\common\model\Goods;
use app\common\model\MemberSupply;
use app\common\model\Order;
use app\common\model\OrderSupply;

class SupplyService
{
    /**
     * 订单成功结算供货商货款（幂等）
     */
    public static function settleIfSuccess(Order $order): void
    {
        $goodsId = (int) $order->getAttr('goods_id');
        $supplierId = $goodsId > 0 ? (int) Goods::where('id', $goodsId)->value('uid') : 0;
        if ($supplierId <= 0) {
            return; // 平台自营
        }
        $exists = OrderSupply::where('order_id', (int) $order->getAttr('id'))->count();
        if ($exists > 0) {
            return; // 幂等
        }
        // order.cost_value 发货时已按库存行成本×数量重算（总成本）
        $supply = (string) $order->getAttr('cost_value');
        if (bccomp($supply, '0', 4) <= 0) {
            return;
        }
        OrderSupply::create([
            'order_id'     => (int) $order->getAttr('id'),
            'supply_price' => $supply,
            'back_supply_price' => '0.00',
            'supply_id'    => $supplierId,
            'uid'          => (int) $order->getAttr('uid'),
            'status'       => 1,
            'sure_status'  => 2, // 已结算（即时入账）
            'create_time'  => time(),
            'frozen_time'  => 0,
        ]);
        MemberSupplyService::changeMoney(
            $supplierId,
            MemberSupply::T_ORDER_SUPPLY,
            MemberSupply::C_INCOME,
            $supply,
            (int) $order->getAttr('id'),
            '订单货款 ' . (string) $order->getAttr('ordersn'),
            'SUPPLY:' . (string) $order->getAttr('ordersn')
        );
    }

    /**
     * 退款回冲供货货款（比例 backRate）
     */
    public static function rollbackForRefund(int $orderId, string $backRate): void
    {
        $rows = OrderSupply::where('order_id', $orderId)
            ->whereIn('sure_status', [1, 2, 4])
            ->select();
        foreach ($rows as $row) {
            $total  = (string) $row->getAttr('supply_price');
            $backed = (string) $row->getAttr('back_supply_price');
            $remain = bcsub($total, $backed, 4);
            if (bccomp($remain, '0', 4) <= 0) {
                continue;
            }
            $back = bccomp(bcmul($total, $backRate, 4), $remain, 4) > 0 ? $remain : bcmul($total, $backRate, 4);
            if (bccomp($back, '0', 4) <= 0) {
                continue;
            }
            $uid = (int) $row->getAttr('supply_id');
            if ($uid > 0) {
                MemberSupplyService::changeMoney(
                    $uid,
                    MemberSupply::T_SUPPLY_BACK,
                    MemberSupply::C_PAY,
                    $back,
                    $orderId,
                    '订单退款货款回冲 ' . (string) Order::where('id', $orderId)->value('ordersn'),
                    'SUPPLYBACK:' . $orderId . ':' . $row->getAttr('id')
                );
            }
            $newBacked = bcadd($backed, $back, 4);
            $row->save([
                'back_supply_price' => $newBacked,
                'sure_status'       => bccomp($newBacked, $total, 4) >= 0 ? 3 : 4,
            ]);
        }
    }
}
