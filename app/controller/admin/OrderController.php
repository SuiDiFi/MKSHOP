<?php
// +----------------------------------------------------------------------
// | 后台 · 订单管理（对齐权限点 order/*）
// dual 语义：3=处理成功(自动发货重试/直接成功) 2=处理中 -1=取消待支付单 5=退款
// +----------------------------------------------------------------------
declare (strict_types = 1);

namespace app\controller\admin;

use app\common\model\Card;
use app\common\model\Order;
use app\common\model\OrderLog;
use app\common\model\PayList;
use app\common\service\OrderService;
use mks\traits\CurdTrait;
use think\facade\Db;

class OrderController extends Base
{
    use CurdTrait;

    protected string $crudModel = Order::class;

    protected array $crudSearchable = [
        'ordersn'      => 'like',
        'status'       => 'eq',
        'uid'          => 'eq',
        'goods_id'     => 'eq',
        'pay_type'     => 'eq',
        'goods_name'   => 'like',
        'recharge_account' => 'like',
        'docking_id'   => 'eq',
        // M7 对齐 KASUSHOU 订单列表筛选颗粒度
        'create_time'   => 'between',
        'finish_time'   => 'between',
        'pay_time'      => 'between',
        'user_where'    => 'eq',
        'goods_type'    => 'eq',
        // 归属页签（1 自营 / 5 供货 / 6 渠道）：前端已发该条件，缺登记会被 where 白名单静默丢弃
        'provide_type'  => 'eq',
        'supplier_id'   => 'eq',
        'vip_id'        => 'eq',
        'total_price'   => 'between',
        'cost_value'    => 'between',
        'is_fail'       => 'eq',
        'has_back_money' => 'gt',
        'quantity'      => 'eq',
    ];

    protected array $crudModifiable = ['admin_mark', 'recharge_hints'];

    protected array $crudFields = ['admin_mark', 'reader', 'handler'];

    protected string $crudOrder = 'id desc';

    protected array $crudOrderable = ['id', 'create_time', 'total_price', 'pay_time'];

    /**
     * 列表：状态文案映射补充（对齐 KASUSHOU 状态页签文案）
     */
    public function index()
    {
        $result = $this->crudIndex();
        $data   = $result->getData();
        $statusText = [
            -1 => '取消支付', 0 => '等待支付', 1 => '等待处理', 2 => '正在处理',
            3 => '交易成功', 4 => '取消交易', 5 => '已经退款',
        ];
        $list = (array) ($data['data']['list'] ?? []);
        foreach ($list as &$row) {
            $row['status_text'] = $statusText[(int) ($row['status'] ?? 0)] ?? (string) $row['status'];
            // 异常订单标记（KASUSHOU「异常订单」页签语义）
            $row['is_fail_text'] = (int) ($row['is_fail'] ?? 0) === 1 ? '异常' : '';
            // 部分退款标记：有回款且未走完退款流程
            $row['is_part_back'] = (float) ($row['has_back_money'] ?? 0) > 0
                && bccomp((string) ($row['has_back_money'] ?? '0'), (string) ($row['total_price'] ?? '0'), 4) < 0 ? 1 : 0;
        }
        unset($row);
        $data['data']['list'] = $list;
        return json_ok($data['data'], $data['msg']);
    }

    /**
     * 订单导出（对齐 KASUSHOU「导出订单」）：CSV，最多 5000 行
     * GET 参数与 index 一致（where/page 忽略，按条件全量取前 5000 条）
     */
    public function export()
    {
        $rows = $this->crudQuery(new Order())
            ->limit(5000)
            ->select()
            ->toArray();
        $statusText = [
            -1 => '取消支付', 0 => '等待支付', 1 => '等待处理', 2 => '正在处理',
            3 => '交易成功', 4 => '取消交易', 5 => '已经退款',
        ];
        $header = ['ID', '订单号', '状态', '商品名称', '下单参数', '数量', '单价', '总价', '成本', '利润',
            '买家', '用户ID', '支付方式', '下单来源', '供货商', '分站ID', '下单IP', '下单时间', '支付时间', '完成时间'];
        return response_csv('orders_' . date('Ymd_His') . '.csv', $header, array_map(function ($r) use ($statusText) {
            return [
                $r['id'], $r['ordersn'], $statusText[(int) $r['status']] ?? (string) $r['status'],
                $r['goods_name'], (string) $r['recharge_account'], $r['quantity'], $r['price'], $r['total_price'],
                $r['cost_value'], $r['order_price'], $r['nickname'], $r['uid'], $r['pay_type'],
                (string) $r['user_where'], (string) $r['supplier_name'], $r['vip_id'], (string) $r['ip'],
                date('Y-m-d H:i:s', (int) $r['create_time']),
                (int) $r['pay_time'] > 0 ? date('Y-m-d H:i:s', (int) $r['pay_time']) : '',
                (int) $r['finish_time'] > 0 ? date('Y-m-d H:i:s', (int) $r['finish_time']) : '',
            ];
        }, $rows));
    }

    /**
     * 订单详情（含操作日志）
     */
    public function info(int $id)
    {
        $order = Order::findOrEmpty($id);
        if ($order->isEmpty()) {
            return $this->fail('订单不存在');
        }
        $logs = OrderLog::where('order_id', $id)->order('id asc')->select()->toArray();
        return $this->ok(['order' => $order, 'logs' => $logs]);
    }

    /**
     * 订单卡密明细（明文，对齐 order/goods）
     */
    public function goods(int $id)
    {
        $order = Order::findOrEmpty($id);
        if ($order->isEmpty()) {
            return $this->fail('订单不存在');
        }
        $cards = Card::where('order_id', $id)->field('id,card_no,card_password,status,create_time')->select()->toArray();
        return $this->ok($cards);
    }

    /**
     * 订单处理（对齐 order/dual）
     * POST {status: 3|2|-1|5, money?, remark?}
     */
    public function dual(int $id)
    {
        $order = Order::findOrEmpty($id);
        if ($order->isEmpty()) {
            return $this->fail('订单不存在');
        }
        $status = (int) $this->request->param('status', 0);
        if ($status === 5) {
            $money  = (string) $this->request->param('money', '0');
            OrderService::refund($order, $money, $this->adminId(), (string) $this->request->param('remark', ''));
            return $this->ok([], '退款成功');
        }
        OrderService::dualOrder($order, $status, [
            'mark' => (string) $this->request->param('remark', ''),
        ], $this->adminId());
        $fresh = Order::findOrEmpty($id);
        return $this->ok(['status' => (int) $fresh->getAttr('status')], '处理成功');
    }

    /**
     * 订单同步（对齐 order/sync_order）：拉渠道侧状态推进本地订单
     */
    public function sync_order(int $id)
    {
        $order = Order::findOrEmpty($id);
        if ($order->isEmpty()) {
            return $this->fail('订单不存在');
        }
        $result = \app\common\service\DockingV2Service::syncOrder($order);
        return $this->ok($result, $result['changed'] === [] ? '渠道无变更' : implode('；', $result['changed']));
    }

    /**
     * 补单（对齐 order/do_get_order）
     * POST {ids: 1,2,3 | ordersn: xxx}
     * 已提交渠道单 → 拉取渠道状态；未提交 → 按商品渠道库存重新提交渠道下单
     */
    public function do_get_order()
    {
        $orders = $this->resolveOrders();
        if ($orders === []) {
            return $this->fail('未找到可补订单');
        }
        $list = [];
        foreach ($orders as $order) {
            if (!in_array((int) $order->getAttr('status'), [Order::ST_WAIT_DEAL, Order::ST_DEALING], true)) {
                $list[] = ['id' => (int) $order->getAttr('id'), 'status' => false, 'msg' => '订单当前状态不可补单'];
                continue;
            }
            try {
                if ((string) $order->getAttr('docking_info') !== '') {
                    $result = \app\common\service\DockingV2Service::syncOrder($order);
                    $msg = $result['changed'] === [] ? '渠道无变更' : implode('；', $result['changed']);
                } else {
                    $stock = \app\common\model\GoodsStock::where('goods_id', (int) $order->getAttr('goods_id'))
                        ->where('type', 2)->findOrEmpty();
                    if ($stock->isEmpty()) {
                        throw_fail('该订单商品未配置渠道库存', 1);
                    }
                    \app\common\service\DockingV2Service::deliver($order, $stock);
                    $msg = '已重新提交渠道下单';
                    OrderService::log($order, '后台补单：' . $msg);
                }
                $list[] = ['id' => (int) $order->getAttr('id'), 'status' => true, 'msg' => $msg];
            } catch (\Throwable $e) {
                OrderService::log($order, '后台补单失败：' . $e->getMessage());
                $list[] = ['id' => (int) $order->getAttr('id'), 'status' => false, 'msg' => $e->getMessage()];
            }
        }
        $ok = count(array_filter($list, fn ($r) => $r['status'] === true));
        return $this->ok(['list' => $list], "补单完成（成功 {$ok} / 共 " . count($list) . '）');
    }

    /**
     * 停止补单（对齐 order/no_bu_order）
     * POST {ids: 1,2,3 | ordersn: xxx}
     * 置 order.order_bu_count=0 即退出自动补单扫描（goods 侧不受影响）
     */
    public function no_bu_order()
    {
        $orders = $this->resolveOrders();
        if ($orders === []) {
            return $this->fail('订单不存在');
        }
        $done = 0;
        foreach ($orders as $order) {
            $affected = \think\facade\Db::name('order')
                ->where('id', (int) $order->getAttr('id'))
                ->where('order_bu_count', '>', 0)
                ->update(['order_bu_count' => 0, 'update_time' => time()]);
            if ($affected > 0) {
                $done++;
                OrderService::log($order, '订单撤单不再自动补单');
            }
        }
        return $this->ok(['count' => $done], '停止补单成功');
    }

    /**
     * 解析 ids（逗号分隔）/ ordersn 为订单集合
     * @return Order[]
     */
    protected function resolveOrders(): array
    {
        $ordersn = trim((string) $this->request->param('ordersn', ''));
        $query = Order::where('delete_time', 0);
        if ($ordersn !== '') {
            $query = $query->where('ordersn', $ordersn);
        } else {
            $raw = (string) $this->request->param('ids', '');
            $ids = array_values(array_filter(array_map('intval', explode(',', $raw))));
            if ($ids === []) {
                return [];
            }
            $query = $query->whereIn('id', $ids);
        }
        $rows = [];
        foreach ($query->select() as $row) {
            $rows[] = $row;
        }
        return $rows;
    }

    /**
     * 状态枚举（对齐 order/status_type，取自 sys_dict）
     */
    public function status_type()
    {
        return $this->ok(\app\common\model\SysDict::where('dict_type', 'order_status')
            ->where('status', 1)->order('sort asc')->field('code,label,color')->select()->toArray());
    }

    /**
     * 页签数量角标（对齐 KASUSHOU 订单列表「所有订单 10612 / 平台订单 …」）
     * 口径：近 N 天创建且未软删的订单（默认 30 天，与列表默认区间一致）
     * 简化说：不跟随当前筛选条件联动（要联动需把 CurdTrait 的 where DSL 复用进聚合查询，列入后续）
     * GET order/status_counts?days=30
     */
    public function statusCounts()
    {
        $days = (int) $this->request->param('days', 30);
        $base = function () use ($days) {
            $q = Db::name('order')->where('delete_time', 0);
            if ($days > 0) {
                $q->where('create_time', '>=', time() - $days * 86400);
            }
            return $q;
        };

        $byStatus = [];
        foreach ($base()->field('status, COUNT(*) cnt')->group('status')->select()->toArray() as $r) {
            $byStatus[(string) $r['status']] = (int) $r['cnt'];
        }
        $byProvide = [];
        foreach ($base()->field('provide_type, COUNT(*) cnt')->group('provide_type')->select()->toArray() as $r) {
            $byProvide[(string) $r['provide_type']] = (int) $r['cnt'];
        }

        return $this->ok([
            'days'        => $days,
            'total'       => array_sum($byStatus),
            'by_status'   => $byStatus,
            'by_provide'  => $byProvide,
            // 部分退款：已退款金额>0 且交易成功
            'part_refund' => $base()->where('has_back_money', '>', 0)->where('status', 3)->count(),
            // 异常订单
            'fail'        => $base()->where('is_fail', 1)->count(),
        ]);
    }

    /**
     * 支付方式枚举（对齐 order/pay_type，取自 pay_list）
     */
    public function pay_type()
    {
        return $this->ok(PayList::where('status', 1)->order('sort desc')->field('id,name,title,icon')->select()->toArray());
    }

    /**
     * 删除（软删，对齐 order/delete）
     */
    public function delete(int $id)
    {
        $order = Order::findOrEmpty($id);
        if ($order->isEmpty()) {
            return $this->fail('订单不存在');
        }
        $order->delete();
        return $this->ok([], '删除成功');
    }
}
