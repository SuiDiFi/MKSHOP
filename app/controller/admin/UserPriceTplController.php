<?php
// +----------------------------------------------------------------------
// | 后台 · 用户权限模板（M7 对齐 KASUSHOU 用户密价/限价/商品权限三页合一）
// | type：1=会员密价（mode+value 幅度） 2=销售限价（value=最低售价） 3=商品权限（value 保留位）
// | 绑定：create/save 携带 user_ids/goods_ids 数组即全量替换；read 返回已绑定 id 列表
// +----------------------------------------------------------------------
declare (strict_types = 1);

namespace app\controller\admin;

use app\common\model\UserPriceTpl;
use mks\traits\CurdTrait;
use think\facade\Db;

class UserPriceTplController extends Base
{
    use CurdTrait;

    protected string $crudModel = UserPriceTpl::class;

    protected array $crudSearchable = [
        'name'   => 'like',
        'type'   => 'eq',
        'status' => 'eq',
    ];

    protected array $crudModifiable = ['name', 'mode', 'value', 'status'];

    protected array $crudFields = ['name', 'type', 'mode', 'value', 'status'];

    protected string $crudOrder = 'id desc';

    /**
     * 列表补充：已绑定用户数/商品数
     */
    public function index()
    {
        $result = $this->crudIndex();
        $data   = $result->getData();
        $list   = (array) ($data['data']['list'] ?? []);
        $ids    = array_column($list, 'id');
        if ($ids !== []) {
            $userCount  = Db::name('user_price_tpl_user')->whereIn('tpl_id', $ids)->group('tpl_id')->column('COUNT(*)', 'tpl_id');
            $goodsCount = Db::name('user_price_tpl_goods')->whereIn('tpl_id', $ids)->group('tpl_id')->column('COUNT(*)', 'tpl_id');
            foreach ($list as &$row) {
                $row['user_count']  = (int) ($userCount[$row['id']] ?? 0);
                $row['goods_count'] = (int) ($goodsCount[$row['id']] ?? 0);
            }
            unset($row);
        }
        $data['data']['list'] = $list;
        return json_ok($data['data'], $data['msg']);
    }

    /**
     * 详情（含已绑定用户/商品 id 列表，编辑回显用）
     */
    public function read(int $id)
    {
        $row = UserPriceTpl::findOrEmpty($id);
        if ($row->isEmpty()) {
            return $this->fail('模板不存在');
        }
        $arr = $row->toArray();
        $arr['user_ids']  = Db::name('user_price_tpl_user')->where('tpl_id', $id)->column('uid');
        $arr['goods_ids'] = Db::name('user_price_tpl_goods')->where('tpl_id', $id)->column('goods_id');
        return $this->ok($arr);
    }

    /**
     * 从原始 form 提取绑定 id（crudFields 白名单会过滤掉 user_ids/goods_ids，故绕行读取）
     */
    protected function formBindIds(string $key): ?array
    {
        $form = (array) ($this->request->param('form') ?: []);
        if (!array_key_exists($key, $form)) {
            return null;
        }
        return $this->normalizeIds($form[$key]);
    }

    /**
     * 新建：基础字段 + 可选绑定全量替换
     */
    public function create()
    {
        $data  = $this->crudFormData();
        $users = $this->formBindIds('user_ids');
        $goods = $this->formBindIds('goods_ids');
        Db::startTrans();
        try {
            $model = $this->crudCreate($data);
            if ($model instanceof \think\Response\Json) {
                Db::rollback();
                return $model;
            }
            $this->syncBindings((int) $model->id, $users, $goods);
            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            return $this->fail('保存失败：' . $e->getMessage());
        }
        return $this->ok(['id' => (int) $model->id], '创建成功');
    }

    /**
     * 更新：基础字段 + 可选绑定全量替换
     */
    public function save(int $id)
    {
        $row = UserPriceTpl::findOrEmpty($id);
        if ($row->isEmpty()) {
            return $this->fail('模板不存在');
        }
        $data  = $this->crudFormData();
        $users = $this->formBindIds('user_ids');
        $goods = $this->formBindIds('goods_ids');
        Db::startTrans();
        try {
            if ($data !== []) {
                $row->allowField($this->crudFields ?: true)->save($data);
            }
            if ($users !== null) {
                $this->syncBindings((int) $row->id, $users, null);
            }
            if ($goods !== null) {
                $this->syncBindings((int) $row->id, null, $goods);
            }
            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            return $this->fail('保存失败：' . $e->getMessage());
        }
        return $this->ok([], '保存成功');
    }

    /**
     * 删除：软删模板并清理两端绑定
     */
    public function delete(int $id)
    {
        $row = UserPriceTpl::findOrEmpty($id);
        if ($row->isEmpty()) {
            return $this->fail('模板不存在');
        }
        Db::startTrans();
        try {
            $row->delete();
            Db::name('user_price_tpl_user')->where('tpl_id', $id)->delete();
            Db::name('user_price_tpl_goods')->where('tpl_id', $id)->delete();
            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            return $this->fail('删除失败：' . $e->getMessage());
        }
        return $this->ok([], '删除成功');
    }

    /**
     * 全量替换绑定（null=不改动该端绑定）
     */
    protected function syncBindings(int $tplId, ?array $users, ?array $goods): void
    {
        if ($users !== null) {
            Db::name('user_price_tpl_user')->where('tpl_id', $tplId)->delete();
            if ($users !== []) {
                Db::name('user_price_tpl_user')->insertAll(
                    array_map(fn ($uid) => ['tpl_id' => $tplId, 'uid' => $uid], $users)
                );
            }
        }
        if ($goods !== null) {
            Db::name('user_price_tpl_goods')->where('tpl_id', $tplId)->delete();
            if ($goods !== []) {
                Db::name('user_price_tpl_goods')->insertAll(
                    array_map(fn ($gid) => ['tpl_id' => $tplId, 'goods_id' => $gid], $goods)
                );
            }
        }
    }

    /**
     * 归一化 id 数组：接受数组或逗号分隔串；去重、过滤非正整数
     * 返回 null 表示未提交该端绑定
     */
    protected function normalizeIds(mixed $ids): ?array
    {
        if ($ids === null) {
            return null;
        }
        $arr = is_array($ids) ? $ids : array_filter(explode(',', (string) $ids), fn ($v) => trim($v) !== '');
        $out = [];
        foreach ($arr as $v) {
            $n = (int) $v;
            if ($n > 0) {
                $out[$n] = $n;
            }
        }
        return array_values($out);
    }
}
