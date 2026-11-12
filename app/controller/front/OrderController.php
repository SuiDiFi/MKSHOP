<?php
// +----------------------------------------------------------------------
// | 前台 · 订单（M2：下单/余额支付/我的订单/查单提卡）
// +----------------------------------------------------------------------
declare (strict_types = 1);

namespace app\controller\front;

use app\common\model\Card;
use app\common\model\Goods;
use app\common\model\Member;
use app\common\model\Order;
use app\common\model\RechargeLog;
use app\common\service\OrderService;
use mks\BaseController;

class OrderController extends BaseController
{
    /**
     * 下单（登录态，余额支付闭环；在线支付通道 M2c/M3 接入）
     * @param {goods_id, quantity, recharge_account?, pay_type, mark?}
     */
    public function buy()
    {
        $member = Member::findOrEmpty((int) ($this->request->memberId ?? 0));
        if ($member->isEmpty()) {
            return $this->fail('请先登录');
        }
        $goodsId = (int) $this->request->param('goods_id', 0);
        $goods   = Goods::where('id', $goodsId)->where('status', 1)->where('delete_time', 0)->findOrEmpty();
        if ($goods->isEmpty()) {
            return $this->fail('商品不存在或已下架');
        }
        // 创建订单（分站上下文：vipSite 由中间件按 Host 注入）
        try {
            $order = OrderService::createOrder($member, $goods, [
                'quantity'         => (int) $this->request->param('quantity', 1),
                'recharge_account' => (string) $this->request->param('recharge_account', ''),
                'mark'             => (string) $this->request->param('mark', ''),
                'user_where'       => 'pc',
                'ip'               => (string) $this->request->ip(),
                'buy_host'         => (string) $this->request->host(),
                'vip_site'         => $this->request->vipSite ?? null,
            ]);
            // 支付单
            $payType  = (string) $this->request->param('pay_type', 'balance');
            $payOrder = OrderService::createPayOrder($order, $payType);
        } catch (\mks\exception\BizException $e) {
            return $this->fail($e->getMessage());
        }
        // 余额支付闭环
        if ($payType === 'balance') {
            try {
                OrderService::payByBalance($payOrder, $member);
            } catch (\Throwable $e) {
                // 余额不足等：返回可重试支付单
                $fresh = Order::findOrEmpty((int) $order->getAttr('id'));
                return $this->fail($e->getMessage(), 1, [
                    'ordersn' => (string) $order->getAttr('ordersn'),
                    'pay_sn'  => (string) $payOrder->getAttr('order_sn'),
                    'status'  => (int) $fresh->getAttr('status'),
                ]);
            }
        }
        $fresh = Order::findOrEmpty((int) $order->getAttr('id'));
        return $this->ok([
            'ordersn'  => (string) $fresh->getAttr('ordersn'),
            'status'   => (int) $fresh->getAttr('status'),
            'pay_sn'   => (string) $payOrder->getAttr('order_sn'),
            'total'    => (string) $fresh->getAttr('total_price'),
        ], '下单成功');
    }

    /**
     * 我的订单列表（登录态）
     */
    public function index()
    {
        $page  = max(1, (int) $this->request->param('page', 1));
        $limit = min(50, max(1, (int) $this->request->param('limit', 10)));
        $query = Order::where('uid', (int) ($this->request->memberId ?? 0))->where('delete_time', 0);
        $status = $this->request->param('status');
        if ($status !== null && $status !== '') {
            $query = $query->where('status', (int) $status);
        }
        $total = (clone $query)->count();
        $list  = $query->order('id desc')->page($page, $limit)
            ->field('id,ordersn,status,goods_id,goods_name,goods_img,goods_type,quantity,price,total_price,pay_type,pay_time,create_time,finish_time,recharge_account')
            ->select()
            ->toArray();
        return $this->ok(['list' => $list, 'count' => $total]);
    }

    /**
     * 查单/提卡（登录态 + 订单号；交易成功返回卡密明细）
     */
    public function query()
    {
        $ordersn = (string) $this->request->param('ordersn', '');
        if ($ordersn === '') {
            return $this->fail('请提供订单号');
        }
        $order = Order::where('ordersn', $ordersn)
            ->where('uid', (int) ($this->request->memberId ?? 0))
            ->where('delete_time', 0)
            ->findOrEmpty();
        if ($order->isEmpty()) {
            return $this->fail('订单不存在');
        }
        $data = $order->visible(['ordersn', 'status', 'goods_name', 'quantity', 'total_price', 'pay_type', 'pay_time', 'create_time', 'finish_time', 'recharge_account', 'recharge_url', 'recharge_hints'])->toArray();
        $data['cards'] = [];
        if ((int) $order->getAttr('status') === Order::ST_SUCCESS) {
            $data['cards'] = Card::where('order_id', (int) $order->getAttr('id'))
                ->field('id,card_no,card_password,card_show_type')
                ->select()
                ->toArray();
        }
        return $this->ok($data);
    }

    /**
     * 继续支付（待支付订单；余额）
     */
    public function pay()
    {
        $member = Member::findOrEmpty((int) ($this->request->memberId ?? 0));
        $ordersn = (string) $this->request->param('ordersn', '');
        $order = Order::where('ordersn', $ordersn)
            ->where('uid', (int) $member->getAttr('id'))
            ->where('delete_time', 0)
            ->findOrEmpty();
        if ($order->isEmpty()) {
            return $this->fail('订单不存在');
        }
        if ((int) $order->getAttr('status') !== Order::ST_WAIT_PAY) {
            return $this->fail('订单状态不可支付');
        }
        $payOrder = RechargeLog::where('id', (int) $order->getAttr('recharge_log_id'))->findOrEmpty();
        if ($payOrder->isEmpty()) {
            return $this->fail('支付单不存在');
        }
        OrderService::payByBalance($payOrder, $member);
        $fresh = Order::findOrEmpty((int) $order->getAttr('id'));
        return $this->ok(['ordersn' => (string) $fresh->getAttr('ordersn'), 'status' => (int) $fresh->getAttr('status')], '支付成功');
    }
}
