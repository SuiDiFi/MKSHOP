<?php
// +----------------------------------------------------------------------
// | 运营统计服务（M8 对齐 KASUSHOU 首页统计 / 商品统计 / 用户统计 / 交易分析）
// |
// | 口径约定（与 MoneyDayService 完全一致，杜绝同一系统两套数字）：
// |   成交口径：status=3(交易成功) 且 finish_time 落在统计日 → 订单金额 total_price
// |             利润 order_price、成本 cost_value
// |   下单口径：create_time 落在统计日（含全部状态，反映流量与转化）
// |   退款口径：status=5(已经退款)，按 create_time 归日
// |   新增会员：member.create_time 落在统计日
// |   在售商品：goods.status=1（字典 goods_status），且 delete_time=0
// |   可售卡密：card.status=1 且 order_id=0（同 OrderService 分配条件）
// |
// | 数据源分层：
// |   mks_statics 为「按日成交汇总」物化表（先删后插幂等，由 statics_order 定时任务维护）
// |   区间 ≤ LIVE_DAYS 天走实时聚合（命中 order 的 status_finish_time / status_create_time 索引）
// |   区间 > LIVE_DAYS 天读 statics，缺日再由实时补算，保证图表不出现空洞
// |
// | 红线：金额一律 mks_money()（bcmath 4 位）；枚举文案一律走 Dict（sys_dict）
// +----------------------------------------------------------------------
declare (strict_types = 1);

namespace app\common\service;

use app\common\model\Card;
use app\common\model\Complain;
use app\common\model\Goods;
use app\common\model\MerchantApply;
use app\common\model\Order;
use app\common\model\RechargeLog;
use app\common\model\VipSite;
use mks\util\Dict;
use think\facade\Db;

class StaticsService
{
    /** 实时聚合的最大跨度（天）；超过则读 mks_statics 物化数据 */
    public const LIVE_DAYS = 14;

    /** 定时任务默认回算天数（对齐原系统「自动统计10天内订单数据」） */
    public const AGG_DAYS = 10;

    /** 支付网关文案（order.pay_type 存网关串，非数字码，故不走 sys_dict） */
    private const GATEWAY_LABELS = [
        'balance' => '余额支付',
        'wechat'  => '微信支付',
        'alipay'  => '支付宝',
        'epay'    => '易支付',
        'usdt'    => 'USDT',
        'offline' => '线下转账',
    ];

    /* ====================================================================
     | 区间
     ==================================================================== */

    /**
     * 解析统计区间：start/end（Y-m-d）优先，否则取最近 defaultDays 天（含今天）
     * @return array{0:string,1:string,2:int} [起始日, 结束日, 天数]
     */
    public static function resolveRange(string $start = '', string $end = '', int $defaultDays = 30): array
    {
        $end   = self::validDate($end) ? $end : date('Y-m-d');
        $start = self::validDate($start) ? $start : date('Y-m-d', strtotime($end . ' -' . ($defaultDays - 1) . ' day'));
        if ($start > $end) {
            [$start, $end] = [$end, $start];
        }
        $days = (int) (floor((strtotime($end) - strtotime($start)) / 86400) + 1);
        return [$start, $end, max(1, $days)];
    }

    private static function validDate(string $d): bool
    {
        return (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) && strtotime($d) !== false;
    }

    private static function dayRange(string $date, bool $wholeDay = true): array
    {
        $s = strtotime($date . ' 00:00:00');
        return [$s, $wholeDay ? $s + 86399 : time()];
    }

    /* ====================================================================
     | 物化：按日成交汇总（幂等）
     ==================================================================== */

    /**
     * 回算最近 $days 天的按日成交汇总到 mks_statics
     * @return int 写入天数
     */
    public static function aggregate(int $days = self::AGG_DAYS): int
    {
        $today = date('Y-m-d');
        $count = 0;
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime($today . " -{$i} day"));
            self::writeDay($date);
            $count++;
        }
        return $count;
    }

    /**
     * 写单日汇总（REPLACE INTO，主键 total_create_time）
     */
    public static function writeDay(string $date): array
    {
        [$s, $e] = self::dayRange($date);
        $row = Db::name('order')
            ->where('status', Order::ST_SUCCESS)
            ->where('delete_time', 0)
            ->whereBetween('finish_time', [$s, $e])
            ->field('COUNT(*) AS cnt, COALESCE(SUM(total_price),0) AS total, COALESCE(SUM(order_price),0) AS profit')
            ->find() ?: [];
        $data = [
            'total_create_time' => $date,
            'order_order_id'    => (int) ($row['cnt'] ?? 0),
            'order_total_price' => mks_money((string) ($row['total'] ?? '0')),
            'order_order_price' => mks_money((string) ($row['profit'] ?? '0')),
            'update_time'       => time(),
        ];
        // 先删后插（与 MoneyDayService 同模式）
        // 注：think-orm 的 insert($data, $flag) 第二参是 getLastInsID，不是 REPLACE，
        //     直接 insert 会在重复回算时撞主键（1062 Duplicate entry）
        Db::name('statics')->where('total_create_time', $date)->delete();
        Db::name('statics')->insert($data);
        return $data;
    }

    /* ====================================================================
     | 首页看板
     ==================================================================== */

    /**
     * 后台首页统计（index_statics）
     */
    public static function overview(): array
    {
        $today     = date('Y-m-d');
        $yesterday = date('Y-m-d', strtotime('-1 day'));

        return [
            'today'     => self::daySummary($today),
            'yesterday' => self::daySummary($yesterday),
            'pending'   => [
                // 订单待处理 / 处理中
                'wait_deal'      => self::countOrder(Order::ST_WAIT_DEAL),
                'dealing'        => self::countOrder(Order::ST_DEALING),
                'wait_pay'       => self::countOrder(Order::ST_WAIT_PAY),
                // 售后待处理
                'complain_wait'  => Complain::where('status', Complain::ST_WAIT)->where('delete_time', 0)->count(),
                // 商户入驻待审核（mks_merchant_apply 无 delete_time 列，不可加软删过滤）
                'apply_wait'     => MerchantApply::where('status', MerchantApply::ST_WAIT)->count(),
                // 商品待审核 / 已售罄
                'goods_wait'     => Goods::where('status', 4)->where('delete_time', 0)->count(),
                'goods_sold_out' => Goods::where('status', 6)->where('delete_time', 0)->count(),
            ],
            'totals'    => [
                'member_total'   => Db::name('member')->where('delete_time', 0)->count(),
                'goods_online'   => Goods::where('status', 1)->where('delete_time', 0)->count(),
                'goods_total'    => Goods::where('delete_time', 0)->count(),
                'card_available' => Card::where('status', 1)->where('order_id', 0)->where('delete_time', 0)->count(),
                'order_total'    => Db::name('order')->where('delete_time', 0)->count(),
                'balance_total'  => mks_money((string) Db::name('member')->where('delete_time', 0)->sum('balance')),
                'commission_total' => mks_money((string) Db::name('member')->where('delete_time', 0)->sum('commission_money')),
            ],
            'trend7'    => self::buyTrend(...self::tuple(date('Y-m-d', strtotime('-6 day')), $today)),
            'today_hour'=> self::buyToday(),
        ];
    }

    /** 单日成交/下单摘要（不含未来时段） */
    private static function daySummary(string $date): array
    {
        $whole = $date < date('Y-m-d');
        [$s, $e] = self::dayRange($date, $whole);

        $deal = Db::name('order')
            ->where('status', Order::ST_SUCCESS)
            ->where('delete_time', 0)
            ->whereBetween('finish_time', [$s, $e])
            ->field('COUNT(*) cnt, COALESCE(SUM(total_price),0) total, COALESCE(SUM(order_price),0) profit, COALESCE(SUM(cost_value),0) cost, COUNT(DISTINCT uid) uids')
            ->find() ?: [];

        $place = Db::name('order')
            ->where('delete_time', 0)
            ->whereBetween('create_time', [$s, $e])
            ->field('COUNT(*) cnt, COALESCE(SUM(total_price),0) total')
            ->find() ?: [];

        return [
            'date'         => $date,
            'order_count'  => (int) ($place['cnt'] ?? 0),      // 下单量
            'order_amount' => mks_money((string) ($place['total'] ?? '0')),
            'success_count'=> (int) ($deal['cnt'] ?? 0),       // 成交量
            'success_amount' => mks_money((string) ($deal['total'] ?? '0')),
            'profit'       => mks_money((string) ($deal['profit'] ?? '0')),
            'cost'         => mks_money((string) ($deal['cost'] ?? '0')),
            'buyers'       => (int) ($deal['uids'] ?? 0),
            'avg_price'    => self::div($deal['total'] ?? '0', $deal['cnt'] ?? 0),  // 客单价
            'new_member'   => Db::name('member')->where('delete_time', 0)->whereBetween('create_time', [$s, $e])->count(),
        ];
    }

    private static function countOrder(int $status): int
    {
        return Db::name('order')->where('status', $status)->where('delete_time', 0)->count();
    }

    /**
     * 支付网关文案映射（未知网关原样返回码值，不做臆测）
     * @return array<string,string>
     */
    private static function gatewayLabel(): array
    {
        return self::GATEWAY_LABELS;
    }

    /** @return array{0:string,1:string} */
    private static function tuple(string $a, string $b): array
    {
        return [$a, $b];
    }

    /* ====================================================================
     | 交易分析
     ==================================================================== */

    /**
     * 交易统计（buy_statics）：区间总量 + 结构分布
     */
    public static function buyStatics(string $start = '', string $end = ''): array
    {
        [$from, $to, $days] = self::resolveRange($start, $end, 30);
        [$s, $e] = self::span($from, $to);

        $deal = Db::name('order')
            ->where('status', Order::ST_SUCCESS)
            ->where('delete_time', 0)
            ->whereBetween('finish_time', [$s, $e])
            ->field('COUNT(*) cnt, COALESCE(SUM(total_price),0) total, COALESCE(SUM(order_price),0) profit, COALESCE(SUM(cost_value),0) cost, COUNT(DISTINCT uid) uids')
            ->find() ?: [];

        $place  = self::countBy('order', 'create_time', $s, $e);
        $refund = self::countBy('order', 'create_time', $s, $e, ['status' => Order::ST_REFUNDED]);
        $cancel = self::countBy('order', 'create_time', $s, $e, ['status' => [Order::ST_CANCEL, Order::ST_CANCEL_PAY]]);

        return [
            'range'        => ['start' => $from, 'end' => $to, 'days' => $days],
            'order_count'  => $place,
            'success_count'=> (int) ($deal['cnt'] ?? 0),
            'success_rate' => self::rate($deal['cnt'] ?? 0, $place),
            'total_amount' => mks_money((string) ($deal['total'] ?? '0')),
            'profit'       => mks_money((string) ($deal['profit'] ?? '0')),
            'cost'         => mks_money((string) ($deal['cost'] ?? '0')),
            'profit_rate'  => self::rate($deal['profit'] ?? '0', $deal['total'] ?? '0'),
            'avg_price'    => self::div($deal['total'] ?? '0', $deal['cnt'] ?? 0),
            'buyers'       => (int) ($deal['uids'] ?? 0),
            'refund_count' => $refund,
            'cancel_count' => $cancel,
            'provide_type' => self::distribution('order', 'provide_type', 'finish_time', $s, $e, ['status' => Order::ST_SUCCESS], 'provide_type'),
            'pay_gateway'  => self::distribution('order', 'pay_type', 'finish_time', $s, $e, ['status' => Order::ST_SUCCESS], '', self::gatewayLabel()),
            // 主站/分站归属（M6 分站体系；vip_id=0 为主站）
            'vip_site'     => self::vipDistribution($s, $e),
        ];
    }

    /**
     * 交易统计图表（buy_statics_trend）：按日
     */
    public static function buyTrend(string $start = '', string $end = ''): array
    {
        [$from, $to, $days] = self::resolveRange($start, $end, 30);
        // 长区间读物化表（days > LIVE_DAYS），缺日自动回落实时补算
        $out = $days > self::LIVE_DAYS ? self::seriesFromStatics($from, $to) : self::seriesLive($from, $to);
        $out['range']['days']        = $days;
        $out['range']['materialized'] = $days > self::LIVE_DAYS;
        return $out;
    }

    /**
     * 交易统计今日图表（buy_statics_today）：按小时
     */
    public static function buyToday(): array
    {
        $dayStart = strtotime(date('Y-m-d') . ' 00:00:00');
        $rows = Db::name('order')
            ->where('delete_time', 0)
            ->where('create_time', '>=', $dayStart)
            ->field("FROM_UNIXTIME(create_time,'%H') h, COUNT(*) cnt, COALESCE(SUM(total_price),0) total")
            ->group('h')
            ->select()
            ->toArray();
        $placeMap = [];
        foreach ($rows as $r) {
            $placeMap[(int) $r['h']] = ['cnt' => (int) $r['cnt'], 'total' => (string) $r['total']];
        }

        $dealRows = Db::name('order')
            ->where('status', Order::ST_SUCCESS)
            ->where('delete_time', 0)
            ->where('finish_time', '>=', $dayStart)
            ->field("FROM_UNIXTIME(finish_time,'%H') h, COUNT(*) cnt, COALESCE(SUM(order_price),0) profit")
            ->group('h')
            ->select()
            ->toArray();
        $dealMap = [];
        foreach ($dealRows as $r) {
            $dealMap[(int) $r['h']] = ['cnt' => (int) $r['cnt'], 'profit' => (string) $r['profit']];
        }

        $list = [];
        $nowH = (int) date('G');
        for ($h = 0; $h <= $nowH; $h++) {
            $list[] = [
                'hour'         => sprintf('%02d:00', $h),
                'order_count'  => $placeMap[$h]['cnt'] ?? 0,
                'order_amount' => mks_money($placeMap[$h]['total'] ?? '0'),
                'success_count'=> $dealMap[$h]['cnt'] ?? 0,
                'profit'       => mks_money($dealMap[$h]['profit'] ?? '0'),
            ];
        }
        return ['date' => date('Y-m-d'), 'list' => $list];
    }

    /** 按日实时聚合序列（零填充） */
    private static function seriesLive(string $from, string $to): array
    {
        $map = self::dayBuckets($from, $to);
        return ['range' => ['start' => $from, 'end' => $to], 'list' => self::fill($from, $to, fn (string $d) => $map[$d] ?? self::emptyDay())];
    }

    /**
     * 单日指标（用于 mks_statics 缺日补算）
     */
    private static function dayMetrics(string $date): array
    {
        $bucket = self::dayBuckets($date, $date);
        return $bucket[$date] ?? self::emptyDay();
    }

    /**
     * 区间内按日分桶（成交/下单/退款 三条聚合查询）
     * @return array<string,array> 日期 → 指标
     */
    private static function dayBuckets(string $from, string $to): array
    {
        [$s, $e] = self::span($from, $to);

        $deal = Db::name('order')
            ->where('status', Order::ST_SUCCESS)
            ->where('delete_time', 0)
            ->whereBetween('finish_time', [$s, $e])
            ->field("FROM_UNIXTIME(finish_time,'%Y-%m-%d') d, COUNT(*) cnt, COALESCE(SUM(total_price),0) total, COALESCE(SUM(order_price),0) profit, COALESCE(SUM(cost_value),0) cost, COUNT(DISTINCT uid) uids")
            ->group('d')
            ->select()
            ->toArray();
        $dealMap = array_column($deal, null, 'd');

        $place = Db::name('order')
            ->where('delete_time', 0)
            ->whereBetween('create_time', [$s, $e])
            ->field("FROM_UNIXTIME(create_time,'%Y-%m-%d') d, COUNT(*) cnt, COALESCE(SUM(total_price),0) total")
            ->group('d')
            ->select()
            ->toArray();
        $placeMap = array_column($place, null, 'd');

        $refund = Db::name('order')
            ->where('status', Order::ST_REFUNDED)
            ->where('delete_time', 0)
            ->whereBetween('create_time', [$s, $e])
            ->field("FROM_UNIXTIME(create_time,'%Y-%m-%d') d, COUNT(*) cnt, COALESCE(SUM(total_price),0) total")
            ->group('d')
            ->select()
            ->toArray();
        $refundMap = array_column($refund, null, 'd');

        $out = [];
        foreach (array_unique(array_merge(array_keys($dealMap), array_keys($placeMap), array_keys($refundMap))) as $d) {
            $dl = $dealMap[$d] ?? [];
            $pl = $placeMap[$d] ?? [];
            $rf = $refundMap[$d] ?? [];
            $out[$d] = [
                'order_count'   => (int) ($pl['cnt'] ?? 0),
                'order_amount'  => mks_money((string) ($pl['total'] ?? '0')),
                'success_count' => (int) ($dl['cnt'] ?? 0),
                'success_amount'=> mks_money((string) ($dl['total'] ?? '0')),
                'profit'        => mks_money((string) ($dl['profit'] ?? '0')),
                'cost'          => mks_money((string) ($dl['cost'] ?? '0')),
                'buyers'        => (int) ($dl['uids'] ?? 0),
                'refund_count'  => (int) ($rf['cnt'] ?? 0),
                'refund_amount' => mks_money((string) ($rf['total'] ?? '0')),
            ];
        }
        return $out;
    }

    /** 零值日（图表补空用） */
    private static function emptyDay(): array
    {
        return [
            'order_count' => 0, 'order_amount' => '0.0000', 'success_count' => 0, 'success_amount' => '0.0000',
            'profit' => '0.0000', 'cost' => '0.0000', 'buyers' => 0, 'refund_count' => 0, 'refund_amount' => '0.0000',
        ];
    }

    /** 从 mks_statics 读物化序列，缺日用实时补算（保证图表无空洞） */
    private static function seriesFromStatics(string $from, string $to): array
    {
        $rows = Db::name('statics')
            ->whereBetween('total_create_time', [$from, $to])
            ->select()
            ->toArray();
        $map = array_column($rows, null, 'total_create_time');

        $list = self::fill($from, $to, function (string $d) use ($map) {
            if (isset($map[$d])) {
                return [
                    'order_count'    => 0,   // 物化表仅存成交口径（下单量置 0 并在前端标注）
                    'success_count'  => (int) $map[$d]['order_order_id'],
                    'success_amount' => mks_money((string) $map[$d]['order_total_price']),
                    'profit'         => mks_money((string) $map[$d]['order_order_price']),
                    'source'         => 'statics',
                ];
            }
            return self::dayMetrics($d);
        });
        return ['range' => ['start' => $from, 'end' => $to], 'list' => $list];
    }

    /* ====================================================================
     | 商品统计
     ==================================================================== */

    /**
     * 商品统计（goods_statics）
     */
    public static function goodsStatics(string $start = '', string $end = ''): array
    {
        [$from, $to, $days] = self::resolveRange($start, $end, 30);
        [$s, $e] = self::span($from, $to);

        $inRange = Db::name('order')
            ->where('status', Order::ST_SUCCESS)
            ->where('delete_time', 0)
            ->whereBetween('finish_time', [$s, $e])
            ->field('COUNT(DISTINCT goods_id) gids, COALESCE(SUM(quantity),0) qty, COALESCE(SUM(total_price),0) total')
            ->find() ?: [];

        // 状态分布（字典 goods_status）
        $statusRows = Db::name('goods')->where('delete_time', 0)->field('status, COUNT(*) cnt')->group('status')->select()->toArray();
        $status = [];
        foreach ($statusRows as $r) {
            $code = (string) $r['status'];
            $status[] = ['code' => $code, 'label' => Dict::label('goods_status', $code), 'count' => (int) $r['cnt']];
        }

        // 低库存预警（warn_num>0 且 stock_num<=warn_num）
        $warn = Db::name('goods')
            ->where('delete_time', 0)
            ->where('warn_num', '>', 0)
            ->whereRaw('stock_num <= warn_num')
            ->count();

        return [
            'range'        => ['start' => $from, 'end' => $to, 'days' => $days],
            'goods_total'  => Db::name('goods')->where('delete_time', 0)->count(),
            'goods_online' => Goods::where('status', 1)->where('delete_time', 0)->count(),
            'sales_qty'    => (int) ($inRange['qty'] ?? 0),
            'sales_amount' => mks_money((string) ($inRange['total'] ?? '0')),
            'active_goods' => (int) ($inRange['gids'] ?? 0),
            'browse_total' => (int) Db::name('goods')->where('delete_time', 0)->sum('browse'),
            'sales_num_all'=> (int) Db::name('goods')->where('delete_time', 0)->sum('sales_num'),
            'card_available' => Card::where('status', 1)->where('order_id', 0)->where('delete_time', 0)->count(),
            'stock_warn'   => $warn,
            'status_dist'  => $status,
            'provide_dist' => self::distribution('goods', 'provide_type', '', 0, 0, [], 'provide_type', [], ''),
        ];
    }

    /**
     * 商品统计图表（goods_statics_trend）：按日出单商品数 / 销量 / 销售额
     */
    public static function goodsTrend(string $start = '', string $end = ''): array
    {
        [$from, $to, $days] = self::resolveRange($start, $end, 30);
        [$s, $e] = self::span($from, $to);

        $rows = Db::name('order')
            ->where('status', Order::ST_SUCCESS)
            ->where('delete_time', 0)
            ->whereBetween('finish_time', [$s, $e])
            ->field("FROM_UNIXTIME(finish_time,'%Y-%m-%d') d, COUNT(DISTINCT goods_id) gids, COALESCE(SUM(quantity),0) qty, COALESCE(SUM(total_price),0) total, COUNT(*) cnt")
            ->group('d')
            ->select()
            ->toArray();
        $map = array_column($rows, null, 'd');

        $list = self::fill($from, $to, fn (string $d) => [
            'order_count'  => (int) ($map[$d]['cnt'] ?? 0),
            'active_goods' => (int) ($map[$d]['gids'] ?? 0),
            'sales_qty'    => (int) ($map[$d]['qty'] ?? 0),
            'sales_amount' => mks_money((string) ($map[$d]['total'] ?? '0')),
        ]);
        return ['range' => ['start' => $from, 'end' => $to, 'days' => $days], 'list' => $list];
    }

    /**
     * 商品排行统计（goods_statics_list）
     * @param string $sort sales|amount|orders|browse
     */
    public static function goodsRank(string $start = '', string $end = '', string $sort = 'sales', int $limit = 20): array
    {
        [$from, $to] = self::resolveRange($start, $end, 30);
        [$s, $e] = self::span($from, $to);
        $limit = max(1, min(100, $limit));

        $rows = Db::name('order')->alias('o')
            ->join('goods g', 'g.id = o.goods_id')
            ->where('o.status', Order::ST_SUCCESS)
            ->where('o.delete_time', 0)
            ->whereBetween('o.finish_time', [$s, $e])
            ->field('o.goods_id, g.goods_name, g.goods_no, g.provide_type, g.browse, g.stock_num, g.real_price,
                     COUNT(o.id) orders, SUM(o.quantity) sales_qty, SUM(o.total_price) amount, SUM(o.order_price) profit')
            ->group('o.goods_id')
            ->select()
            ->toArray();

        foreach ($rows as &$r) {
            $r['amount'] = mks_money((string) $r['amount']);
            $r['profit'] = mks_money((string) $r['profit']);
            $r['provide_type_label'] = Dict::label('provide_type', (string) $r['provide_type']);
        }
        unset($r);

        $key = ['sales' => 'sales_qty', 'amount' => 'amount', 'orders' => 'orders', 'browse' => 'browse'][$sort] ?? 'sales_qty';
        usort($rows, function ($a, $b) use ($key) {
            return (float) $b[$key] <=> (float) $a[$key];
        });

        return [
            'range' => ['start' => $from, 'end' => $to],
            'sort'  => $sort,
            'list'  => array_slice($rows, 0, $limit),
        ];
    }

    /* ====================================================================
     | 用户统计
     ==================================================================== */

    /**
     * 用户统计（user_statics）
     */
    public static function userStatics(string $start = '', string $end = ''): array
    {
        [$from, $to, $days] = self::resolveRange($start, $end, 30);
        [$s, $e] = self::span($from, $to);

        $newRows = Db::name('member')->where('delete_time', 0)->whereBetween('create_time', [$s, $e])->count();
        $buyerRow = Db::name('order')->where('status', Order::ST_SUCCESS)->where('delete_time', 0)
            ->whereBetween('finish_time', [$s, $e])->field('COUNT(DISTINCT uid) uids')->find() ?: [];
        $buyers = (int) ($buyerRow['uids'] ?? 0);
        // 充值只取充值单（type=T_RECHARGE）并排除下单支付单，金额取实付 price
        $recharge = Db::name('recharge_log')
            ->where('status', RechargeLog::ST_PAID)
            ->where('type', RechargeLog::T_RECHARGE)
            ->whereBetween('payment_time', [$s, $e])
            ->field('COUNT(*) cnt, COALESCE(SUM(price),0) total, COALESCE(SUM(recharge_give_price),0) give')
            ->find() ?: [];

        // 等级分布
        $levels = Db::name('member')->alias('m')
            ->leftJoin('member_level l', 'l.id = m.level_id')
            ->where('m.delete_time', 0)
            ->field('m.level_id, l.name level_name, COUNT(*) cnt, COALESCE(SUM(m.balance),0) balance')
            ->group('m.level_id')
            ->select()
            ->toArray();
        foreach ($levels as &$l) {
            $l['level_name'] = (string) ($l['level_name'] ?? '') !== '' ? $l['level_name'] : '未分级';
            $l['balance']    = mks_money((string) $l['balance']);
        }
        unset($l);

        $memberTotal = (int) Db::name('member')->where('delete_time', 0)->count();

        return [
            'range'       => ['start' => $from, 'end' => $to, 'days' => $days],
            'member_total'=> $memberTotal,
            'new_member'  => (int) $newRows,
            'today_new'   => Db::name('member')->where('delete_time', 0)->where('create_time', '>=', strtotime(date('Y-m-d')))->count(),
            'buyers'      => (int) $buyers,
            // 活跃成交率：区间成交人数 / 会员总数（旧口径用区间新增会员作分母，会把老会员复购算失真）
            'active_rate' => self::rate($buyers, $memberTotal),
            'balance_total' => mks_money((string) Db::name('member')->where('delete_time', 0)->sum('balance')),
            'commission_total' => mks_money((string) Db::name('member')->where('delete_time', 0)->sum('commission_money')),
            'recharge_count' => (int) ($recharge['cnt'] ?? 0),
            'recharge_amount'=> mks_money((string) ($recharge['total'] ?? '0')),
            'recharge_give'  => mks_money((string) ($recharge['give'] ?? '0')),
            'level_dist'  => $levels,
        ];
    }

    /**
     * 用户统计图表（user_statics_trend）：按日新增会员 / 下单用户 / 成交年金流
     */
    public static function userTrend(string $start = '', string $end = ''): array
    {
        [$from, $to, $days] = self::resolveRange($start, $end, 30);
        [$s, $e] = self::span($from, $to);

        $reg = Db::name('member')->where('delete_time', 0)->whereBetween('create_time', [$s, $e])
            ->field("FROM_UNIXTIME(create_time,'%Y-%m-%d') d, COUNT(*) cnt")->group('d')->select()->toArray();
        $regMap = array_column($reg, null, 'd');

        $buy = Db::name('order')->where('status', Order::ST_SUCCESS)->where('delete_time', 0)
            ->whereBetween('finish_time', [$s, $e])
            ->field("FROM_UNIXTIME(finish_time,'%Y-%m-%d') d, COUNT(DISTINCT uid) uids, COALESCE(SUM(total_price),0) total")->group('d')
            ->select()->toArray();
        $buyMap = array_column($buy, null, 'd');

        $list = self::fill($from, $to, fn (string $d) => [
            'new_member'   => (int) ($regMap[$d]['cnt'] ?? 0),
            'buyers'       => (int) ($buyMap[$d]['uids'] ?? 0),
            'user_amount'  => mks_money((string) ($buyMap[$d]['total'] ?? '0')),
        ]);
        return ['range' => ['start' => $from, 'end' => $to, 'days' => $days], 'list' => $list];
    }

    /* ====================================================================
     | 通用工具
     ==================================================================== */

    /** 区间起止时间戳（含整日） */
    private static function span(string $from, string $to): array
    {
        return [strtotime($from . ' 00:00:00'), strtotime($to . ' 23:59:59')];
    }

    /** 按日零填充 */
    private static function fill(string $from, string $to, callable $fn): array
    {
        $out  = [];
        $cur  = strtotime($from);
        $stop = strtotime($to);
        while ($cur <= $stop) {
            $d    = date('Y-m-d', $cur);
            $out[] = array_merge(['date' => $d], $fn($d));
            $cur  += 86400;
        }
        return $out;
    }

    /** 条件计数 */
    private static function countBy(string $table, string $timeField, int $s, int $e, array $where = []): int
    {
        $q = Db::name($table)->where('delete_time', 0)->whereBetween($timeField, [$s, $e]);
        foreach ($where as $k => $v) {
            is_array($v) ? $q->whereIn($k, $v) : $q->where($k, $v);
        }
        return $q->count();
    }

    /**
     * 维度分布（码值 → 文案 → 数量/金额）
     * @param string $dictType  sys_dict 类型（优先）
     * @param array  $labelMap  非字典维度（如支付网关）的文案映射
     * @param string $moneyField 金额列；传 '' 则不输出金额
     */
    private static function distribution(string $table, string $field, string $timeField, int $s, int $e, array $where, string $dictType = '', array $labelMap = [], string $moneyField = 'total_price'): array
    {
        if ($field === '') {
            return [];
        }
        $q = Db::name($table)->where('delete_time', 0);
        if ($timeField !== '') {
            $q = $q->whereBetween($timeField, [$s, $e]);
        }
        foreach ($where as $k => $v) {
            is_array($v) ? $q->whereIn($k, $v) : $q->where($k, $v);
        }
        $select = "$field code, COUNT(*) cnt" . ($moneyField !== '' ? ", COALESCE(SUM($moneyField),0) total" : '');
        $rows = $q->field($select)->group($field)->select()->toArray();

        $out = [];
        foreach ($rows as $r) {
            $code = (string) $r['code'];
            $item = [
                'code'  => $code,
                'label' => self::pickLabel($dictType, $labelMap, $code),
                'count' => (int) $r['cnt'],
            ];
            if ($moneyField !== '') {
                $item['amount'] = mks_money((string) $r['total']);
            }
            $out[] = $item;
        }
        usort($out, fn ($a, $b) => $b['count'] <=> $a['count']);
        return $out;
    }

    /**
     * 主站/分站成交分布（vip_id=0 主站，其余取分站名）
     */
    private static function vipDistribution(int $s, int $e): array
    {
        $rows = Db::name('order')
            ->where('delete_time', 0)
            ->where('status', Order::ST_SUCCESS)
            ->whereBetween('finish_time', [$s, $e])
            ->field('vip_id code, COUNT(*) cnt, COALESCE(SUM(total_price),0) total')
            ->group('vip_id')
            ->select()
            ->toArray();
        $names  = $rows === [] ? [] : VipSite::whereIn('id', array_column($rows, 'code'))->column('name', 'id');
        $out    = [];
        foreach ($rows as $r) {
            $code = (int) $r['code'];
            $out[] = [
                'code'   => (string) $code,
                'label'  => $code > 0 ? ((string) ($names[$code] ?? '') !== '' ? '分站-' . $names[$code] : "分站#{$code}") : '主站',
                'count'  => (int) $r['cnt'],
                'amount' => mks_money((string) $r['total']),
            ];
        }
        usort($out, fn ($a, $b) => $b['count'] <=> $a['count']);
        return $out;
    }

    /** 文案优先级：字典 → 映射表 → 码值原样 */
    private static function pickLabel(string $dictType, array $labelMap, string $code): string
    {
        if ($dictType !== '') {
            $label = Dict::label($dictType, $code);
            if ((string) $label !== $code) {
                return (string) $label;
            }
        }
        return (string) ($labelMap[$code] ?? $code);
    }

    /** 单价 = 金额 / 数量（4 位） */
    private static function div(string|float|int $total, string|float|int $cnt): string
    {
        $cnt = (float) $cnt;
        return $cnt > 0 ? bcdiv((string) $total, (string) $cnt, 4) : '0.0000';
    }

    /** 百分比（2 位，返回数值不带 % 号） */
    private static function rate(string|float|int $a, string|float|int $b): float
    {
        $b = (float) $b;
        return $b > 0 ? round((float) $a * 100 / $b, 2) : 0.0;
    }
}
