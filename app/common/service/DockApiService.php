<?php
// +----------------------------------------------------------------------
// | MKSHOP 对外供货 API（dockapi 供货商端；下游商户调用我们下单/查单）
// | 协议兼容 KASUSHOU 2.0 v2api：Header Sign/Timestamp/UserId，sha1(ts+body+apikey)
// | 凭据：member.income_app=appid(UserId)，member.income_key=apikey（AES-GCM 加密入库）
// | 红线：验签失败一律"签名错误"；income_order=1 才允许下单；IP 白名单 income_ip
// +----------------------------------------------------------------------
declare (strict_types = 1);

namespace app\common\service;

use app\common\model\Card;
use app\common\model\Goods;
use app\common\model\GoodsStock;
use app\common\model\Member;
use app\common\model\Order;
use mks\util\Crypt;
use mks\util\DockSign;
use think\Request;

class DockApiService
{
    /**
     * 鉴权：解析 Header → 定位会员 → 验签 → 权限校验 → 返回 Member
     * 失败统一抛 "签名错误"（不暴露具体原因，防探测）
     */
    public static function auth(Request $request): Member
    {
        $sign      = (string) $request->header('sign', '');
        $timestamp = (string) $request->header('timestamp', '');
        $userid    = (string) $request->header('userid', '');
        if ($sign === '' || $timestamp === '' || $userid === '') {
            throw_fail('签名错误', 1);
        }
        $member = Member::where('income_app', $userid)->where('delete_time', 0)->findOrEmpty();
        if ($member->isEmpty()) {
            throw_fail('签名错误', 1);
        }
        $apikey = $member->getIncomeKeyPlain();
        if ($apikey === '') {
            throw_fail('签名错误', 1);
        }
        $rawBody = (string) $request->getContent();
        if (!DockSign::verify($rawBody, $sign, $timestamp, $apikey)) {
            throw_fail('签名错误', 1);
        }
        // IP 白名单（income_ip 逗号分隔；已配则强制校验）
        $ipWhite = trim((string) $member->getAttr('income_ip'));
        if ($ipWhite !== '') {
            $ips = array_filter(array_map('trim', explode(',', $ipWhite)));
            if ($ips !== [] && !in_array((string) $request->ip(), $ips, true)) {
                throw_fail('IP 不在白名单', 1);
            }
        }
        return $member;
    }

    /**
     * 会员余额
     */
    public static function userMoney(Member $member): array
    {
        return ['money' => (string) $member->getAttr('balance')];
    }

    /**
     * 商品列表（仅上架；卡密商品带实时库存）
     */
    public static function goodsList(int $page = 1, int $limit = 20, int $cateId = 0, string $keyword = ''): array
    {
        $limit = min(100, max(1, $limit));
        $query = Goods::where('status', 1)->where('delete_time', 0);
        if ($cateId > 0) {
            $query = $query->whereRaw("FIND_IN_SET({$cateId}, category_ids)");
        }
        if ($keyword !== '') {
            $query = $query->where('goods_name', 'like', '%' . $keyword . '%');
        }
        $total = (clone $query)->count();
        $rows  = $query->order('sort desc, id desc')->page($page, $limit)
            ->field('id,goods_name,goods_img,goods_type,face_value,real_price,start_count,end_count,multiple,can_back,recharge_url')
            ->select()->toArray();
        foreach ($rows as &$r) {
            $r['stock'] = self::goodsStock((int) $r['id'], (int) $r['goods_type']);
        }
        return ['list' => $rows, 'count' => $total];
    }

    /**
     * 商品详情
     */
    public static function goodsDetail(int $goodsId): array
    {
        $goods = Goods::where('id', $goodsId)->where('status', 1)->where('delete_time', 0)->findOrEmpty();
        if ($goods->isEmpty()) {
            throw_fail('商品不存在或已下架', 1);
        }
        $data = $goods->visible([
            'id', 'goods_name', 'goods_img', 'goods_type', 'face_value', 'real_price',
            'start_count', 'end_count', 'multiple', 'can_back', 'recharge_url',
            'recharge_time_type', 'recharge_time_value', 'goods_info',
        ])->toArray();
        $data['stock'] = self::goodsStock($goodsId, (int) $goods->getAttr('goods_type'));
        return $data;
    }

    /**
     * 下游下单（余额支付闭环；卡密商品自动发货回传）
     * @return array{ordersn:string, status:int, total_price:string, card_list:array}
     */
    public static function buy(Member $member, array $params): array
    {
        if ((int) $member->getAttr('income_order') !== 1) {
            throw_fail('未开启 API 下单权限', 1);
        }
        $goodsId = (int) ($params['goods_id'] ?? 0);
        $goods   = Goods::where('id', $goodsId)->where('status', 1)->where('delete_time', 0)->findOrEmpty();
        if ($goods->isEmpty()) {
            throw_fail('商品不存在或已下架', 1);
        }
        $externalOrderno = (string) ($params['external_orderno'] ?? '');
        // 幂等：同一 external_orderno 不重复下单
        if ($externalOrderno !== '') {
            $exist = Order::where('uid', (int) $member->getAttr('id'))
                ->where('mark', 'DOCKAPI:' . $externalOrderno)
                ->where('delete_time', 0)->findOrEmpty();
            if (!$exist->isEmpty()) {
                return self::formatOrderReturn($exist);
            }
        }
        try {
            $order = OrderService::createOrder($member, $goods, [
                'quantity'         => (int) ($params['quantity'] ?? 1),
                'recharge_account' => (string) ($params['recharge_account'] ?? ''),
                'mark'             => $externalOrderno !== '' ? 'DOCKAPI:' . $externalOrderno : ('DOCKAPI:' . date('YmdHis')),
                'user_where'       => 'api',
                'ip'               => (string) ($params['_ip'] ?? ''),
                'buy_host'         => (string) ($params['_host'] ?? ''),
            ]);
            $payOrder = OrderService::createPayOrder($order, 'balance');
            OrderService::payByBalance($payOrder, $member);
        } catch (\mks\exception\BizException $e) {
            throw $e;
        }
        $fresh = Order::findOrEmpty((int) $order->getAttr('id'));
        return self::formatOrderReturn($fresh);
    }

    /**
     * 查单（按 ordersn 或 external_orderno）
     */
    public static function queryOrder(Member $member, string $ordersn = '', string $externalOrderno = ''): array
    {
        $query = Order::where('uid', (int) $member->getAttr('id'))->where('delete_time', 0);
        if ($ordersn !== '') {
            $query = $query->where('ordersn', $ordersn);
        } elseif ($externalOrderno !== '') {
            $query = $query->where('mark', 'DOCKAPI:' . $externalOrderno);
        } else {
            throw_fail('请提供订单号', 1);
        }
        $order = $query->findOrEmpty();
        if ($order->isEmpty()) {
            throw_fail('订单不存在', 1);
        }
        return self::formatOrderReturn($order);
    }

    /**
     * 格式化 dockapi 订单返回（卡密商品成功时回传 card_list）
     */
    protected static function formatOrderReturn(Order $order): array
    {
        $data = [
            'ordersn'           => (string) $order->getAttr('ordersn'),
            'external_orderno'  => (string) $order->getAttr('mark'),
            'status'            => (int) $order->getAttr('status'),
            'goods_name'        => (string) $order->getAttr('goods_name'),
            'quantity'          => (int) $order->getAttr('quantity'),
            'total_price'       => (string) $order->getAttr('total_price'),
            'recharge_account'  => (string) $order->getAttr('recharge_account'),
            'recharge_hints'    => (string) $order->getAttr('recharge_hints'),
            'card_list'         => [],
        ];
        if ((int) $order->getAttr('status') === Order::ST_SUCCESS) {
            $cards = Card::where('order_id', (int) $order->getAttr('id'))
                ->field('card_no,card_password')->select()->toArray();
            foreach ($cards as $c) {
                $data['card_list'][] = [
                    'card_no'       => $c['card_no'] !== '' ? Crypt::decrypt((string) $c['card_no']) : '',
                    'card_password' => $c['card_password'] !== '' ? Crypt::decrypt((string) $c['card_password']) : '',
                ];
            }
        }
        return $data;
    }

    /**
     * 商品库存（卡密=card_cate 余量；渠道=对接侧库存；其他=0）
     */
    protected static function goodsStock(int $goodsId, int $goodsType): int
    {
        if ($goodsType !== 1) {
            return 0;
        }
        $stock = GoodsStock::where('goods_id', $goodsId)->where('type', 1)->findOrEmpty();
        if ($stock->isEmpty()) {
            return 0;
        }
        $cate = \app\common\model\CardCate::findOrEmpty((int) $stock->getAttr('card_cate_id'));
        return $cate->isEmpty() ? 0 : (int) $cate->getAttr('card_num');
    }
}
