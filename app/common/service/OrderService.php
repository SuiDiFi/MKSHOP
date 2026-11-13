<?php
// +----------------------------------------------------------------------
// | MKSHOP 订单服务（M2 核心闭环）
// 流转：createOrder(0 等待支付) → payByBalance → paySuccessOrder(乐观并发 0/-1→1)
//       → deliver(本地卡密 FIFO 发货 → 3 交易成功；无卡 → 1 等待人工)
// 售后：refund(3→5：卡冻结+余额原路退) dualOrder(人工处理)
// 红线：资金变动必须走 MemberMoneyService（同事务流水）；订单状态推进必须乐观并发（幂等）
// +----------------------------------------------------------------------
declare (strict_types = 1);

namespace app\common\service;

use app\common\model\Card;
use app\common\model\CardCate;
use app\common\model\Goods;
use app\common\model\GoodsStock;
use app\common\model\Member;
use app\common\model\MemberLevel;
use app\common\model\MemberMoney;
use app\common\model\Order;
use app\common\model\OrderDolog;
use app\common\model\OrderLog;
use app\common\model\OrderRefund;
use app\common\model\RechargeLog;
use mks\util\Hook;
use think\facade\Cache;
use think\facade\Db;

class OrderService
{
    /**
     * 订单号（对齐原系统端前缀规则：D=pc M=h5 API=接口）
     */
    public static function buildOrderSn(string $prefix = 'D'): string
    {
        return $prefix . date('YmdHis') . str_pad((string) mt_rand(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    /**
     * 支付单号
     */
    public static function buildPaySn(): string
    {
        return 'R' . date('YmdHis') . str_pad((string) mt_rand(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    /**
     * 创建订单（status=0 等待支付；不扣库存，发货时才扣卡——对齐原系统）
     * @param array $input{quantity, recharge_account, mark, user_where}
     */
    public static function createOrder(?Member $member, Goods $goods, array $input): Order
    {
        $quantity = max(1, (int) ($input['quantity'] ?? 1));
        if ((int) $goods->getAttr('status') !== 1) {
            throw_fail('商品已暂停或禁售', 1);
        }
        if ((int) $goods->getAttr('goods_type') === 3) {
            throw_fail('实物商品暂不支持在线下单', 1);
        }
        // 数量规则
        $start = max(1, (int) $goods->getAttr('start_count'));
        $end   = max(1, (int) $goods->getAttr('end_count'));
        if ($quantity < $start || $quantity > $end) {
            throw_fail("购买数量需在 {$start}~{$end} 之间", 1);
        }
        $multiple = (int) $goods->getAttr('multiple');
        if ($multiple > 0 && $quantity % $multiple !== 0) {
            throw_fail("购买数量必须为 {$multiple} 的整数倍", 1);
        }
        // 库存预检（卡密商品：stock(type=1) → card_cate 余量）
        $stock = self::cardStockOf($goods);
        if ((int) $goods->getAttr('goods_type') === 1) {
            if ($stock === null) {
                throw_fail('商品未配置卡密库存', 1);
            }
            $cate = CardCate::findOrEmpty((int) $stock->getAttr('card_cate_id'));
            if ($cate->isEmpty() || (int) $cate->getAttr('card_num') < $quantity) {
                throw_fail('库存不足', 1);
            }
        }

        $quantity2 = $quantity;
        $basePrice = (string) $goods->getAttr('real_price');
        $price = $basePrice;
        $priceTplContent = null; // 用户密价模板快照（M7-P2）
        // 分站上下文（M6）：三级定价（商品级覆盖 > 分站比例 > 基准价）；站点内不叠加会员折扣/密价
        $vipSite = $input['vip_site'] ?? null;
        $vipFields = [
            'vip_id'          => 0,
            'vip_uid'         => 0,
            'vip_goods_price' => '0.0000',
            'vip_add_price'   => '0.0000',
            'vip_set_content' => null,
        ];
        if ($vipSite instanceof \app\common\model\VipSite) {
            $vp = VipPriceService::resolve($goods, $vipSite);
            if (!$vp['visible']) {
                throw_fail('商品已下架或不在当前分站销售', 1);
            }
            $basePrice = $vp['base'];
            $price     = $vp['price'];
            $vipFields = [
                'vip_id'          => (int) $vipSite->getAttr('id'),
                'vip_uid'         => (int) $vipSite->getAttr('uid'),
                'vip_goods_price' => $price,
                'vip_add_price'   => $vp['add'],
                'vip_set_content' => json_encode([
                    'mode'       => $vp['mode'],
                    'value'      => $vp['value'],
                    'base_price' => $basePrice,
                    'rate'       => (string) $vipSite->getAttr('rate'),
                ], JSON_UNESCAPED_UNICODE),
            ];
        } elseif ($member !== null) {
            // 用户密价模板（M7-P2）：命中即用模板价，不叠加会员等级折扣（避免双重让价）
            $close = UserPriceService::closePrice($goods, (int) $member->getAttr('id'));
            if ($close !== null) {
                $price = $close['price'];
                $priceTplContent = json_encode([
                    'source' => 'close_tpl',
                    'tpl_id' => $close['tpl_id'],
                    'mode'   => $close['mode'],
                    'value'  => $close['value'],
                    'base'   => $close['base'],
                ], JSON_UNESCAPED_UNICODE);
            } elseif ((int) $member->getAttr('level_id') > 0) {
                // 会员折扣价（M4）：member_level.pay_rate 语义 0=无折扣；0~100 为折后百分比（95 = 95 折）
                $payRate = (string) MemberLevel::where('id', (int) $member->getAttr('level_id'))->value('pay_rate');
                if (bccomp($payRate, '0', 2) > 0 && bccomp($payRate, '100', 2) < 0) {
                    $price = bcdiv(bcmul($price, $payRate, 6), '100', 4);
                }
            }
        }
        // 统一校验（服务层唯一入口）：商品权限白名单 + 最低售价（限价）
        UserPriceService::assertOrderAllowed($goods, $member, $price);
        $total = bcmul($price, (string) $quantity2, 4);
        // 供货商快照（M6）
        $supplierId   = (int) $goods->getAttr('uid');
        $supplierName = $supplierId > 0
            ? (string) Member::where('id', $supplierId)->value('nickname')
            : '';

        $order = new Order();
        $order->save(array_merge([
            'status'              => Order::ST_WAIT_PAY,
            'ordersn'             => self::buildOrderSn($input['user_where'] === 'h5' ? 'M' : 'D'),
            'uid'                 => $member ? (int) $member->getAttr('id') : 0,
            'nickname'            => $member ? (string) $member->getAttr('nickname') : '',
            'mobile'              => $member ? (string) $member->getAttr('mobile') : '',
            'supplier_id'         => $supplierId,
            'supplier_name'       => $supplierName,
            'user_where'          => (string) ($input['user_where'] ?? 'pc'),
            'goods_id'            => (int) $goods->getAttr('id'),
            'goods_name'          => (string) $goods->getAttr('goods_name'),
            'goods_img'           => (string) $goods->getAttr('goods_img'),
            'goods_type'          => (int) $goods->getAttr('goods_type'),
            'provide_type'        => (int) $goods->getAttr('provide_type'),
            'face_value'          => (string) $goods->getAttr('face_value'),
            'cost_value'          => (string) $goods->getAttr('cost_value'),
            'real_price'          => $price,
            'price'               => $price,
            'quantity'            => $quantity2,
            'total_price'         => $total,
            'order_price'         => bcsub($total, bcmul((string) $goods->getAttr('cost_value'), (string) $quantity2, 5), 4),
            'recharge_account'    => (string) ($input['recharge_account'] ?? ''),
            'recharge_url'        => (string) $goods->getAttr('recharge_url'),
            'recharge_time_type'  => (int) $goods->getAttr('recharge_time_type'),
            'recharge_time_value' => (int) $goods->getAttr('recharge_time_value'),
            'price_set_content'   => $priceTplContent,
            'mark'                => (string) ($input['mark'] ?? ''),
            'can_back'            => (int) $goods->getAttr('can_back'),
            'create_time'         => time(),
            'ip'                  => (string) ($input['ip'] ?? ''),
            'buy_host'            => (string) ($input['buy_host'] ?? ''),
        ], $vipFields));
        return $order;
    }

    /**
     * 生成支付单（recharge_log type=2 下单；一张支付单对一单，购物车聚合留 M5）
     */
    public static function createPayOrder(Order $order, string $gateway): RechargeLog
    {
        $payOrder = new RechargeLog();
        $payOrder->save([
            'status'          => RechargeLog::ST_WAIT,
            'order_sn'        => self::buildPaySn(),
            'title'           => mb_substr((string) $order->getAttr('goods_name'), 0, 250, 'UTF-8'),
            'uid'             => (int) $order->getAttr('uid'),
            'uname'           => (string) $order->getAttr('nickname'),
            'type'            => RechargeLog::T_ORDER,
            'payment_type'    => $gateway === 'balance' ? 0 : 1,
            'payment_gateway' => $gateway,
            'price'           => (string) $order->getAttr('total_price'),
            'total_price'     => (string) $order->getAttr('total_price'),
            'service_price'   => '0',
            'order_id'        => (int) $order->getAttr('id'),
            'create_time'     => time(),
        ]);
        $order->save(['recharge_log_id' => (int) $payOrder->getAttr('id')]);
        return $payOrder;
    }

    /**
     * 余额支付（幂等：redis 并发锁 + 幂等流水 + 订单乐观更新）
     */
    public static function payByBalance(RechargeLog $payOrder, Member $member): void
    {
        if ((int) $payOrder->getAttr('status') === RechargeLog::ST_PAID) {
            return; // 已支付，幂等
        }
        if ((int) $payOrder->getAttr('type') !== RechargeLog::T_ORDER) {
            throw_fail('支付单类型错误', 1);
        }
        if ((int) $payOrder->getAttr('uid') > 0 && (int) $payOrder->getAttr('uid') !== (int) $member->getAttr('id')) {
            throw_fail('无权支付该订单', 1);
        }
        $orderSn = (string) $payOrder->getAttr('order_sn');
        $lockKey = 'pay:balance:' . $orderSn;
        $redis   = Cache::store('redis')->handler();
        if (!$redis->set($lockKey, 1, ['nx', 'ex' => 60])) {
            throw_fail('正在支付，请勿重复操作', 1);
        }
        try {
            $total = (string) $payOrder->getAttr('total_price');
            // 资金扣款（幂等流水键）
            MemberMoneyService::changeMoney(
                (int) $member->getAttr('id'),
                MemberMoney::T_ORDER_PAY,
                MemberMoney::C_PAY,
                $total,
                (int) $payOrder->getAttr('order_id'),
                '下单扣款 ' . (string) $payOrder->getAttr('title'),
                'PAY:' . $orderSn
            );
            // 支付单乐观置 1（0 行=已处理）
            $affected = RechargeLog::where('id', (int) $payOrder->getAttr('id'))
                ->where('status', RechargeLog::ST_WAIT)
                ->update([
                    'status'          => RechargeLog::ST_PAID,
                    'payment_gateway' => 'balance',
                    'payment_time'    => time(),
                    'update_time'     => time(),
                ]);
            if ($affected === 0) {
                return; // 并发重复支付，幂等返回
            }
            self::paySuccessOrder($payOrder);
        } finally {
            $redis->del($lockKey);
        }
    }

    /**
     * 支付成功处理：订单 0/-1 → 1（等待处理），随后尝试自动发货
     */
    public static function paySuccessOrder(RechargeLog $payOrder): void
    {
        $order = Order::findOrEmpty((int) $payOrder->getAttr('order_id'));
        if ($order->isEmpty()) {
            throw_fail('支付单未关联有效订单', 1);
        }
        $affected = Order::where('id', (int) $order->getAttr('id'))
            ->whereIn('status', [Order::ST_WAIT_PAY, Order::ST_CANCEL_PAY])
            ->update([
                'status'     => Order::ST_WAIT_DEAL,
                'pay_time'   => time(),
                'pay_type'   => (string) $payOrder->getAttr('payment_gateway'),
                'update_time' => time(),
            ]);
        if ($affected === 0) {
            return; // 已被处理（幂等）
        }
        // 累计消费
        if ((int) $order->getAttr('uid') > 0) {
            Db::name('member')->where('id', (int) $order->getAttr('uid'))->inc('cost', (float) $order->getAttr('total_price'))->update();
        }
        self::log($order, '订单支付成功（' . (string) $payOrder->getAttr('payment_gateway') . '）');
        // 插件钩子：支付成功（异常不阻断主流程）
        Hook::fire('order.paid', self::payload($order, ['pay_type' => (string) $payOrder->getAttr('payment_gateway')]));
        // 自动发货（失败不回滚支付，订单停留 status=1 等人工）
        try {
            self::deliver(Order::findOrEmpty((int) $order->getAttr('id')));
        } catch (\Throwable $e) {
            self::log($order, '自动发货异常：' . $e->getMessage());
        }
    }

    /**
     * 自动发货调度：stock.type=1 本地卡密 FIFO / type=2 渠道下单（对齐原系统 doJob 选择器）
     */
    public static function deliver(Order $order): void
    {
        $order = Order::findOrEmpty((int) $order->getAttr('id'));
        if ($order->isEmpty() || (int) $order->getAttr('status') !== Order::ST_WAIT_DEAL) {
            return;
        }
        $goodsType = (int) $order->getAttr('goods_type');
        $quantity  = (int) $order->getAttr('quantity');

        if ($goodsType !== 1 && $goodsType !== 2) {
            // 实物等其他类型：等待人工
            self::log($order, '订单进入人工处理队列');
            return;
        }

        $stock = self::stockOf($order);
        if ($stock === null) {
            self::log($order, '商品未配置库存，等待人工处理');
            $order->save(['recharge_hints' => '库存补货中，订单排队处理']);
            return;
        }

        if ((int) $stock->getAttr('type') === GoodsStock::TYPE_DOCKING) {
            // 渠道发货（失败抛出走人工，不回滚支付）
            try {
                DockingV2Service::deliver($order, $stock);
            } catch (\Throwable $e) {
                self::log($order, '渠道发货进入人工处理：' . $e->getMessage());
            }
            return;
        }
        self::deliverByCards($order, $stock, $quantity);
    }

    /**
     * 本地卡密发货（FIFO：sale_first desc → id asc；固定/普通统一按卡行分配）
     */
    protected static function deliverByCards(Order $order, GoodsStock $stock, int $quantity): void
    {
        $cateId = (int) $stock->getAttr('card_cate_id');
        $qty    = $quantity;

        Db::startTrans();
        try {
            // FIFO 分配卡密（对齐原系统：sale_first desc, id asc）
            $cardIds = Card::where('card_cate_id', $cateId)
                ->where('status', 1)
                ->where('order_id', 0)
                ->order('sale_first desc, id asc')
                ->limit($qty)
                ->lock(true)
                ->column('id');
            if (count($cardIds) < $qty) {
                Db::rollback();
                self::log($order, '卡密库存不足（需 ' . $qty . ' 张），等待人工处理');
                $order->save(['recharge_hints' => '库存补货中，订单排队处理']);
                return;
            }
            Card::whereIn('id', $cardIds)->update([
                'status'      => 2, // 已售
                'order_id'    => (int) $order->getAttr('id'),
                'goods_id'    => (int) $order->getAttr('goods_id'),
                'uid'         => (int) $order->getAttr('uid'),
                'update_time' => time(),
            ]);
            // 扣库存（重算口径，防漂移；goods.stock_num 为冗余汇总，重算防 unsigned 下溢）
            $cateNum  = Card::where('card_cate_id', $cateId)->where('status', 1)->count();
            CardCate::where('id', $cateId)->update(['card_num' => $cateNum, 'update_time' => time()]);
            $stockNum = max(0, (int) $stock->getAttr('stock_num') - $qty);
            GoodsStock::where('id', (int) $stock->getAttr('id'))->update(['stock_num' => $stockNum]);
            $goodsId = (int) $order->getAttr('goods_id');
            $totalStock = (int) Db::name('goods_stock')->where('goods_id', $goodsId)->where('status', 1)->sum('stock_num');
            Db::name('goods')->where('id', $goodsId)->inc('sales_num', $qty)->update(['stock_num' => $totalStock]);

            // 订单完成
            $cost = bcmul((string) $stock->getAttr('cost_value'), (string) $qty, 5);
            Order::where('id', (int) $order->getAttr('id'))
                ->whereIn('status', [Order::ST_WAIT_DEAL, Order::ST_DEALING])
                ->update([
                    'status'        => Order::ST_SUCCESS,
                    'finish_time'   => time(),
                    'cost_value'    => $cost,
                    'stock_id'      => (int) $stock->getAttr('id'),
                    'docking_id'    => 0,
                    'has_sync'      => 1,
                    'update_time'   => time(),
                ]);
            OrderDolog::create([
                'stock_id'    => (int) $stock->getAttr('id'),
                'card_cate_id' => $cateId,
                'order_id'    => (int) $order->getAttr('id'),
                'stock_log_type' => OrderDolog::LOG_DEC,
                'mark'        => '自动发货扣卡 ' . $qty . ' 张',
                'create_time' => time(),
            ]);
            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            throw $e;
        }
        self::log($order, '自动发货成功 ' . $qty . ' 张卡密');
        // 分销佣金 / 分站抽成 / 供货货款结算（幂等）
        self::settleAfterSuccess(Order::findOrEmpty((int) $order->getAttr('id')));
    }

    /**
     * 订单成功后的资金结算统一出口（佣金/分站抽成/供货货款；各服务自身幂等）
     */
    public static function settleAfterSuccess(Order $order): void
    {
        try {
            CommissionService::settleIfSuccess((int) $order->getAttr('id'));
        } catch (\Throwable $e) {
            self::log($order, '佣金结算异常：' . $e->getMessage());
        }
        try {
            VipSiteService::makeShare($order);
        } catch (\Throwable $e) {
            self::log($order, '分站抽成异常：' . $e->getMessage());
        }
        try {
            SupplyService::settleIfSuccess($order);
        } catch (\Throwable $e) {
            self::log($order, '供货货款异常：' . $e->getMessage());
        }
        // 插件钩子：订单完成（结算后）
        Hook::fire('order.success', self::payload($order));
    }

    /**
     * 插件钩子上下文（只读快照，避免插件误改模型）
     */
    protected static function payload(Order $order, array $extra = []): array
    {
        return array_merge([
            'order_id'    => (int) $order->getAttr('id'),
            'ordersn'     => (string) $order->getAttr('ordersn'),
            'uid'         => (int) $order->getAttr('uid'),
            'goods_id'    => (int) $order->getAttr('goods_id'),
            'goods_name'  => (string) $order->getAttr('goods_name'),
            'quantity'    => (int) $order->getAttr('quantity'),
            'total_price' => (string) $order->getAttr('total_price'),
            'vip_id'      => (int) $order->getAttr('vip_id'),
            'supplier_id' => (int) $order->getAttr('supplier_id'),
        ], $extra);
    }

    /**
     * 订单库存行（type=1 卡密 或 type=2 渠道；卡密类取关联卡库，渠道类取绑定渠道）
     */
    public static function stockOf(Order $order): ?GoodsStock
    {
        $goods = Goods::findOrEmpty((int) $order->getAttr('goods_id'));
        if ($goods->isEmpty()) {
            return null;
        }
        $stock = GoodsStock::where('goods_id', (int) $goods->getAttr('id'))
            ->whereIn('type', [GoodsStock::TYPE_CARD, GoodsStock::TYPE_DOCKING])
            ->where('status', 1)
            ->order('id asc')
            ->findOrEmpty();
        return $stock->isEmpty() ? null : $stock;
    }

    /**
     * 后台人工处理（对齐 order/dual）
     * @param array $option{card_cate_id?, mark?}
     */
    public static function dualOrder(Order $order, int $status, array $option, int $adminId): void
    {
        $current = (int) $order->getAttr('status');
        if ($status === Order::ST_SUCCESS) {
            // 手动发货/处理成功：重试自动发货（卡密单）；其他类型直接置成功
            if ((int) $order->getAttr('goods_type') === 1) {
                Order::where('id', (int) $order->getAttr('id'))->update(['status' => Order::ST_WAIT_DEAL, 'update_time' => time()]);
                self::deliver($order);
                $fresh = Order::findOrEmpty((int) $order->getAttr('id'));
                if ((int) $fresh->getAttr('status') !== Order::ST_SUCCESS) {
                    throw_fail('发货失败：卡密库存不足或未配置卡库', 1);
                }
                return;
            }
            Order::where('id', (int) $order->getAttr('id'))
                ->whereIn('status', [Order::ST_WAIT_DEAL, Order::ST_DEALING])
                ->update(['status' => Order::ST_SUCCESS, 'finish_time' => time(), 'reader' => (string) $adminId, 'update_time' => time()]);
            self::log($order, '管理员手动处理成功');
            // 分销佣金 / 分站抽成 / 供货货款结算（幂等）
            self::settleAfterSuccess(Order::findOrEmpty((int) $order->getAttr('id')));
            return;
        }
        if ($status === Order::ST_DEALING) {
            Order::where('id', (int) $order->getAttr('id'))
                ->where('status', Order::ST_WAIT_DEAL)
                ->update(['status' => Order::ST_DEALING, 'recharge_hints' => (string) ($option['mark'] ?? '订单处理中'), 'reader' => (string) $adminId, 'update_time' => time()]);
            self::log($order, '管理员标记处理中');
            return;
        }
        if ($status === Order::ST_CANCEL_PAY) {
            // 仅待支付可取消
            $affected = Order::where('id', (int) $order->getAttr('id'))
                ->where('status', Order::ST_WAIT_PAY)
                ->update(['status' => Order::ST_CANCEL_PAY, 'mark' => '管理员取消', 'update_time' => time()]);
            if ($affected === 0) {
                throw_fail('仅待支付订单可取消', 1);
            }
            self::log($order, '管理员取消待支付订单');
            return;
        }
        throw_fail('不支持的处理状态', 1);
    }

    /**
     * 退款（status 3→5；余额支付原路退回；卡密冻结）
     */
    public static function refund(Order $order, string $money, int $adminId, string $remark = ''): void
    {
        if ((string) $order->getAttr('pay_type') !== 'balance') {
            throw_fail('仅余额支付订单支持系统自动退款', 1);
        }
        $money = bccomp($money, '0', 4) > 0 ? $money : (string) $order->getAttr('total_price');
        $paid  = bcsub((string) $order->getAttr('total_price'), (string) $order->getAttr('has_back_money'), 4);
        if (bccomp($money, $paid, 4) > 0) {
            throw_fail('退款金额超过实付金额', 1);
        }
        Db::startTrans();
        try {
            // 乐观推进 3 → 5
            $affected = Order::where('id', (int) $order->getAttr('id'))
                ->where('status', Order::ST_SUCCESS)
                ->update([
                    'status'         => Order::ST_REFUNDED,
                    'has_back_money' => bcadd((string) $order->getAttr('has_back_money'), $money, 4),
                    'update_time'    => time(),
                ]);
            if ($affected === 0) {
                Db::rollback();
                throw_fail('订单状态已变更，退款失败', 1);
            }
            // 卡密冻结（已发出的卡作废）
            $cardNum = 0;
            if ((int) $order->getAttr('goods_type') === 1) {
                $cardNum = Card::where('order_id', (int) $order->getAttr('id'))->where('status', 2)->count();
                Card::where('order_id', (int) $order->getAttr('id'))->where('status', 2)->update([
                    'status' => 3, // 冻结（不回库，防二次售卖）
                    'update_time' => time(),
                ]);
            }
            // 余额原路退（幂等键）
            $uid = (int) $order->getAttr('uid');
            if ($uid > 0) {
                MemberMoneyService::changeMoney(
                    $uid,
                    MemberMoney::T_ORDER_REFUND,
                    MemberMoney::C_INCOME,
                    $money,
                    (int) $order->getAttr('id'),
                    '订单退款 ' . (string) $order->getAttr('ordersn') . ' ' . $remark,
                    'REFUND:' . (string) $order->getAttr('ordersn'),
                    $adminId
                );
            }
            OrderRefund::create([
                'order_id'      => (string) $order->getAttr('id'),
                'money'         => $money,
                'oper'          => $adminId,
                'oper_nickname' => 'admin',
                'oper_type'     => 1,
                'create_time'   => time(),
                'backmoney_type' => 0,
                'backmoney_part' => bccomp($money, (string) $order->getAttr('total_price'), 4) === 0 ? 0 : 1,
                'order_style'   => 0,
                'order_money'   => (string) $order->getAttr('total_price'),
                'order_money1'  => (string) $order->getAttr('total_price'),
                'days'          => 0,
                'card_num'      => $cardNum,
            ]);
            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            throw $e;
        }
        self::log($order, '退款 ' . $money . ' 元' . ($cardNum > 0 ? '，冻结卡密 ' . $cardNum . ' 张' : ''));
        // 分销佣金按退款比例回冲
        try {
            $backRate = bcdiv($money, (string) $order->getAttr('total_price'), 4);
            CommissionService::rollbackForRefund((int) $order->getAttr('id'), $backRate);
            VipSiteService::rollbackShare((int) $order->getAttr('id'), $backRate);
            SupplyService::rollbackForRefund((int) $order->getAttr('id'), $backRate);
        } catch (\Throwable $e) {
            self::log($order, '佣金回冲异常：' . $e->getMessage());
        }
        // 插件钩子：订单退款
        Hook::fire('order.refund', self::payload($order, [
            'refund_money' => $money,
            'back_rate'    => (string) $money === '0.0000' || (string) $order->getAttr('total_price') === '0.0000'
                ? '0'
                : bcdiv($money, (string) $order->getAttr('total_price'), 4),
        ]));
    }

    /**
     * 商品卡密库存行（type=1 第一条）
     */
    public static function cardStockOf(Goods $goods): ?GoodsStock
    {
        $stock = GoodsStock::where('goods_id', (int) $goods->getAttr('id'))
            ->where('type', GoodsStock::TYPE_CARD)
            ->where('status', 1)
            ->order('id asc')
            ->findOrEmpty();
        return $stock->isEmpty() ? null : $stock;
    }

    /**
     * 订单操作日志
     */
    public static function log(Order $order, string $mark): void
    {
        OrderLog::create([
            'type'        => 1,
            'mark'        => mb_substr($mark, 0, 500, 'UTF-8'),
            'order_id'    => (int) $order->getAttr('id'),
            'create_time' => time(),
        ]);
    }
}
