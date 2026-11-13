<?php
// +----------------------------------------------------------------------
// | 后台 · 商品管理（对齐权限点 goods/*，M1 核心子集）
// 状态机：1销售中 2暂停中 3已下架 4待审核 5已拒绝 6已售罄 7已预警 8回收站 9已审核
// 删除语义：delete=入回收站(status=8) → destroy=软删(delete_time) / restore=还原(status=1)
// +----------------------------------------------------------------------
declare (strict_types = 1);

namespace app\controller\admin;

use app\common\model\Goods;
use app\common\model\GoodsCategory;
use app\common\model\GoodsStock;
use app\common\model\GoodsTags;
use app\common\model\SysDict;
use mks\traits\CurdTrait;
use think\facade\Db;

class GoodsController extends Base
{
    use CurdTrait;

    protected string $crudModel = Goods::class;

    protected array $crudSearchable = [
        'goods_name'   => 'like',
        'goods_no'     => 'like',
        'status'       => 'eq',
        'goods_type'   => 'eq',
        'provide_type' => 'eq',
        'check_status' => 'eq',
        'uid'          => 'eq',
        'tags_id'      => 'eq',
        // M7 对齐 KASUSHOU 商品列表筛选颗粒度
        'spec_type'      => 'eq',
        'goods_price_id' => 'eq',
        'only_balance'   => 'eq',
        'need_balance'   => 'eq',
        'vip_id'         => 'eq',
        'cost_value'     => 'between',
        'real_price'     => 'between',
        'create_time'    => 'between',
        'docking_goods_id' => 'eq',
        'tpl_id'         => 'eq',
        'id'             => 'in', // M7 用户权限模板按已绑定商品ID回显
    ];

    protected array $crudModifiable = [
        'status', 'sort', 'ishot', 'real_price', 'face_value', 'cost_value',
        'goods_name', 'goods_short_name', 'start_count', 'end_count', 'warn_num',
    ];

    protected array $crudFields = [
        'goods_no', 'goods_name', 'goods_short_name', 'goods_name_blod_flag', 'goods_name_color',
        'ishot', 'goods_type', 'start_count', 'end_count', 'num_count', 'multiple',
        'face_value', 'real_price', 'cost_value', 'goods_info', 'goods_notice', 'order_notice',
        'recharge_url', 'demo_url', 'recharge_time_type', 'recharge_time_value',
        'status', 'stop_reason', 'goods_imgs', 'goods_img', 'provide_type', 'category_ids',
        'tags_id', 'check_status', 'virtual_num', 'stock_num', 'warn_num', 'sort',
        'only_balance', 'can_back', 'is_batch', 'goods_tags', 'is_recharge', 'tpl_id',
    ];

    protected string $crudOrder = 'sort desc, id desc';

    protected array $crudOrderable = ['sort', 'id', 'create_time', 'real_price', 'sales_num', 'stock_num'];

    /**
     * 列表补充：分类名/品牌名映射（前端渲染用）
     * 支持 category_id 特例：FIND_IN_SET 精确匹配逗号分隔的分类串（对齐 KASUSHOU 商品分类筛选）
     */
    public function index()
    {
        $categoryId = (int) $this->request->param('category_id', 0);
        $data = $categoryId > 0
            ? ['data' => $this->pagedWithCategory($categoryId), 'msg' => 'ok']
            : $this->crudIndex()->getData();
        $list   = (array) ($data['data']['list'] ?? []);
        if ($list !== []) {
            $catIds = [];
            $tagIds = [];
            foreach ($list as $row) {
                foreach (explode(',', (string) ($row['category_ids'] ?? '')) as $cid) {
                    if ((int) $cid > 0) {
                        $catIds[] = (int) $cid;
                    }
                }
                if ((int) ($row['tags_id'] ?? 0) > 0) {
                    $tagIds[] = (int) $row['tags_id'];
                }
            }
            $catMap = $catIds === [] ? [] : GoodsCategory::whereIn('id', array_unique($catIds))->column('name', 'id');
            $tagMap = $tagIds === [] ? [] : GoodsTags::whereIn('id', array_unique($tagIds))->column('name', 'id');
            foreach ($list as &$row) {
                $row['category_names'] = implode('、', array_map(
                    fn ($cid) => (string) ($catMap[(int) $cid] ?? ''),
                    array_filter(explode(',', (string) ($row['category_ids'] ?? '')), fn ($v) => (int) $v > 0)
                ));
                $row['tags_name'] = (string) ($tagMap[(int) ($row['tags_id'] ?? 0)] ?? '');
            }
            unset($row);
        }
        $data['data']['list'] = $list;
        return json_ok($data['data'], $data['msg']);
    }

    /**
     * 分类精确筛选分页（FIND_IN_SET；仅分类筛选时走此分支，避免 like 误匹配多级 ID）
     */
    protected function pagedWithCategory(int $categoryId): array
    {
        $page  = max(1, (int) $this->request->param('page', 1));
        $limit = min(200, max(1, (int) $this->request->param('limit', 15)));
        $query = $this->crudQuery($this->crudModel())
            ->whereRaw('FIND_IN_SET(' . $categoryId . ', category_ids)');
        $total = (clone $query)->count();
        $list  = $query->page($page, $limit)->select()->toArray();
        return ['list' => $list, 'count' => $total];
    }

    /**
     * 商品导出（对齐 KASUSHOU「导出」）：CSV，最多 5000 行
     */
    public function export()
    {
        $categoryId = (int) $this->request->param('category_id', 0);
        $query = $this->crudQuery(new Goods());
        if ($categoryId > 0) {
            $query = $query->whereRaw('FIND_IN_SET(' . $categoryId . ', category_ids)');
        }
        $rows = $query->limit(5000)->select()->toArray();
        $typeMap = [1 => '卡密', 2 => '手工', 3 => '自动', 4 => '实物'];
        // 导出行做分类/品牌名映射（原始行只有 ID 串）
        $catIds = $tagIds = [];
        foreach ($rows as $r) {
            foreach (explode(',', (string) ($r['category_ids'] ?? '')) as $cid) {
                if ((int) $cid > 0) $catIds[] = (int) $cid;
            }
            if ((int) ($r['tags_id'] ?? 0) > 0) $tagIds[] = (int) $r['tags_id'];
        }
        $catMap = $catIds === [] ? [] : GoodsCategory::whereIn('id', array_unique($catIds))->column('name', 'id');
        $tagMap = $tagIds === [] ? [] : GoodsTags::whereIn('id', array_unique($tagIds))->column('name', 'id');
        $header = ['商品ID', '商品编号', '商品名称', '分类', '品牌', '类型', '供货方式', '定价成本', '面值',
            '售价', '库存', '销量', '所属供货商ID', '分站ID', '状态', '创建时间'];
        return response_csv('goods_' . date('Ymd_His') . '.csv', $header, array_map(function ($r) use ($typeMap, $catMap, $tagMap) {
            $cats = implode('、', array_map(
                fn ($cid) => (string) ($catMap[(int) $cid] ?? ''),
                array_filter(explode(',', (string) ($r['category_ids'] ?? '')), fn ($v) => (int) $v > 0)
            ));
            return [
                $r['id'], (string) $r['goods_no'], $r['goods_name'], $cats,
                (string) ($tagMap[(int) ($r['tags_id'] ?? 0)] ?? ''), $typeMap[(int) $r['goods_type']] ?? (string) $r['goods_type'],
                (string) $r['provide_type'], $r['cost_value'], $r['face_value'], $r['real_price'],
                $r['stock_num'], $r['sales_num'], $r['uid'], $r['vip_id'], (string) $r['status'],
                date('Y-m-d H:i:s', (int) $r['create_time']),
            ];
        }, $rows));
    }

    /**
     * 新建：默认值兜底（编号/审核态/分类冗余）
     */
    public function create()
    {
        $data = $this->crudFormData();
        if ($data === []) {
            return $this->fail('没有需要保存的数据');
        }
        $data['goods_no'] ??= '';
        if ($data['goods_no'] === '') {
            $data['goods_no'] = 'G' . date('Ymd') . str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
        }
        $data['create_time'] = time();
        // 表内 NOT NULL 且无默认值的 text 列兜底（严格 SQL 模式下缺列即 1364）
        foreach (['goods_info', 'goods_notice', 'order_notice', 'category_ids'] as $f) {
            if (!array_key_exists($f, $data)) {
                $data[$f] = '';
            }
        }
        return $this->doSave(new Goods(), $data, '创建成功');
    }

    /**
     * 更新
     */
    public function save(int $id)
    {
        $row = Goods::findOrEmpty($id);
        if ($row->isEmpty()) {
            return $this->fail('商品不存在');
        }
        $data = $this->crudFormData();
        if ($data === []) {
            return $this->fail('没有需要保存的数据');
        }
        return $this->doSave($row, $data, '保存成功');
    }

    /**
     * 入回收站（status=8）
     */
    public function delete(int $id)
    {
        $row = Goods::findOrEmpty($id);
        if ($row->isEmpty()) {
            return $this->fail('商品不存在');
        }
        $row->save(['status' => 8, 'status_time' => time()]);
        return $this->ok([], '已移入回收站');
    }

    /**
     * 彻底删除（软删）
     */
    public function destroy(int $id)
    {
        $row = Goods::findOrEmpty($id);
        if ($row->isEmpty()) {
            return $this->fail('商品不存在');
        }
        $row->delete();
        return $this->ok([], '已彻底删除');
    }

    /**
     * 回收站还原
     */
    public function restore(int $id)
    {
        $row = Goods::findOrEmpty($id);
        if ($row->isEmpty()) {
            return $this->fail('商品不存在');
        }
        $row->save(['status' => 1, 'status_time' => time()]);
        return $this->ok([], '已还原为销售中');
    }

    /**
     * 排序（批量：{items:[{id,sort}]}）
     */
    public function sort()
    {
        $items = (array) ($this->request->param('items') ?: []);
        if ($items === []) {
            return $this->fail('没有需要排序的数据');
        }
        Db::startTrans();
        try {
            foreach ($items as $it) {
                $id   = (int) ($it['id'] ?? 0);
                $sort = (int) ($it['sort'] ?? 0);
                if ($id > 0) {
                    Goods::where('id', $id)->update(['sort' => $sort]);
                }
            }
            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            return $this->fail('排序失败：' . $e->getMessage());
        }
        return $this->ok([], '排序已更新');
    }

    /**
     * 保存卡密库存桥（对齐权限点 goods/save_goods_stock）
     * 卡密商品 → goods_stock(type=1) 绑定卡密库；goods.stock_num 同步卡库余量
     * POST {goods_id, card_cate_id}
     */
    public function save_goods_stock()
    {
        $goodsId   = (int) $this->request->param('goods_id', 0);
        $cardCateId = (int) $this->request->param('card_cate_id', 0);
        $goods = Goods::findOrEmpty($goodsId);
        if ($goods->isEmpty()) {
            return $this->fail('商品不存在');
        }
        $cate = \app\common\model\CardCate::findOrEmpty($cardCateId);
        if ($cate->isEmpty()) {
            return $this->fail('卡密库不存在');
        }
        $stock = GoodsStock::where('goods_id', $goodsId)->where('type', 1)->findOrEmpty();        $data  = [
            'uid'          => 0,
            'type'         => 1,
            'name'         => (string) $cate->getAttr('name'),
            'goods_id'     => $goodsId,
            'card_cate_id' => $cardCateId,
            'cost_value'   => (string) $cate->getAttr('cost'),
            'stock_num'    => (int) $cate->getAttr('card_num'),
            'status'       => 1,
        ];
        if ($stock->isEmpty()) {
            $stock = new GoodsStock();
            $data['create_time'] = time();
            $stock->save($data);
        } else {
            $stock->save($data);
        }
        // 商品冗余库存同步
        Goods::where('id', $goodsId)->update(['stock_num' => (int) $cate->getAttr('card_num')]);
        return $this->ok($stock, '库存已绑定卡密库');
    }

    /**
     * 枚举：商品状态（mks_sys_dict goods_status）
     */
    public function status_type()
    {
        return $this->ok($this->dictOptions('goods_status'));
    }

    /**
     * 枚举：商品类型
     */
    public function goods_type()
    {
        return $this->ok($this->dictOptions('goods_type'));
    }

    /**
     * 枚举：供货方式
     */
    public function provide_type()
    {
        return $this->ok($this->dictOptions('provide_type'));
    }

    /**
     * 默认标签（品牌下拉，对接权限点 goods/default_tags_list）
     */
    public function default_tags_list()
    {
        $list = GoodsTags::where('status', 1)->order('sort desc, id asc')->select();
        return $this->ok($list);
    }

    /**
     * 写入共用（分类冗余同步 has_child/goods_num）
     */
    protected function doSave(Goods $row, array $data, string $msg)
    {
        $form = array_intersect_key($data, array_flip($this->crudFields));
        if ($form === []) {
            return $this->fail('没有需要保存的数据');
        }
        $oldCids = explode(',', (string) $row->getAttr('category_ids'));
        $row->allowField($this->crudFields)->save($form);
        $newCids = array_filter(explode(',', (string) $row->getAttr('category_ids')), fn ($v) => (int) $v > 0);
            // 分类商品数冗余
            $affected = array_unique(array_merge($oldCids, $newCids));
            foreach ($affected as $cid) {
                $cid = (int) $cid;
                if ($cid > 0) {
                    GoodsCategory::where('id', $cid)->update([
                        'goods_num' => Db::name('goods')->whereRaw("FIND_IN_SET({$cid}, category_ids)")->where('delete_time', 0)->count(),
                    ]);
                }
            }
        return $this->ok($row, $msg);
    }

    /**
     * 字典枚举读取（label/code/color/sort）
     */
    protected function dictOptions(string $type): array
    {
        return SysDict::where('dict_type', $type)
            ->where('status', 1)
            ->where('vip_id', 0)
            ->order('sort asc, id asc')
            ->field('code,label,color,sort')
            ->select()
            ->toArray();
    }
}
