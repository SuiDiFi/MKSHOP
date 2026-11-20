<?php
// +----------------------------------------------------------------------
// | MKSHOP 回归测试命令（重排路线④：测试资产沉淀）
// | 用法：php think mks:regress [all|e2e]
// | 内容：ZZTESTREG 前缀自造夹具 → 加款 → 服务层下单+余额支付 → 自动发货断言(13项)
// |       → 退款断言(5项) → 对账 audit=0 → 清理残留=0。可重复执行（幂等：先清后建）。
// | 红线：自造数据跑完清理；真实商品/会员只读；判定以打印标记为准（REGRESS_PASS）
// +----------------------------------------------------------------------
declare (strict_types = 1);

namespace app\common\commands;

use app\common\model\Card;
use app\common\model\Goods;
use app\common\model\GoodsStock;
use app\common\model\Member;
use app\common\model\MemberMoney;
use app\common\model\Order;
use app\common\service\MemberMoneyService;
use app\common\service\OrderService;
use app\common\service\StockAuditService;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\facade\Db;

class Regress extends Command
{
    private const PFX = 'ZZTESTREG';
    private array $fix = [];
    private int $bad = 0;
    private Output $out;

    protected function configure()
    {
        // 无参数化（think-console 的 Argument 常量在 6.1 不可用）：固定执行 e2e 全量回归
        $this->setName('mks:regress')->setDescription('回归测试：资金/订单/发货/退款/对账（自造数据，幂等可重复）');
    }

    protected function execute(Input $input, Output $output)
    {
        $this->out = $output;
        $this->step('清理历史残留');
        $this->cleanup();
        $this->step('建立夹具');
        $this->fixture();
        $this->step('加款 +50（T_ADMIN_ADD）');
        $r = MemberMoneyService::changeMoney((int) $this->fix['memberId'], MemberMoney::T_ADMIN_ADD, MemberMoney::C_INCOME, '50.0000', 0, self::PFX . ' 加款', '');
        $this->assert('add_balance_50', $r['after'] === '50.0000');
        $this->step('服务层下单 + 余额支付（等价 order/buy 闭环）');
        $goods = Goods::findOrEmpty((int) $this->fix['goodsId']);
        $member = Member::findOrEmpty((int) $this->fix['memberId']);
        try {
            $order = OrderService::createOrder($member, $goods, [
                'quantity' => 1, 'recharge_account' => self::PFX . '@acc', 'mark' => self::PFX, 'user_where' => 'pc',
            ]);
            $payOrder = OrderService::createPayOrder($order, 'balance');
            OrderService::payByBalance($payOrder, $member);
        } catch (\Throwable $e) {
            $output->writeln('REGRESS_FAIL create_or_pay: ' . $e->getMessage());
            $this->cleanup();
            return 1;
        }
        $ordersn = (string) $order->getAttr('ordersn');
        $this->step('发货后断言（13 项，含对账）');
        $this->assertPostPay($ordersn);
        $this->step('退款（refund：卡冻结不回库 + 余额原路退）');
        try {
            OrderService::refund(Order::findOrEmpty((int) $this->fix['orderId']), '10', 0, self::PFX . ' 回归退款');
        } catch (\Throwable $e) {
            $output->writeln('REGRESS_FAIL refund: ' . $e->getMessage());
            $this->cleanup();
            return 1;
        }
        $this->step('退款后断言（5 项）');
        $this->assertPostRefund();
        $this->step('对账（卡数 vs 库存桥 vs 商品冗余）');
        $audit = StockAuditService::audit();
        $this->assert('audit_clean', (int) $audit['total'] === 0);
        $this->step('清理夹具');
        $this->cleanup();
        $this->assert('cleanup_residual_0', $this->residual() === 0);
        if ($this->bad > 0) {
            $output->writeln("REGRESS_FAIL bad={$this->bad}");
            return 1;
        }
        $output->writeln('REGRESS_PASS');
        return 0;
    }

    private function step(string $msg): void
    {
        $this->out->writeln('[regress] ' . $msg);
    }

    private function assert(string $name, bool $cond): void
    {
        if (!$cond) {
            $this->out->writeln('ASSERT_FAIL ' . $name);
            $this->bad++;
        }
    }

    private function fixture(): void
    {
        $now = time();
        $cateId = (int) Db::name('card_cate')->insertGetId($this->nn('mks_card_cate', [
            'name' => self::PFX . ' 卡库', 'card_type' => 1, 'card_num' => 3,
            'card_no_alias' => '卡号', 'card_password_alias' => '卡密',
            'cost' => 5, 'status' => 1, 'create_time' => $now, 'update_time' => $now, 'delete_time' => 0,
        ]));
        for ($i = 1; $i <= 3; $i++) {
            Card::create($this->nn('mks_card', [
                'card_no' => self::PFX . '-N' . $i, 'card_password' => self::PFX . '-P' . $i,
                'card_cate_id' => $cateId, 'status' => 1, 'create_time' => $now, 'update_time' => $now,
            ]));
        }
        $goodsId = (int) Db::name('goods')->insertGetId($this->nn('mks_goods', [
            'goods_no' => self::PFX . '-G', 'goods_name' => self::PFX . ' 回归商品（勿下单）', 'goods_type' => 1,
            'goods_info' => '<p>' . self::PFX . '</p>', 'goods_notice' => '', 'order_notice' => '', 'category_ids' => '',
            'status' => 1, 'check_status' => 3, 'face_value' => '10.0000', 'real_price' => '10.0000', 'cost_value' => '5.00000',
            'start_count' => 1, 'end_count' => 10, 'multiple' => 0, 'tpl_id' => 0, 'ishot' => 0, 'sort' => 0,
            'vip_id' => 0, 'stock_num' => 3, 'warn_num' => 0, 'delete_time' => 0, 'create_time' => $now, 'update_time' => $now,
        ]));
        $stockId = (int) Db::name('goods_stock')->insertGetId($this->nn('mks_goods_stock', [
            'goods_id' => $goodsId, 'type' => GoodsStock::TYPE_CARD, 'card_cate_id' => $cateId, 'status' => 1,
            'cost_value' => '5.00000', 'stock_num' => 3, 'create_time' => $now,
        ]));
        $memberId = (int) Db::name('member')->insertGetId($this->nn('mks_member', [
            'username' => self::PFX . 'user', 'nickname' => self::PFX . ' 回归会员',
            'password' => password_hash(self::PFX . '@Pwd', PASSWORD_BCRYPT),
            'salt' => substr(md5(self::PFX . $now), 0, 12),
            'level_id' => 1, 'balance' => '0.0000', 'status' => 1, 'delete_time' => 0,
            'create_time' => $now, 'update_time' => $now,
        ]));
        $this->fix = ['cateId' => $cateId, 'goodsId' => $goodsId, 'stockId' => $stockId, 'memberId' => $memberId];
    }

    /** 补齐 NOT NULL 无默认列（防 1364） */
    private function nn(string $table, array $data): array
    {
        $cols = Db::query(
            "SELECT COLUMN_NAME, DATA_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND IS_NULLABLE = 'NO' AND COLUMN_DEFAULT IS NULL AND EXTRA NOT LIKE '%auto_increment%'",
            [$table]
        );
        foreach ($cols as $c) {
            $n = (string) $c['COLUMN_NAME'];
            if (!array_key_exists($n, $data)) {
                $data[$n] = in_array((string) $c['DATA_TYPE'], ['int', 'bigint', 'smallint', 'tinyint', 'decimal', 'float', 'double'], true) ? 0 : '';
            }
        }
        return $data;
    }

    private function assertPostPay(string $ordersn): void
    {
        $order = Db::name('order')->where('ordersn', $ordersn)->find();
        $oid = (int) ($order['id'] ?? 0);
        $this->fix['orderId'] = $oid;
        $balance = (string) Db::name('member')->where('id', (int) $this->fix['memberId'])->value('balance');
        $cardNo = (string) Card::where('order_id', $oid)->findOrEmpty()->getAttr('card_no');
        $cardSold = (int) Db::name('card')->where('card_cate_id', (int) $this->fix['cateId'])->where('status', 2)->count();
        $payLog = Db::name('recharge_log')->where('order_id', $oid)->find();
        $paySn = (string) ($payLog['order_sn'] ?? '');
        $pay = Db::name('member_money')->where('only_order_no', 'PAY:' . $paySn)->find();
        $cateNum = (int) Db::name('card_cate')->where('id', (int) $this->fix['cateId'])->value('card_num');
        $stockNum = (int) Db::name('goods_stock')->where('id', (int) $this->fix['stockId'])->value('stock_num');
        $sales = (int) Db::name('goods')->where('id', (int) $this->fix['goodsId'])->value('sales_num');
        $dolog = (int) Db::name('order_dolog')->where('order_id', $oid)->count();
        $this->assert('order_status_3', (int) ($order['status'] ?? 0) === 3);
        $this->assert('order_bound_member', (int) ($order['uid'] ?? 0) === (int) $this->fix['memberId']);
        $this->assert('total_10', (string) ($order['total_price'] ?? '') === '10.0000');
        $this->assert('balance_40', $balance === '40.0000');
        $this->assert('card_fifo_decrypted', $cardNo === self::PFX . '-N1');
        $this->assert('card_sold_1', $cardSold === 1);
        $this->assert('pay_flow', (string) ($pay['money'] ?? '') === '10.0000' && (string) ($pay['before'] ?? '') === '50.0000' && (string) ($pay['after'] ?? '') === '40.0000' && (int) ($pay['trade_type'] ?? 0) === MemberMoney::T_ORDER_PAY);
        $this->assert('cate_recalc_2', $cateNum === 2);
        $this->assert('stock_recalc_2', $stockNum === 2);
        $this->assert('sales_1', $sales === 1);
        $this->assert('dolog_1', $dolog === 1);
        $this->assert('paylog_paid_balance', (int) ($payLog['status'] ?? 0) === 1 && (string) ($payLog['payment_gateway'] ?? '') === 'balance');
    }

    private function assertPostRefund(): void
    {
        $oid = (int) $this->fix['orderId'];
        $order = Db::name('order')->where('id', $oid)->find();
        $balance = (string) Db::name('member')->where('id', (int) $this->fix['memberId'])->value('balance');
        $cardFrozen = (int) Db::name('card')->where('order_id', $oid)->where('status', 3)->count();
        $refundFlow = Db::name('member_money')->where('uid', (int) $this->fix['memberId'])->where('trade_type', MemberMoney::T_ORDER_REFUND)->find();
        $refundRows = (int) Db::name('order_refund')->where('order_id', $oid)->count();
        $this->assert('order_status_5', (int) ($order['status'] ?? 0) === 5);
        $this->assert('balance_50', $balance === '50.0000');
        $this->assert('card_frozen_1', $cardFrozen === 1);
        $this->assert('refund_flow', (string) ($refundFlow['money'] ?? '') === '10.0000' && (string) ($refundFlow['after'] ?? '') === '50.0000');
        $this->assert('refund_rows_1', $refundRows >= 1);
    }

    private function residual(): int
    {
        return (int) Db::name('card')->where('card_no', 'like', self::PFX . '%')->count()
            + (int) Db::name('goods')->where('goods_name', 'like', self::PFX . '%')->count()
            + (int) Db::name('goods')->where('goods_no', 'like', self::PFX . '%')->count()
            + (int) Db::name('member')->where('username', 'like', self::PFX . '%')->count()
            + (int) Db::name('card_cate')->where('name', 'like', self::PFX . '%')->count()
            + (int) Db::name('member_money')->where('mark', 'like', self::PFX . '%')->count();
    }

    private function cleanup(): void
    {
        // 全部按 ZZTESTREG 前缀级联清（不依赖进程内 fix，保证幂等）；逐表 try/catch 定位异常表
        $oidRows = Db::name('order')->where('goods_name', 'like', self::PFX . '%')->column('id');
        foreach (['order_dolog', 'order_log', 'order_refund', 'recharge_log'] as $t) {
            try {
                if ($oidRows) Db::name($t)->where('order_id', 'in', $oidRows)->delete();
                Db::name($t)->where('order_id', 'like', self::PFX . '%')->delete();
            } catch (\Throwable $e) {
                $this->out->writeln('CLEANUP_ERR ' . $t . ' ' . $e->getMessage());
            }
        }
        try {
            $mids = Db::name('member')->where('username', 'like', self::PFX . '%')->column('id');
            if ($mids) Db::name('member_money')->where('uid', 'in', $mids)->delete();
            Db::name('member')->where('username', 'like', self::PFX . '%')->delete();
            Db::name('card')->where('card_no', 'like', self::PFX . '%')->delete();
            Db::name('card_cate')->where('name', 'like', self::PFX . '%')->delete();
            Db::name('goods_stock')->where('goods_id', 'in', Db::name('goods')->where('goods_no', 'like', self::PFX . '%')->column('id'))->delete();
            Db::name('goods')->where('goods_no', 'like', self::PFX . '%')->delete();
            Db::name('goods')->where('goods_name', 'like', self::PFX . '%')->delete();
        } catch (\Throwable $e) {
            $this->out->writeln('CLEANUP_ERR base ' . $e->getMessage());
        }
        $this->fix = [];
    }
}
