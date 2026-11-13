<?php
// +----------------------------------------------------------------------
// | 后台 · 售后/投诉管理（M7 对齐 KASUSHOU 投诉管理页）
// | dual 语义：action=handle（处理，可联动退款）/cancel（撤销工单）
// +----------------------------------------------------------------------
declare (strict_types = 1);

namespace app\controller\admin;

use app\common\model\Complain;
use app\common\model\ComplainType;
use app\common\model\Order;
use app\common\service\OrderService;
use mks\traits\CurdTrait;

class ComplainController extends Base
{
    use CurdTrait;

    protected string $crudModel = Complain::class;

    protected array $crudSearchable = [
        'ordersn'     => 'like',
        'subject'     => 'like',
        'nickname'    => 'like',
        'status'      => 'eq',
        'type_id'     => 'eq',
        'supplier_type' => 'eq',
        'order_id'    => 'eq',
        'create_time' => 'between',
    ];

    protected array $crudModifiable = ['status', 'add_count'];

    protected array $crudFields = ['status'];

    protected string $crudOrder = 'id desc';

    /**
     * 售后类型全量（筛选下拉）
     */
    public function type_list()
    {
        return $this->ok(ComplainType::where('status', 1)->where('delete_time', 0)->order('sort desc, id asc')->select());
    }

    /**
     * 列表补充：订单状态/商品信息/状态文案
     */
    public function index()
    {
        $result = $this->crudIndex();
        $data   = $result->getData();
        $list   = (array) ($data['data']['list'] ?? []);
        $orderIds = array_values(array_filter(array_column($list, 'order_id')));
        $orderMap = $orderIds === [] ? [] : Order::whereIn('id', $orderIds)
            ->column('ordersn,status,goods_name,total_price', 'id');
        $statusText = [1 => '待处理', 2 => '已处理', 3 => '已撤销'];
        foreach ($list as &$row) {
            $order = $orderMap[(int) ($row['order_id'] ?? 0)] ?? null;
            $row['order_status']     = $order ? (int) $order['status'] : -9;
            $row['order_goods_name'] = (string) ($order['goods_name'] ?? '');
            $row['order_total_price'] = (string) ($order['total_price'] ?? '0');
            $row['status_text']      = $statusText[(int) ($row['status'] ?? 0)] ?? (string) $row['status'];
        }
        unset($row);
        $data['data']['list'] = $list;
        return json_ok($data['data'], $data['msg']);
    }

    /**
     * 详情（含订单快照）
     */
    public function read(int $id)
    {
        $row = Complain::findOrEmpty($id);
        if ($row->isEmpty()) {
            return $this->fail('售后单不存在');
        }
        $order = Order::findOrEmpty((int) $row->getAttr('order_id'));
        return $this->ok([
            'complain' => $row->toArray(),
            'order'    => $order->isEmpty() ? null : $order->toArray(),
        ]);
    }

    /**
     * 处理售后
     * POST {action: 'handle'|'cancel', result: 'agree'|'refuse', money?, mark?}
     * result=agree 且订单可退款时触发 OrderService::refund（金额默认全额）
     */
    public function dual(int $id)
    {
        $row = Complain::findOrEmpty($id);
        if ($row->isEmpty()) {
            return $this->fail('售后单不存在');
        }
        if ((int) $row->getAttr('status') !== Complain::ST_WAIT) {
            return $this->fail('该售后单已处理');
        }
        $action = (string) $this->request->param('action', 'handle');
        $mark   = (string) $this->request->param('mark', '');
        $order  = Order::findOrEmpty((int) $row->getAttr('order_id'));

        if ($action === 'cancel') {
            $row->save(['status' => Complain::ST_CANCELED, 'update_time' => time()]);
            if (!$order->isEmpty() && (int) $order->getAttr('status') === Order::ST_SUCCESS) {
                OrderService::refund($order, '0', $this->adminId(), '售后撤销退款 ' . $mark);
            }
            return $this->ok([], '已撤销');
        }

        $result = (string) $this->request->param('result', 'refuse');
        if ($result === 'agree') {
            if ($order->isEmpty() || (int) $order->getAttr('status') !== Order::ST_SUCCESS) {
                return $this->fail('订单当前状态不可退款');
            }
            $money = (string) $this->request->param('money', '0');
            OrderService::refund($order, $money, $this->adminId(), '售后同意退款 ' . $mark);
        }
        $row->save(['status' => Complain::ST_DONE, 'update_time' => time()]);
        return $this->ok([], '处理完成');
    }

    /**
     * 售后记录导出（对齐 KASUSHOU「售后记录导出」）：CSV，最多 5000 行
     */
    public function export()
    {
        $rows = $this->crudQuery(new Complain())->limit(5000)->select()->toArray();
        $statusText = [1 => '待处理', 2 => '已处理', 3 => '已撤销'];
        $typeText   = [1 => '自营', 2 => '商家', 3 => '渠道'];
        $header = ['ID', '售后主题', '订单号', '商品', '用户', '金额', '来源', '售后状态', '投诉内容', '售后时间'];
        return response_csv('complain_' . date('Ymd_His') . '.csv', $header, array_map(function ($r) use ($statusText, $typeText) {
            return [
                $r['id'], (string) $r['subject'], (string) $r['ordersn'], (string) $r['goods_name'],
                (string) $r['nickname'], (string) $r['total_price'],
                $typeText[(int) ($r['supplier_type'] ?? 0)] ?? (string) $r['supplier_type'],
                $statusText[(int) ($r['status'] ?? 0)] ?? (string) $r['status'],
                mb_substr((string) $r['content'], 0, 100, 'UTF-8'),
                date('Y-m-d H:i:s', (int) $r['create_time']),
            ];
        }, $rows));
    }
}
