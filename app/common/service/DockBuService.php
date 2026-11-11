<?php
// +----------------------------------------------------------------------
// | MKSHOP 渠道补单/同步任务（M3）
// | buOrders   渠道下单失败自动补单：status=1 渠道单 docking_info='' → 重新提交
// |            受 goods.docking_order_bu 开关 / order_bu_count 次数上限 / auto_order_time 间隔控制
// | syncDealing 处理中订单同步兜底：status=2 且已提交渠道单 → syncOrder（防回调丢失）
// | 入口：php think mks:dockbu（console command / sys_timer cron 均可触发）
// +----------------------------------------------------------------------
declare (strict_types = 1);

namespace app\common\service;

use app\common\model\Goods;
use app\common\model\GoodsStock;
use app\common\model\Order;
use think\facade\Db;
use think\facade\Log;

class DockBuService
{
    /**
     * 渠道下单失败自动补单
     * @return array{scanned:int, submitted:int, failed:int}
     */
    public static function buOrders(int $limit = 50): array
    {
        $stat = ['scanned' => 0, 'submitted' => 0, 'failed' => 0];
        // 待补单订单：渠道供货 + 已付款未提交成功 + 开启自动补单 + 未达次数上限
        $orderIds = Order::alias('o')
            ->join('goods g', 'g.id = o.goods_id')
            ->where('o.status', Order::ST_WAIT_DEAL)
            ->where('o.delete_time', 0)
            ->where('o.docking_info', '')
            ->where('g.provide_type', 6)
            ->where('g.docking_order_bu', 1)
            ->whereRaw('o.now_bu_count < g.order_bu_count')
            ->whereRaw('(g.auto_order_time <= 0 OR o.order_bu_time <= 0 OR o.order_bu_time + g.auto_order_time <= ?)', [time()])
            ->order('o.id asc')
            ->limit($limit)
            ->column('o.id');
        foreach ($orderIds as $id) {
            $stat['scanned']++;
            $order = Order::findOrEmpty((int) $id);
            if ($order->isEmpty()) {
                continue;
            }
            $goods = Goods::findOrEmpty((int) $order->getAttr('goods_id'));
            $stock = GoodsStock::where('goods_id', (int) $order->getAttr('goods_id'))->where('type', 2)->findOrEmpty();
            try {
                if ($goods->isEmpty() || $stock->isEmpty()) {
                    throw_fail('商品/渠道库存配置缺失', 1);
                }
                DockingV2Service::deliver($order, $stock);
                $stat['submitted']++;
                Order::where('id', (int) $id)->update(['order_bu_time' => time()]);
            } catch (\Throwable $e) {
                // 补单失败：计数 + 间隔；达上限自然停止（留人工处理）
                Db::name('order')->where('id', (int) $id)->inc('now_bu_count')->update([
                    'order_bu_time' => time(),
                    'update_time'   => time(),
                ]);
                $stat['failed']++;
                self::log('补单失败', (string) $id, $e->getMessage());
            }
        }
        return $stat;
    }

    /**
     * 处理中订单同步兜底（回调丢失时由轮询兜住）
     * @return array{scanned:int, changed:int, failed:int}
     */
    public static function syncDealing(int $limit = 100): array
    {
        $stat = ['scanned' => 0, 'changed' => 0, 'failed' => 0];
        $orderIds = Order::where('status', Order::ST_DEALING)
            ->where('delete_time', 0)
            ->where('docking_info', '<>', '')
            ->order('id asc')
            ->limit($limit)
            ->column('id');
        foreach ($orderIds as $id) {
            $stat['scanned']++;
            $order = Order::findOrEmpty((int) $id);
            if ($order->isEmpty()) {
                continue;
            }
            try {
                $result = DockingV2Service::syncOrder($order);
                if ($result['changed'] !== []) {
                    $stat['changed']++;
                    OrderService::log($order, '渠道同步：' . implode('；', $result['changed']));
                }
            } catch (\Throwable $e) {
                $stat['failed']++;
                self::log('同步失败', (string) $id, $e->getMessage());
            }
        }
        return $stat;
    }

    protected static function log(string $action, string $orderId, string $reason): void
    {
        $line = sprintf('[dockbu] %s order=%s reason=%s', $action, $orderId, mb_substr($reason, 0, 300));
        try {
            Log::warning($line);
        } catch (\Throwable $ignore) {
        }
    }
}
