<?php
// +----------------------------------------------------------------------
// | Mock 上游控制器（KASUSHOU v2api 协议模拟器）—— 仅用于 E2E 验收/回归
// 自验签：客户端签名错误（sha1(13位毫秒ts+ksort(body)JSON+apikey)）即拒绝，
// 可同时验证我方 DockSign 客户端实现的正确性。
// 行为（对齐 docs/kasushou-v2api）：
//   POST /mockv2/api/v1/order/buy   → status=2（无卡，异步）+ 记录 state
//   POST /mockv2/api/v1/order/info  → status=3 + card_list（数量=下单数量）
//   POST /mockv2/api/v1/goods/list  → 2 个 mock 商品
//   POST /mockv2/api/v1/user/money  → 余额 888.88
// 状态存储：runtime/mock_orders.json
// +----------------------------------------------------------------------
declare (strict_types = 1);

namespace app\controller\mock;

use mks\BaseController;
use mks\util\DockSign;
use think\facade\Config;

class MockV2Controller extends BaseController
{
    private const MOCK_APPID = 'mksmockappid';
    private const MOCK_KEY   = 'mksmockapikey123';

    private const MOCK_GOODS = [
        ['id' => 101, 'goods_name' => 'Mock 移动话费100元', 'goods_type' => 2, 'face_value' => '100.00', 'goods_price' => '0.45', 'status' => 1, 'stock_num' => 9999],
        ['id' => 102, 'goods_name' => 'Mock 联通话费50元', 'goods_type' => 2, 'face_value' => '50.00', 'goods_price' => '0.22', 'status' => 1, 'stock_num' => 5000],
    ];

    /**
     * 验签 + 分发（raw body 原样参与验签）
     */
    protected function dispatch(string $action)
    {
        $raw  = (string) $this->request->getContent();
        $sign = (string) $this->request->header('sign', '');
        $ts   = (string) $this->request->header('timestamp', '');
        $uid  = (string) $this->request->header('userid', '');

        if ($sign === '' || $ts === '') {
            return $this->mockFail('缺少签名头');
        }
        if ($uid !== self::MOCK_APPID) {
            return $this->mockFail('appid 不正确');
        }
        if (!DockSign::verify($raw, $sign, $ts, self::MOCK_KEY)) {
            return $this->mockFail('签名校验失败');
        }
        $body = json_decode($raw !== '' ? $raw : '{}', true) ?: [];

        return match ($action) {
            'order/buy'   => $this->doBuy($body),
            'order/info'  => $this->doInfo($body),
            'goods/list'  => $this->doGoodsList(),
            'user/money'  => $this->doMoney(),
            default       => $this->mockFail('未知接口：' . $action, 404),
        };
    }

    public function buy()
    {
        return $this->dispatch('order/buy');
    }

    public function info()
    {
        return $this->dispatch('order/info');
    }

    public function goodsList()
    {
        return $this->dispatch('goods/list');
    }

    public function money()
    {
        return $this->dispatch('user/money');
    }

    private function doBuy(array $body)
    {
        $external = (string) ($body['external_orderno'] ?? '');
        $quantity = max(1, (int) ($body['quantity'] ?? 1));
        if ($external === '') {
            return $this->mockFail('external_orderno 必填', 500);
        }
        if (bccomp((string) ($body['safe_price'] ?? '0'), '0', 4) <= 0) {
            return $this->mockFail('safe_price 无效', 500);
        }
        $state   = $this->stateRead();
        $ordersn = 'MOCK' . date('YmdHis') . substr(md5($external), 0, 8);
        $state[$external] = ['ordersn' => $ordersn, 'quantity' => $quantity, 'create_time' => time()];
        $this->stateWrite($state);
        return $this->mockOk('下单成功', [
            'ordersn'          => $ordersn,
            'external_orderno' => $external,
            'total_price'      => bcmul('0.45', (string) $quantity, 4),
            'status'           => 2, // 异步：正在处理
            'card_list'        => [],
        ]);
    }

    private function doInfo(array $body)
    {
        // 协议二选一：external_orderno（我方单号）/ ordersn（上游单号），逗号分隔批量
        $externals = array_values(array_filter(explode(',', (string) ($body['external_orderno'] ?? ''))));
        $orderSns  = array_values(array_filter(explode(',', (string) ($body['ordersn'] ?? ''))));
        $state     = $this->stateRead();
        if ($orderSns !== []) {
            // ordersn 反查
            $bySn = [];
            foreach ($state as $ext => $info) {
                $bySn[$info['ordersn']] = $ext;
            }
            $externals = [];
            foreach ($orderSns as $sn) {
                if (isset($bySn[$sn])) {
                    $externals[] = $bySn[$sn];
                }
            }
        }
        $out = [];
        foreach ($externals as $ext) {
            if (!isset($state[$ext])) {
                continue;
            }
            $info  = $state[$ext];
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
                'card_list'        => $this->mockCards($ext, (int) $info['quantity']),
            ];
        }
        return $this->mockOk('成功', $out);
    }

    private function doGoodsList()
    {
        return $this->mockOk('成功', ['list' => self::MOCK_GOODS, 'total' => count(self::MOCK_GOODS)]);
    }

    private function doMoney()
    {
        return $this->mockOk('成功', ['money' => '888.88']);
    }

    private function mockCards(string $external, int $quantity): array
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

    private function stateRead(): array
    {
        $raw = \think\facade\Cache::store('redis')->get('mock:v2:orders');
        $data = $raw ? json_decode((string) $raw, true) : [];
        return is_array($data) ? $data : [];
    }

    private function stateWrite(array $data): void
    {
        \think\facade\Cache::store('redis')->set('mock:v2:orders', json_encode($data, JSON_UNESCAPED_UNICODE), 86400);
    }

    private function mockOk(string $msg, array $data)
    {
        return json(['code' => 200, 'msg' => $msg, 'data' => $data], 200, ['Content-Type' => 'application/json; charset=utf-8']);
    }

    private function mockFail(string $msg, int $code = 400)
    {
        return json(['code' => $code, 'msg' => $msg, 'data' => []], 200, ['Content-Type' => 'application/json; charset=utf-8']);
    }
}
