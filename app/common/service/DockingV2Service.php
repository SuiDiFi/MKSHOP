<?php
// +----------------------------------------------------------------------
// | MKSHOP KASUSHOU 2.0 v2api 客户端（M3 上游对接第一适配）
// 协议：POST JSON；Header Sign=sha1(13位毫秒时间戳+ksort(body)JSON+apikey)/Timestamp/UserId(appid)
// 下单异步：buy 成功≠充值成功；查单 /api/v1/order/info 轮询（1-3s）或回调拿卡密
// 上游状态：1等待 2处理中 3成功 4取消 5退款 -1未付（与本地订单同语义）
// +----------------------------------------------------------------------
declare (strict_types = 1);

namespace app\common\service;

use app\common\model\Card;
use app\common\model\Docking;
use app\common\model\DockingLog;
use app\common\model\GoodsStock;
use app\common\model\Order;
use app\common\model\OrderDolog;
use app\common\model\RechargeLog;
use app\common\model\MemberMoney;
use mks\util\Crypt;
use mks\util\DockSign;
use think\facade\Db;

class DockingV2Service
{
    /**
     * 渠道密钥（docking.username=appid，docking.password=apikey；M0 基座约定 text 可加密，这里兼容明文）
     */
    public static function credentials(Docking $docking): array
    {
        $userid = (string) Crypt::decrypt((string) ($docking->getAttr('username') ?? ''));
        $apikey = (string) Crypt::decrypt((string) ($docking->getAttr('password') ?? ''));
        return [$userid, $apikey];
    }

    /**
     * 统一请求（签名 + 日志 + 状态码校验）
     * @return array{code:int, msg:string, data:array}
     */
    public static function request(Docking $docking, string $path, array $body, int $logType = 0, Order $order = null): array
    {
        [$userid, $apikey] = self::credentials($docking);
        $base = rtrim((string) $docking->getAttr('url'), '/');
        $signed = DockSign::makeHeaders($body, $apikey, $userid);
        $url = $base . $path;

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $signed['json'],
            CURLOPT_HTTPHEADER     => array_map(fn ($k, $v) => "$k: $v", array_keys($signed['headers']), $signed['headers']),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
        ]);
        $res = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $ok = false;
        $decoded = [];
        $summary = '';
        if ($res !== false && $errno === 0) {
            $decoded = json_decode((string) $res, true);
            if (is_array($decoded)) {
                $ok = (int) ($decoded['code'] ?? 0) === 200;
                $summary = (string) ($decoded['msg'] ?? '');
            }
        } else {
            $summary = 'CURL_ERR#' . $errno . ' ' . $error;
        }
        self::writeLog($docking, $logType, $path, $body, $signed, (string) $res, $ok, $summary, $order);

        if (!$ok) {
            throw_fail('渠道接口异常：' . $summary, 1);
        }
        return ['code' => (int) ($decoded['code'] ?? 0), 'msg' => (string) ($decoded['msg'] ?? ''), 'data' => (array) ($decoded['data'] ?? [])];
    }

    /**
     * 渠道下单（异步；card_list 可能同步给出）
     * @return array{ordersn:string, external_orderno:string, total_price:string, status:int, card_list:array}
     */
    public static function buy(Docking $docking, GoodsStock $stock, Order $order): array
    {
        $quantity = (int) $order->getAttr('quantity');
        $total = (string) $order->getAttr('total_price');
        $body = [
            'id'               => (int) $stock->getAttr('docking_goods_id'),
            'external_orderno' => (string) $order->getAttr('ordersn'),
            'quantity'         => $quantity,
            'safe_price'       => $total, // 安全价=本单实付，防上游调价亏本
            'mark'             => 'MKSHOP:' . (string) $order->getAttr('ordersn'),
        ];
        $account = (string) $order->getAttr('recharge_account');
        if ($account !== '') {
            $body['attach'] = ['recharge_account' => $account];
        }
        $res = self::request($docking, '/api/v1/order/buy', $body, DockingLog::L_BUY, $order);
        $data = $res['data'];
        return [
            'ordersn'          => (string) ($data['ordersn'] ?? ''),
            'external_orderno' => (string) ($data['external_orderno'] ?? (string) $order->getAttr('ordersn')),
            'total_price'      => (string) ($data['total_price'] ?? $total),
            'status'           => (int) ($data['status'] ?? 0),
            'card_list'        => (array) ($data['card_list'] ?? []),
        ];
    }

    /**
     * 渠道查单（按上游 ordersn 批量，逗号分隔）
     */
    public static function queryOrders(Docking $docking, array $remoteOrderSns): array
    {
        $body = ['external_orderno' => '', 'ordersn' => implode(',', $remoteOrderSns)];
        $res = self::request($docking, '/api/v1/order/info', $body, DockingLog::L_QUERY);
        return (array) ($res['data'] ?? []);
    }

    /**
     * 渠道商品列表
     */
    public static function goodsList(Docking $docking, int $page = 1, string $keyword = '', int $cateId = 0, int $limit = 100): array
    {
        $body = ['cate_id' => $cateId, 'keyword' => $keyword, 'limit' => $limit, 'page' => $page];
        $res = self::request($docking, '/api/v1/goods/list', $body, DockingLog::L_GOODS);
        return $res['data'];
    }

    /**
     * 渠道分类列表
     */
    public static function cateList(Docking $docking): array
    {
        $res = self::request($docking, '/api/v1/goods/cate', [], DockingLog::L_GOODS);
        return $res['data'];
    }

    /**
     * 渠道余额
     */
    public static function userMoney(Docking $docking): string
    {
        $res = self::request($docking, '/api/v1/user/money', [], 0);
        return (string) ($res['data']['money'] ?? '0');
    }

    /**
     * 渠道发货：提交渠道单并按响应推进订单（卡密同步给卡 / 无卡转处理中）
     */
    public static function deliver(Order $order, GoodsStock $stock): void
    {
        $docking = Docking::findOrEmpty((int) $stock->getAttr('docking_id'));
        if ($docking->isEmpty() || (int) $docking->getAttr('status') !== 1) {
            throw_fail('对接渠道不存在或已停用', 1);
        }
        // 渠道单标记（乐观：避免重复提交）
        $affected = Order::where('id', (int) $order->getAttr('id'))
            ->whereIn('status', [Order::ST_WAIT_DEAL, Order::ST_DEALING])
            ->where('docking_info', '')
            ->update([
                'docking_id'        => (int) $docking->getAttr('id'),
                'docking_type'      => (string) $docking->getAttr('type'),
                'docking_goods_id'  => (string) $stock->getAttr('docking_goods_id'),
                'docking_goods_sku_id' => (string) $stock->getAttr('docking_goods_sku_id'),
                'has_sync'          => 1,
                'update_time'       => time(),
            ]);
        if ($affected === 0) {
            return; // 已提交过（幂等）
        }
        $order = Order::findOrEmpty((int) $order->getAttr('id'));
        try {
            $result = self::buy($docking, $stock, $order);
        } catch (\Throwable $e) {
            // 渠道失败：完整回滚渠道标记（保证下次补单乐观 update 值有变化，affected>0 可重新提交）
            Order::where('id', (int) $order->getAttr('id'))->update([
                'docking_info'          => '',
                'docking_id'            => 0,
                'docking_type'          => '',
                'docking_goods_id'      => '0',
                'docking_goods_sku_id'  => '',
                'has_sync'              => 0,
                'update_time'           => time(),
            ]);
            self::log($order, '渠道下单失败：' . $e->getMessage());
            throw $e;
        }
        $cost = (string) ($result['total_price'] !== '' ? $result['total_price'] : $order->getAttr('total_price'));
        $update = [
            'docking_info'   => $result['ordersn'],
            'docking_status' => 1, // 已提交
            'cost_value'     => $cost,
            'update_time'    => time(),
        ];
        if ($result['card_list'] !== []) {
            // 上游同步给卡 → 直接完成
            self::saveDockingCards($order, $result['card_list']);
            $update['status']        = Order::ST_SUCCESS;
            $update['finish_time']   = time();
            $update['docking_status'] = 5; // 已回填卡密
            Order::where('id', (int) $order->getAttr('id'))->update($update);
            self::log($order, '渠道下单成功并同步回卡 ' . count($result['card_list']) . ' 张（渠道单 ' . $result['ordersn'] . '）');
        } else {
            $update['status'] = Order::ST_DEALING; // 正在处理（等轮询/回调）
            $update['recharge_hints'] = '订单正在处理中，耐心等待！';
            Order::where('id', (int) $order->getAttr('id'))->update($update);
            self::log($order, '渠道下单已提交（渠道单 ' . $result['ordersn'] . '），等待结果');
        }
    }

    /**
     * 渠道订单同步：拉上游状态推进本地订单（对齐 DockingLogic::syncOrder 语义子集）
     */
    public static function syncOrder(Order $order): array
    {
        $docking = Docking::findOrEmpty((int) $order->getAttr('docking_id'));
        if ($docking->isEmpty() || (string) $order->getAttr('docking_info') === '') {
            throw_fail('订单无有效渠道单', 1);
        }
        $rows = self::queryOrders($docking, [(string) $order->getAttr('docking_info')]);
        $remote = null;
        foreach ($rows as $r) {
            if ((string) ($r['ordersn'] ?? '') === (string) $order->getAttr('docking_info')) {
                $remote = $r;
                break;
            }
        }
        if ($remote === null) {
            throw_fail('渠道侧未查到该订单', 1);
        }
        return self::applyRemote(
            $order,
            (int) ($remote['status'] ?? 0),
            (array) ($remote['card_list'] ?? []),
            (string) ($remote['recharge_hints'] ?? ''),
            (string) ($remote['has_back_money'] ?? '0'),
            [
                'start_progress'   => (string) ($remote['start_progress'] ?? ''),
                'current_progress' => (string) ($remote['current_progress'] ?? ''),
                'target_progress'  => (string) ($remote['target_progress'] ?? ''),
            ]
        );
    }

    /**
     * 上游状态推进（拉模式 syncOrder 与推模式 回调 共用核心）
     * @param int    $remoteStatus 上游状态 1等待 2处理中 3成功 4取消 5退款
     * @param array  $cards        上游回卡 card_list
     * @param string $hints        处理信息
     * @param string $backMoney    上游已退款金额
     * @param array  $progress     进度 {start_progress, current_progress, target_progress}
     * @return array{changed:string[], remote_status:int, status:int, cards:int}
     */
    public static function applyRemote(Order $order, int $remoteStatus, array $cards, string $hints, string $backMoney, array $progress = []): array
    {
        $local   = (int) $order->getAttr('status');
        $changed = [];

        // 进度字段透传
        $progress = array_filter($progress, fn ($v) => $v !== '');
        if ($progress !== []) {
            Order::where('id', (int) $order->getAttr('id'))->update($progress + ['update_time' => time()]);
            $changed[] = '进度更新';
        }

        if ($remoteStatus === 3 && $local !== Order::ST_SUCCESS) {
            if ($cards !== [] && (string) $order->getAttr('docking_status') != 5) {
                self::saveDockingCards($order, $cards);
                Order::where('id', (int) $order->getAttr('id'))->update(['docking_status' => 5, 'update_time' => time()]);
            }
            Order::where('id', (int) $order->getAttr('id'))
                ->whereNotIn('status', [Order::ST_SUCCESS])
                ->update(['status' => Order::ST_SUCCESS, 'finish_time' => time(), 'update_time' => time()]);
            $changed[] = '渠道成功→交易成功' . ($cards !== [] ? '（补卡 ' . count($cards) . ' 张）' : '');
            // 佣金/分站抽成/供货货款统一结算出口（幂等）
            try {
                OrderService::settleAfterSuccess(Order::findOrEmpty((int) $order->getAttr('id')));
            } catch (\Throwable $e) {
                $changed[] = '结算异常：' . $e->getMessage();
            }
        } elseif ($remoteStatus === 3 && $local === Order::ST_SUCCESS && $cards !== []) {
            // 已成功订单收到补卡回调（上游部分发货后补齐）；saveDockingCards 内部按差额补卡且 md5 去重，天然幂等
            self::saveDockingCards($order, $cards);
            Order::where('id', (int) $order->getAttr('id'))->update(['docking_status' => 5, 'update_time' => time()]);
            $changed[] = '已成功订单补卡';
        } elseif ($remoteStatus === 2 && $local === Order::ST_WAIT_DEAL) {
            Order::where('id', (int) $order->getAttr('id'))->where('status', Order::ST_WAIT_DEAL)
                ->update(['status' => Order::ST_DEALING, 'update_time' => time()]);
            $changed[] = '渠道处理中→正在处理';
        } elseif ($remoteStatus === 1 && $local === Order::ST_WAIT_DEAL) {
            $changed[] = '渠道等待处理';
        } elseif (in_array($remoteStatus, [4, 5], true) && $local !== Order::ST_REFUNDED && $local !== Order::ST_CANCEL) {
            // 上游取消/退款：本地对齐 + 已收款退款
            $newStatus = $remoteStatus === 5 ? Order::ST_REFUNDED : Order::ST_CANCEL;
            $affected = Order::where('id', (int) $order->getAttr('id'))
                ->whereIn('status', [Order::ST_WAIT_DEAL, Order::ST_DEALING, Order::ST_SUCCESS])
                ->update(['status' => $newStatus, 'update_time' => time()]);
            if ($affected > 0) {
                $changed[] = '渠道' . ($remoteStatus === 5 ? '退款' : '取消') . '→本地对齐';
                // 已支付单退回余额（渠道已退，本地余额原路退）
                $uid = (int) $order->getAttr('uid');
                $paid = (string) $order->getAttr('total_price');
                if ($uid > 0 && (string) $order->getAttr('pay_type') === 'balance' && bccomp($backMoney, '0', 4) >= 0) {
                    try {
                        MemberMoneyService::changeMoney(
                            $uid, MemberMoney::T_ORDER_REFUND, MemberMoney::C_INCOME, $paid,
                            (int) $order->getAttr('id'),
                            '渠道' . ($remoteStatus === 5 ? '退款' : '取消') . '回款 ' . (string) $order->getAttr('ordersn'),
                            'DOCKREFUND:' . (string) $order->getAttr('ordersn')
                        );
                        Order::where('id', (int) $order->getAttr('id'))->update([
                            'has_back_money' => $paid, 'update_time' => time(),
                        ]);
                    } catch (\Throwable $e) {
                        $changed[] = '退款入账失败：' . $e->getMessage();
                    }
                }
            }
        }
        if ($hints !== '' && $hints !== (string) $order->getAttr('recharge_hints')) {
            Order::where('id', (int) $order->getAttr('id'))->update(['recharge_hints' => mb_substr($hints, 0, 1000, 'UTF-8')]);
        }
        $fresh = Order::findOrEmpty((int) $order->getAttr('id'));
        return [
            'changed' => $changed,
            'remote_status' => $remoteStatus,
            'status' => (int) $fresh->getAttr('status'),
            'cards' => count($cards),
        ];
    }

    /**
     * 渠道卡密入库（幂等 + 部分补卡：已有 N 张补足 quantity，card_no_md5 去重防重复入库）
     */
    protected static function saveDockingCards(Order $order, array $cardList): void
    {
        $quantity = max(1, (int) $order->getAttr('quantity'));
        $exists   = Card::where('order_id', (int) $order->getAttr('id'))->count();
        $need     = $quantity - (int) $exists;
        if ($need <= 0) {
            return; // 已足量，幂等
        }
        $existingMd5 = Card::where('order_id', (int) $order->getAttr('id'))->column('card_no_md5');
        $existingMd5 = array_filter($existingMd5);
        $rows   = [];
        $dupeMd5 = [];
        foreach ($cardList as $c) {
            if (count($rows) >= $need) {
                break; // 只补差额
            }
            $no  = trim((string) ($c['card_no'] ?? ''));
            $pwd = trim((string) ($c['card_password'] ?? ''));
            if ($pwd === '' && $no !== '') {
                [$no, $pwd] = [$pwd, $no]; // 密码空与卡号互换（对齐原系统）
            }
            $md5 = $no !== '' ? md5($no) : '';
            // 去重：本单已入库 / 本批已取
            if ($md5 !== '' && (in_array($md5, $existingMd5, true) || isset($dupeMd5[$md5]))) {
                continue;
            }
            if ($md5 !== '') {
                $dupeMd5[$md5] = true;
            }
            $rows[] = [
                'goods_id'       => (int) $order->getAttr('goods_id'),
                'order_id'       => (int) $order->getAttr('id'),
                'uid'            => (int) $order->getAttr('uid'),
                'card_no'        => $no !== '' ? Crypt::encrypt($no) : '',
                'card_password'  => $pwd !== '' ? Crypt::encrypt($pwd) : '',
                'card_cate_id'   => 0,
                'card_type'      => 1,
                'status'         => 2, // 已售
                'docking_id'     => (int) $order->getAttr('docking_id'),
                'docking_order'  => (string) $order->getAttr('docking_info'),
                'card_show_type' => 1,
                'create_time'    => time(),
                'update_time'    => time(),
                'card_no_md5'       => $md5,
                'card_password_md5' => $pwd !== '' ? md5($pwd) : '',
            ];
        }
        if ($rows === []) {
            return;
        }
        foreach (array_chunk($rows, 500) as $chunk) {
            Db::name('card')->insertAll($chunk);
        }
        OrderDolog::create([
            'docking_id'  => (int) $order->getAttr('docking_id'),
            'docking_type' => (string) $order->getAttr('docking_type'),
            'stock_id'    => 0, // 渠道卡无本地库存行
            'order_id'    => (int) $order->getAttr('id'),
            'stock_log_type' => OrderDolog::LOG_DEC,
            'mark'        => '渠道回卡 ' . count($rows) . ' 张' . ($exists > 0 ? '（补卡，原 ' . $exists . ' 张）' : ''),
            'create_time' => time(),
        ]);
    }

    /**
     * 订单日志（委托 OrderService）
     */
    protected static function log(Order $order, string $mark): void
    {
        OrderService::log($order, $mark);
    }

    /**
     * 渠道日志
     */
    protected static function writeLog(Docking $docking, int $logType, string $path, array $body, array $signed, string $res, bool $ok, string $summary, ?Order $order): void
    {
        try {
            DockingLog::create([
                'docking_id'  => (int) $docking->getAttr('id'),
                'log_type'    => $logType ?: 1,
                'ordersn'     => $order ? (string) $order->getAttr('ordersn') : '',
                'order_id'    => $order ? (int) $order->getAttr('id') : 0,
                'status'      => $ok ? 1 : 0,
                'content'     => mb_substr($path . ' ' . $summary, 0, 1990, 'UTF-8'),
                'create_time' => time(),
                'req'         => mb_substr($signed['json'], 0, 60000, 'UTF-8'),
                'head'        => json_encode($signed['headers'], JSON_UNESCAPED_UNICODE),
                'res'         => mb_substr((string) $res, 0, 60000, 'UTF-8'),
            ]);
        } catch (\Throwable $ignore) {
            // 日志失败不影响主流程
        }
    }
}
