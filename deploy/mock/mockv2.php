<?php
/**
 * MKSHOP Mock 上游（KASUSHOU v2api 协议模拟器）—— 仅用于 E2E 验收/回归
 *
 * 自验签：客户端若签名错误（sha1(13位毫秒ts+ksort(body)JSON+apikey)）即拒绝，
 * 因此可同时验证我方 DockSign 客户端实现的正确性。
 *
 * 行为：
 *   POST {host}/mockv2.php/api/v1/order/buy   → status=2（无卡，异步）
 *   POST {host}/mockv2.php/api/v1/order/info  → status=3 + card_list（数量=下单数量）
 *   POST {host}/mockv2.php/api/v1/goods/list  → 2 个 mock 商品
 *   POST {host}/mockv2.php/api/v1/user/money  → 余额 888.88
 *
 * 状态存储：/tmp/mkshop_mock_orders.json（external_orderno → {quantity, ordersn}）
 */
declare (strict_types = 1);

const MOCK_APPID = 'mksmockappid';
const MOCK_KEY   = 'mksmockapikey123';
const STATE_FILE = '/tmp/mkshop_mock_orders.json';

header('Content-Type: application/json; charset=utf-8');

$raw = file_get_contents('php://input') ?: '{}';
$sign = $_SERVER['HTTP_SIGN'] ?? '';
$ts = $_SERVER['HTTP_TIMESTAMP'] ?? '';
$userid = $_SERVER['HTTP_USERID'] ?? '';

function fail(string $msg, int $code = 400): void
{
    echo json_encode(['code' => $code, 'msg' => $msg, 'data' => []], JSON_UNESCAPED_UNICODE);
    exit;
}

// ── 验签（协议一致：sha1(ts + rawBody + key)）──
if ($sign === '' || $ts === '') {
    fail('缺少签名头');
}
if ((int) $userid !== 0 && $userid !== MOCK_APPID) {
    fail('appid 不正确');
}
if (abs(time() * 1000 - (int) $ts) > 300000) {
    fail('时间戳过期');
}
if (!hash_equals(sha1($ts . $raw . MOCK_KEY), $sign)) {
    fail('签名校验失败');
}
$body = json_decode($raw, true) ?: [];

$uri = $_SERVER['REQUEST_URI'] ?? '';
$path = parse_url($uri, PHP_URL_PATH) ?: '';
// 取 mockv2.php 之后的部分
$pos = strpos($path, 'mockv2.php');
$suffix = $pos !== false ? substr($path, $pos + strlen('mockv2.php')) : $path;

function stateRead(): array
{
    if (!file_exists(STATE_FILE)) {
        return [];
    }
    $data = json_decode((string) file_get_contents(STATE_FILE), true);
    return is_array($data) ? $data : [];
}

function stateWrite(array $data): void
{
    file_put_contents(STATE_FILE, json_encode($data, JSON_UNESCAPED_UNICODE), LOCK_EX);
}

function mockCards(string $external, int $quantity): array
{
    $cards = [];
    for ($i = 1; $i <= $quantity; $i++) {
        $cards[] = [
            'card_no'       => 'MOCKNO' . substr(md5($external . $i), 0, 10),
            'card_password' => 'MOCKPWD' . substr(md5('p' . $external . $i), 0, 16),
        ];
    }
    return $cards;
}

if (str_contains($suffix, '/order/buy')) {
    $external = (string) ($body['external_orderno'] ?? '');
    $quantity = (int) ($body['quantity'] ?? 1);
    if ($external === '') {
        fail('external_orderno 必填', 500);
    }
    // 安全价校验（协议：防调价亏本）
    $safePrice = (string) ($body['safe_price'] ?? '0');
    if (bccomp($safePrice, '0', 4) <= 0) {
        fail('safe_price 无效', 500);
    }
    $state = stateRead();
    $ordersn = 'MOCK' . date('YmdHis') . substr(md5($external), 0, 8);
    $state[$external] = ['ordersn' => $ordersn, 'quantity' => $quantity, 'create_time' => time()];
    stateWrite($state);
    echo json_encode([
        'code' => 200,
        'msg'  => '下单成功',
        'data' => [
            'ordersn'          => $ordersn,
            'external_orderno' => $external,
            'total_price'      => bcmul('0.45', (string) $quantity, 4),
            'status'           => 2, // 异步：正在处理
            'card_list'        => [],
        ],
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if (str_contains($suffix, '/order/info')) {
    $externals = array_filter(explode(',', (string) ($body['external_orderno'] ?? '')));
    $state = stateRead();
    $out = [];
    foreach ($externals as $ext) {
        if (!isset($state[$ext])) {
            continue;
        }
        $info = $state[$ext];
        $out[] = [
            'ordersn'          => $info['ordersn'],
            'external_orderno' => $ext,
            'recharge_info'    => [],
            'recharge_hints'   => '充值成功',
            'status'           => 3, // 查单即成功（模拟异步完成）
            'total_price'      => bcmul('0.45', (string) $info['quantity'], 4),
            'has_back_money'   => '0',
            'start_progress'   => '',
            'current_progress' => '',
            'target_progress'  => '',
            'card_list'        => mockCards($ext, (int) $info['quantity']),
        ];
    }
    echo json_encode(['code' => 200, 'msg' => '成功', 'data' => $out], JSON_UNESCAPED_UNICODE);
    exit;
}

if (str_contains($suffix, '/goods/list')) {
    echo json_encode([
        'code' => 200,
        'msg'  => '成功',
        'data' => [
            'list'  => [
                ['id' => 101, 'goods_name' => 'Mock 移动话费100元', 'goods_type' => 2, 'face_value' => '100.00', 'goods_price' => '0.45', 'status' => 1, 'stock_num' => 9999],
                ['id' => 102, 'goods_name' => 'Mock 联通话费50元', 'goods_type' => 2, 'face_value' => '50.00', 'goods_price' => '0.22', 'status' => 1, 'stock_num' => 5000],
            ],
            'total' => 2,
        ],
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if (str_contains($suffix, '/user/money')) {
    echo json_encode(['code' => 200, 'msg' => '成功', 'data' => ['money' => '888.88']], JSON_UNESCAPED_UNICODE);
    exit;
}

fail('未知接口：' . $suffix, 404);
