<?php
// +----------------------------------------------------------------------
// | 上游回调接收（M3：渠道订单异步通知 /api/v2/order/notify）
// | 协议（docs/kasushou-v2api/33-订单异步回调）：
// |   1. sign 验签串不含 sign/card_list/express_list
// |   2. sha1(time + json_encode(ksort(post), JSON_UNESCAPED_UNICODE) + apikey)
// |   3. 处理成功返回纯文本 ok，否则上游按 5|10|15|20|25 分钟阶梯重试最多 5 次
// | 定位订单：external_orderno = 我方下单时传的 ordersn；ordersn = 上游单号(=order.docking_info)
// +----------------------------------------------------------------------
declare (strict_types = 1);

namespace app\controller\api;

use app\common\model\Docking;
use app\common\model\Order;
use app\common\service\DockingV2Service;
use mks\BaseController;
use mks\util\Crypt;
use think\Response;

class Notify extends BaseController
{
    /**
     * 渠道订单异步回调入口
     */
    public function order(): Response
    {
        try {
            $this->handle();
        } catch (\Throwable $e) {
            // 任何失败返回非 ok，触发上游重试（原因记录到订单日志便于排查）
            return response('fail: ' . mb_substr($e->getMessage(), 0, 200));
        }
        return response('ok');
    }

    protected function handle(): void
    {
        $raw  = (string) $this->request->getContent();
        $post = json_decode($raw !== '' ? $raw : '{}', true);
        if (!is_array($post) || $post === []) {
            throw_fail('回调数据无效', 1);
        }
        $external = (string) ($post['external_orderno'] ?? ''); // 我方订单号
        $remoteSn = (string) ($post['ordersn'] ?? '');          // 上游单号
        $sign     = (string) ($post['sign'] ?? '');
        if ($external === '' || $sign === '') {
            throw_fail('回调参数缺失', 1);
        }
        $order = Order::where('ordersn', $external)->where('delete_time', 0)->findOrEmpty();
        if ($order->isEmpty() || (int) $order->getAttr('docking_id') <= 0) {
            throw_fail('订单不存在', 1);
        }
        $docking = Docking::findOrEmpty((int) $order->getAttr('docking_id'));
        if ($docking->isEmpty()) {
            throw_fail('渠道不存在', 1);
        }
        // 渠道单号对齐校验（已有渠道单时不接受不匹配的回调）
        $localDockingInfo = (string) $order->getAttr('docking_info');
        if ($localDockingInfo !== '' && $remoteSn !== $localDockingInfo) {
            throw_fail('渠道单号不匹配', 1);
        }
        // 官方 demo 验签：去 sign/card_list/express_list → ksort → sha1(time+json+apikey)
        $verify = $post;
        unset($verify['sign'], $verify['card_list'], $verify['express_list']);
        ksort($verify);
        $apikey = Crypt::decrypt((string) ($docking->getAttr('password') ?? ''));
        $json   = json_encode($verify, JSON_UNESCAPED_UNICODE);
        $expect = sha1((string) $verify['time'] . $json . $apikey);
        if (!hash_equals($expect, $sign)) {
            throw_fail('签名校验失败', 1);
        }
        // 推进订单（与轮询同步共用 applyRemote）
        $cards = isset($post['card_list']) && is_array($post['card_list']) ? $post['card_list'] : [];
        $result = DockingV2Service::applyRemote(
            $order,
            (int) ($post['status'] ?? 0),
            $cards,
            (string) ($post['recharge_hints'] ?? ''),
            (string) ($post['has_back_money'] ?? '0'),
            [
                'start_progress'   => (string) ($post['start_progress'] ?? ''),
                'current_progress' => (string) ($post['current_progress'] ?? ''),
                'target_progress'  => (string) ($post['target_progress'] ?? ''),
            ]
        );
        if ($result['changed'] !== []) {
            \app\common\service\OrderService::log($order, '渠道回调：' . implode('；', $result['changed']));
        }
    }
}
