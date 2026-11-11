<?php
// +----------------------------------------------------------------------
// | 后台 · 对接渠道管理（对齐权限点 docking/*；v2api 协议驱动）
// +----------------------------------------------------------------------
declare (strict_types = 1);

namespace app\controller\admin;

use app\common\model\Docking;
use app\common\model\DockingLog;
use app\common\model\Goods;
use app\common\model\GoodsStock;
use app\common\service\DockingV2Service;
use mks\traits\CurdTrait;

class DockingController extends Base
{
    use CurdTrait;

    protected string $crudModel = Docking::class;

    protected array $crudSearchable = [
        'name'   => 'like',
        'status' => 'eq',
        'type'   => 'eq',
    ];

    protected array $crudModifiable = ['status', 'sort', 'name', 'warn', 'warn_open', 'balance'];

    protected array $crudFields = [
        'name', 'type', 'url', 'username', 'password', 'status', 'sort',
        'warn', 'warn_open', 'params', 'auto_back', 'sync_order_time', 'interval',
    ];

    protected string $crudOrder = 'sort desc, id asc';

    /**
     * 全量（下拉）
     */
    public function list()
    {
        $rows = Docking::where('status', 1)->order('sort desc, id asc')
            ->field('id,name,type,url,balance,status')
            ->select()->toArray();
        return $this->ok($rows);
    }

    /**
     * 新建：appid/apikey 加密入库（红线：上游密钥 AES-GCM）
     */
    public function create()
    {
        $data = $this->crudFormData();
        if ($data === []) {
            return $this->fail('没有需要保存的数据');
        }
        $data['type'] = $data['type'] ?: \app\common\model\Docking::DRIVER_V2;
        $data = $this->encryptCredentials($data);
        $result = $this->crudCreate($data);
        return $result instanceof \think\Response\Json ? $result : $this->ok($result, '创建成功');
    }

    /**
     * 更新
     */
    public function save(int $id)
    {
        $row = Docking::findOrEmpty($id);
        if ($row->isEmpty()) {
            return $this->fail('渠道不存在');
        }
        $data = $this->crudFormData();
        if ($data === []) {
            return $this->fail('没有需要保存的数据');
        }
        $data = $this->encryptCredentials($data);
        $row->allowField($this->crudFields)->save(array_intersect_key($data, array_flip($this->crudFields)));
        return $this->ok($row, '保存成功');
    }

    /**
     * 渠道余额（对齐 docking/user_money）
     */
    public function user_money()
    {
        $docking = Docking::findOrEmpty((int) $this->request->param('docking_id', 0));
        if ($docking->isEmpty()) {
            return $this->fail('渠道不存在');
        }
        $money = DockingV2Service::userMoney($docking);
        $docking->save(['balance' => $money]);
        return $this->ok(['money' => $money], '查询成功');
    }

    /**
     * 渠道商品列表（对齐 docking/goods_list）
     */
    public function goods_list()
    {
        $docking = Docking::findOrEmpty((int) $this->request->param('docking_id', 0));
        if ($docking->isEmpty()) {
            return $this->fail('渠道不存在');
        }
        $data = DockingV2Service::goodsList(
            $docking,
            max(1, (int) $this->request->param('page', 1)),
            (string) $this->request->param('keyword', '')
        );
        return $this->ok($data);
    }

    /**
     * 渠道商品对接（对齐 docking/goods_dual）：本地商品绑定渠道商品行（goods_stock type=2）
     * POST {docking_id, goods_id, docking_goods_id, docking_goods_name?, cost_value?, docking_goods_sku_id?}
     */
    public function goods_dual()
    {
        $dockingId = (int) $this->request->param('docking_id', 0);
        $goodsId   = (int) $this->request->param('goods_id', 0);
        $dockingGoodsId = (string) $this->request->param('docking_goods_id', '');
        $docking = Docking::findOrEmpty($dockingId);
        $goods   = Goods::findOrEmpty($goodsId);
        if ($docking->isEmpty() || $goods->isEmpty() || $dockingGoodsId === '') {
            return $this->fail('参数不完整');
        }
        $stock = GoodsStock::where('goods_id', $goodsId)->where('type', 2)->findOrEmpty();
        $data  = [
            'uid'          => 0,
            'type'         => 2,
            'name'         => (string) ($this->request->param('docking_goods_name', '') ?: $goods->getAttr('goods_name')),
            'goods_id'     => $goodsId,
            'docking_id'   => $dockingId,
            'docking_goods_id' => $dockingGoodsId,
            'docking_goods_sku_id' => (string) $this->request->param('docking_goods_sku_id', ''),
            'cost_value'   => (string) $this->request->param('cost_value', '0'),
            'goods_type'   => (int) $goods->getAttr('goods_type'),
            'status'       => 1,
        ];
        if ($stock->isEmpty()) {
            $stock = new GoodsStock();
            $data['create_time'] = time();
            $stock->save($data);
        } else {
            $stock->save($data);
        }
        // 商品供货方式置渠道
        Goods::where('id', $goodsId)->update(['provide_type' => 6]);
        return $this->ok($stock, '已对接渠道商品');
    }

    /**
     * 渠道日志（监控日志对接 dockinglog/*）
     */
    public function log_index()
    {
        $page  = max(1, (int) $this->request->param('page', 1));
        $limit = min(100, max(1, (int) $this->request->param('limit', 15)));
        $query = DockingLog::order('id desc');
        $dockingId = (int) $this->request->param('docking_id', 0);
        if ($dockingId > 0) {
            $query = $query->where('docking_id', $dockingId);
        }
        $total = (clone $query)->count();
        $list  = $query->page($page, $limit)
            ->field('id,docking_id,log_type,ordersn,order_id,status,content,create_time')
            ->select()->toArray();
        return $this->ok(['list' => $list, 'count' => $total]);
    }

    /**
     * appid/apikey 加密
     */
    protected function encryptCredentials(array $data): array
    {
        if (array_key_exists('username', $data) && $data['username'] !== '' && !str_starts_with((string) $data['username'], 'mksv1:')) {
            $data['username'] = \mks\util\Crypt::encrypt((string) $data['username']);
        }
        if (array_key_exists('password', $data) && $data['password'] !== '' && !str_starts_with((string) $data['password'], 'mksv1:')) {
            $data['password'] = \mks\util\Crypt::encrypt((string) $data['password']);
        }
        return $data;
    }
}
