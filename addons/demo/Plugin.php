<?php
// +----------------------------------------------------------------------
// | 演示插件（M6-C 验收）：仅监听订单钩子写日志，不修改任何业务数据
// +----------------------------------------------------------------------
declare (strict_types = 1);

namespace addons\demo;

use think\facade\Log;

class Plugin
{
    public function onOrderPaid(array $payload): void
    {
        $this->write('order.paid', $payload);
    }

    public function onOrderSuccess(array $payload): void
    {
        $this->write('order.success', $payload);
    }

    public function onOrderRefund(array $payload): void
    {
        $this->write('order.refund', $payload);
    }

    /**
     * 写入演示日志（runtime 日志；不写数据库，卸载即清理）
     */
    protected function write(string $hook, array $payload): void
    {
        Log::info('[addon:demo] ' . $hook . ' ' . json_encode([
            'order_id'    => $payload['order_id'] ?? 0,
            'ordersn'     => $payload['ordersn'] ?? '',
            'uid'         => $payload['uid'] ?? 0,
            'total_price' => $payload['total_price'] ?? '0',
            'refund_money' => $payload['refund_money'] ?? null,
        ], JSON_UNESCAPED_UNICODE));
    }
}
