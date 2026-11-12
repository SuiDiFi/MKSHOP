<?php
// +----------------------------------------------------------------------
// | 供货商货款结算（M6）：goods.uid>0 的商品订单成功后按进价结算货款给供货商
// | 语义：supply_price = cost_value（发货时已按总成本重算）；冻结期后转可用余额
// | 红线：入账走 MemberSupplyService（同事务流水+幂等）
// +----------------------------------------------------------------------
declare (strict_types = 1);

namespace app\common\service;

use app\common\model\MemberSupply;
use app\common\model\Order;
use app\common\model\OrderSupply;
use mks\util\SysConfig;

class SupplyService
{
    /**
     * 订单成功结算供货商货款（先进冻结，到期解冻）
     */
    public static function settleIfSuccess(Order $order): void
    {
        $supplierId = (int) $order->getAttr('supplier_id');
        if ($supplierId <= 0) {
            $goodsId = (int) $order->getAttr('goods_id');
            $supplierId = $goodsId > 0 ? (int) \app\common\model\Goods::where('id', $goodsId)->value('uid') : 0;
        }
        if ($supplierId <= 0) {
            return; // 平台自营
        }
        if (OrderSupply::where('order_id', (int) $order->getAttr('id'))->count() > 0) {
            return; // 幂等
        }
        // order.cost_value 发货时已按库存行成本×数量重算（总成本）
        $supply = (string) $order->getAttr('cost_value');
        if (bccomp($supply, '0', 4) <= 0) {
            return;
        }
        $minutes = max(0, (int) SysConfig::get('order_supply_sure_time', 2880));
        $frozen  = $minutes > 0 ? time() + $minutes * 60 : 0;
        OrderSupply::create([
            'order_id'          => (int) $order->getAttr('id'),
            'supply_price'      => $supply,
            'back_supply_price' => '0.0000',
            'supply_id'         => $supplierId,
            'uid'               => (int) $order->getAttr('uid'),
            'status'            => 1,
            'sure_status'       => $frozen > 0 ? OrderSupply::SURE_WAIT : OrderSupply::SURE_SETTLED,
            'create_time'       => time(),
            'frozen_time'       => $frozen,
        ]);
        $ordersn = (string) $order->getAttr('ordersn');
        if ($frozen > 0) {
            MemberSupplyService::freeze(
                $supplierId,
                MemberSupply::T_ORDER_SUPPLY_FREEZE,
                $supply,
                (int) $order->getAttr('id'),
                '订单货款冻结 ' . $ordersn,
                'SUPPLYFREEZE:' . $ordersn
            );
        } else {
            MemberSupplyService::changeMoney(
                $supplierId,
                MemberSupply::T_ORDER_SUPPLY,
                MemberSupply::C_INCOME,
                $supply,
                (int) $order->getAttr('id'),
                '订单货款 ' . $ordersn,
                'SUPPLY:' . $ordersn
            );
        }
    }

    /**
     * 到期解冻货款（定时任务）
     * @return array{scanned:int, settled:int, failed:int}
     */
    public static function settleDue(int $limit = 200): array
    {
        $rows = OrderSupply::where('sure_status', OrderSupply::SURE_WAIT)
            ->where('frozen_time', '>', 0)
            ->where('frozen_time', '<=', time())
            ->order('id asc')
            ->limit($limit)
            ->select();
        $settled = 0;
        $failed  = 0;
        foreach ($rows as $row) {
            if (self::settleRow($row)) {
                $settled++;
            } else {
                $failed++;
            }
        }
        return ['scanned' => count($rows), 'settled' => $settled, 'failed' => $failed];
    }

    /**
     * 解冻单行（到期或后台手动）
     */
    public static function settleRow(OrderSupply $row): bool
    {
        if ((int) $row->getAttr('sure_status') !== OrderSupply::SURE_WAIT) {
            return false;
        }
        $total   = (string) $row->getAttr('supply_price');
        $backed  = (string) $row->getAttr('back_supply_price');
        $amount  = bcsub($total, $backed, 4);
        $uid     = (int) $row->getAttr('supply_id');
        $orderId = (int) $row->getAttr('order_id');
        $id      = (int) $row->getAttr('id');
        if (bccomp($amount, '0', 4) <= 0) {
            $row->save(['sure_status' => OrderSupply::SURE_REFUND]);
            return true;
        }
        if ($uid > 0) {
            $ordersn = (string) Order::where('id', $orderId)->value('ordersn');
            MemberSupplyService::unfreeze(
                $uid,
                $amount,
                $orderId,
                '订单货款解冻 ' . $ordersn,
                'SUPPLYUNFREEZE:' . $id,
                'SUPPLY:' . $id
            );
        }
        $row->save(['sure_status' => OrderSupply::SURE_SETTLED]);
        return true;
    }

    /**
     * 退款回冲供货货款（冻结中扣冻结，已结算扣可用）
     */
    public static function rollbackForRefund(int $orderId, string $backRate): void
    {
        $rows = OrderSupply::where('order_id', $orderId)
            ->whereIn('sure_status', [OrderSupply::SURE_WAIT, OrderSupply::SURE_SETTLED, OrderSupply::SURE_PART])
            ->select();
        $ordersn = (string) Order::where('id', $orderId)->value('ordersn');
        foreach ($rows as $row) {
            $total  = (string) $row->getAttr('supply_price');
            $backed = (string) $row->getAttr('back_supply_price');
            $remain = bcsub($total, $backed, 4);
            if (bccomp($remain, '0', 4) <= 0) {
                continue;
            }
            $back = bcmul($total, $backRate, 4);
            if (bccomp($back, $remain, 4) > 0) {
                $back = $remain;
            }
            if (bccomp($back, '0', 4) <= 0) {
                continue;
            }
            $uid = (int) $row->getAttr('supply_id');
            $key = 'SUPPLYBACK:' . $orderId . ':' . (int) $row->getAttr('id');
            if ($uid > 0) {
                if ((int) $row->getAttr('sure_status') === OrderSupply::SURE_WAIT) {
                    MemberSupplyService::deductFrozen(
                        $uid,
                        MemberSupply::T_SUPPLY_BACK,
                        $back,
                        $orderId,
                        '订单退款货款回冲 ' . $ordersn,
                        $key
                    );
                } else {
                    MemberSupplyService::changeMoney(
                        $uid,
                        MemberSupply::T_SUPPLY_BACK,
                        MemberSupply::C_PAY,
                        $back,
                        $orderId,
                        '订单退款货款回冲 ' . $ordersn,
                        $key
                    );
                }
            }
            $newBacked = bcadd($backed, $back, 4);
            $full      = bccomp($newBacked, $total, 4) >= 0;
            $row->save([
                'back_supply_price' => $newBacked,
                'sure_status'       => $full
                    ? OrderSupply::SURE_REFUND
                    : ((int) $row->getAttr('sure_status') === OrderSupply::SURE_WAIT ? OrderSupply::SURE_WAIT : OrderSupply::SURE_PART),
            ]);
        }
    }
}
