<?php
// +----------------------------------------------------------------------
// | 后台 · 分站管理（M6；对齐权限点 vip/*）
// +----------------------------------------------------------------------
declare (strict_types = 1);

namespace app\controller\admin;

use app\common\model\Goods;
use app\common\model\Member;
use app\common\model\Order;
use app\common\model\OrderVip;
use app\common\model\SysUrl;
use app\common\model\VipGoodsPrice;
use app\common\model\VipSite;
use app\common\service\VipPriceService;
use app\common\service\VipSiteService;
use mks\traits\CurdTrait;

class VipSiteController extends Base
{
    use CurdTrait;

    protected string $crudModel = VipSite::class;

    protected array $crudSearchable = [
        'name'   => 'like',
        'domain' => 'like',
        'status' => 'eq',
    ];

    protected array $crudModifiable = ['status', 'rate', 'share_rate', 'expire_time', 'mark', 'pid', 'path_prefix', 'allow_main_goods', 'settle_minutes'];

    protected array $crudFields = ['uid', 'name', 'domain', 'rate', 'share_rate', 'status', 'expire_time', 'mark', 'pid', 'path_prefix', 'allow_main_goods', 'settle_minutes'];

    protected string $crudOrder = 'id asc';

    /**
     * 分站列表（含分站主账号）
     */
    public function index()
    {
        $page  = max(1, (int) $this->request->param('page', 1));
        $limit = min(100, max(1, (int) $this->request->param('limit', 15)));
        $query = VipSite::order('id asc');
        $name = (string) $this->request->param('name', '');
        if ($name !== '') {
            $query = $query->whereLike('name', '%' . $name . '%');
        }
        $status = $this->request->param('status', '');
        if ($status !== '') {
            $query = $query->where('status', (int) $status);
        }
        $total = (clone $query)->count();
        $list  = $query->page($page, $limit)->select()->toArray();
        $uids  = array_column($list, 'uid');
        $members = $uids ? Member::whereIn('id', array_filter($uids))->column('username,nickname', 'id') : [];
        foreach ($list as &$r) {
            $r['owner'] = $members[$r['uid']]['username'] ?? '';
            $r['owner_nickname'] = $members[$r['uid']]['nickname'] ?? '';
        }
        return $this->ok(['list' => $list, 'count' => $total]);
    }

    /**
     * 创建分站（校验分站主存在）
     */
    public function create()
    {
        $uid = (int) $this->request->param('uid', 0);
        if ($uid <= 0 || Member::where('id', $uid)->where('delete_time', 0)->count() === 0) {
            return $this->fail('分站主会员不存在');
        }
        $data = $this->crudFormData();
        $data['pid']              = 0;
        $data['path_prefix']      = $this->normalizePrefix((string) ($data['path_prefix'] ?? ''));
        $data['allow_main_goods'] = (int) ($data['allow_main_goods'] ?? 1);
        $data['settle_minutes']   = max(0, (int) ($data['settle_minutes'] ?? 0));
        $data['domain']           = $this->normalizeDomain((string) ($data['domain'] ?? ''));
        if ($data['path_prefix'] !== ''
            && VipSite::where('path_prefix', $data['path_prefix'])->where('delete_time', 0)->count() > 0) {
            return $this->fail('二级目录已被占用');
        }
        $data['create_time'] = time();
        $data['update_time'] = time();
        $site = new VipSite();
        $site->save($data);
        // 分站主标记
        Member::where('id', $uid)->update(['has_vip' => 1, 'vip_id' => (int) $site->getAttr('id')]);
        if ($data['domain'] !== '') {
            $this->upsertDomain((int) $site->getAttr('id'), $data['domain']);
        }
        return $this->ok($site, '创建成功');
    }

    /**
     * 更新分站
     */
    public function save(int $id)
    {
        $site = VipSite::findOrEmpty($id);
        if ($site->isEmpty()) {
            return $this->fail('分站不存在');
        }
        $data = $this->crudFormData();
        if (array_key_exists('path_prefix', $data)) {
            $prefix = $this->normalizePrefix((string) $data['path_prefix']);
            if ($prefix !== ''
                && VipSite::where('path_prefix', $prefix)->where('id', '<>', $id)->where('delete_time', 0)->count() > 0) {
                return $this->fail('二级目录已被占用');
            }
            $data['path_prefix'] = $prefix;
        }
        if (array_key_exists('domain', $data)) {
            $data['domain'] = $this->normalizeDomain((string) $data['domain']);
        }
        $data['update_time'] = time();
        $site->save($data);
        if (!empty($data['domain'])) {
            $this->upsertDomain($id, (string) $data['domain']);
        }
        return $this->ok($site, '保存成功');
    }

    /**
     * 分站详情（概览/域名/结算摘要）
     */
    public function read(int $id)
    {
        $site = VipSite::findOrEmpty($id);
        if ($site->isEmpty()) {
            return $this->fail('分站不存在');
        }
        $owner = Member::findOrEmpty((int) $site->getAttr('uid'));
        $orderQ = Order::where('vip_id', $id)->where('delete_time', 0);
        $shareQ = OrderVip::where('vip_id', $id);
        return $this->ok([
            'site'    => $site->toArray(),
            'owner'   => $owner->isEmpty() ? null : [
                'id'       => (int) $owner->getAttr('id'),
                'username' => (string) $owner->getAttr('username'),
                'nickname' => (string) $owner->getAttr('nickname'),
                'mobile'   => (string) $owner->getAttr('mobile'),
            ],
            'domains' => SysUrl::where('vip_id', $id)->order('id asc')->select()->toArray(),
            'stats'   => [
                'order_count'     => (clone $orderQ)->count(),
                'order_sales'     => (string) (clone $orderQ)->sum('total_price'),
                'share_total'     => (string) (clone $shareQ)->sum('commision_price'),
                'share_pending'   => (string) (clone $shareQ)->where('sure_status', OrderVip::SURE_WAIT)->sum('commision_price'),
                'share_refunded'  => (string) (clone $shareQ)->sum('back_commision_price'),
                'platform_profit' => (string) (clone $orderQ)->sum('vip_profit_price'),
            ],
            'config'  => VipSiteService::configRead($id),
        ]);
    }

    /**
     * 分站商品定价列表
     */
    public function goods_price_index()
    {
        $vipId = (int) $this->request->param('vip_id', 0);
        $site  = VipSite::findOrEmpty($vipId);
        if ($site->isEmpty()) {
            return $this->fail('分站不存在');
        }
        $page  = max(1, (int) $this->request->param('page', 1));
        $limit = min(100, max(1, (int) $this->request->param('limit', 20)));
        $query = Goods::where('status', 1)->where('delete_time', 0);
        $keyword = (string) $this->request->param('keyword', '');
        if ($keyword !== '') {
            $query = $query->whereLike('goods_name', '%' . $keyword . '%');
        }
        $total = (clone $query)->count();
        $list  = $query->order('id desc')->page($page, $limit)
            ->field('id,goods_name,goods_img,real_price,vip_id')
            ->select()->toArray();
        $ovRows = VipGoodsPrice::where('vip_id', $vipId)
            ->whereIn('goods_id', array_column($list, 'id'))
            ->select()->toArray();
        $overrides = array_column($ovRows, null, 'goods_id');
        foreach ($list as &$r) {
            $ov = $overrides[(int) $r['id']] ?? null;
            $model = $ov === null ? null : (new VipGoodsPrice())->data($ov, true);
            $p = VipPriceService::price((string) $r['real_price'], $site, $model);
            $r['base_price']   = $p['base'];
            $r['vip_price']    = $p['price'];
            $r['vip_mode']     = $p['mode'];
            $r['vip_value']    = $p['value'];
            $r['has_override'] = $p['override'] ? 1 : 0;
            $r['add_price']    = $p['add'];
            $r['visible']      = ((int) $r['vip_id'] === 0
                ? (int) $site->getAttr('allow_main_goods') === 1
                : (int) $r['vip_id'] === $vipId) ? 1 : 0;
        }
        unset($r);
        return $this->ok(['list' => $list, 'count' => $total]);
    }

    /**
     * 保存商品级定价
     */
    public function goods_price_save()
    {
        $vipId   = (int) $this->request->param('vip_id', 0);
        $goodsId = (int) $this->request->param('goods_id', 0);
        $mode    = (int) $this->request->param('mode', 0);
        $value   = (string) $this->request->param('value', '0');
        $site    = VipSite::findOrEmpty($vipId);
        if ($site->isEmpty()) {
            return $this->fail('分站不存在');
        }
        if ($goodsId <= 0 || !in_array($mode, [1, 2, 3], true) || bccomp($value, '0', 4) < 0) {
            return $this->fail('参数错误');
        }
        $goods = Goods::findOrEmpty($goodsId);
        if ($goods->isEmpty()) {
            return $this->fail('商品不存在');
        }
        $row = VipGoodsPrice::where('vip_id', $vipId)->where('goods_id', $goodsId)->findOrEmpty();
        if ($row->isEmpty()) {
            VipGoodsPrice::create([
                'vip_id'      => $vipId,
                'goods_id'    => $goodsId,
                'mode'        => $mode,
                'value'       => $value,
                'status'      => 1,
                'create_time' => time(),
                'update_time' => time(),
            ]);
        } else {
            $row->save(['mode' => $mode, 'value' => $value, 'status' => 1, 'update_time' => time()]);
        }
        return $this->ok([], '已保存');
    }

    /**
     * 清除商品级定价
     */
    public function goods_price_clear()
    {
        $vipId   = (int) $this->request->param('vip_id', 0);
        $goodsId = (int) $this->request->param('goods_id', 0);
        if ($vipId <= 0 || $goodsId <= 0) {
            return $this->fail('参数错误');
        }
        VipGoodsPrice::where('vip_id', $vipId)->where('goods_id', $goodsId)->delete();
        return $this->ok([], '已恢复默认');
    }

    /**
     * 分站订单
     */
    public function order_index()
    {
        $vipId = (int) $this->request->param('vip_id', 0);
        $page  = max(1, (int) $this->request->param('page', 1));
        $limit = min(100, max(1, (int) $this->request->param('limit', 20)));
        $query = Order::where('delete_time', 0);
        if ($vipId > 0) {
            $query = $query->where('vip_id', $vipId);
        }
        $status = (string) $this->request->param('status', '');
        if ($status !== '') {
            $query = $query->where('status', (int) $status);
        }
        $keyword = (string) $this->request->param('keyword', '');
        if ($keyword !== '') {
            $query = $query->where(function ($q) use ($keyword) {
                $q->whereLike('ordersn', '%' . $keyword . '%')
                    ->whereOr('goods_name', 'like', '%' . $keyword . '%');
            });
        }
        $total = (clone $query)->count();
        $list  = $query->order('id desc')->page($page, $limit)
            ->field('id,ordersn,uid,nickname,goods_id,goods_name,goods_img,quantity,real_price,total_price,vip_id,vip_uid,vip_goods_price,vip_add_price,vip_profit_price,status,pay_time,create_time')
            ->select()->toArray();
        return $this->ok(['list' => $list, 'count' => $total]);
    }

    /**
     * 分站结算（抽成）流水
     */
    public function settlement_index()
    {
        $vipId = (int) $this->request->param('vip_id', 0);
        $page  = max(1, (int) $this->request->param('page', 1));
        $limit = min(100, max(1, (int) $this->request->param('limit', 20)));
        $query = OrderVip::order('id desc');
        if ($vipId > 0) {
            $query = $query->where('vip_id', $vipId);
        }
        $sure = (string) $this->request->param('sure_status', '');
        if ($sure !== '') {
            $query = $query->where('sure_status', (int) $sure);
        }
        $total = (clone $query)->count();
        $rows  = $query->page($page, $limit)->select()->toArray();
        $orderIds = array_column($rows, 'order_id');
        $orders   = $orderIds === [] ? [] : Order::whereIn('id', $orderIds)->column('ordersn,goods_name,total_price,status', 'id');
        $vipIds   = array_unique(array_column($rows, 'vip_id'));
        $sites    = $vipIds === [] ? [] : VipSite::whereIn('id', $vipIds)->column('name', 'id');
        foreach ($rows as &$r) {
            $o = $orders[(int) $r['order_id']] ?? null;
            $r['ordersn']      = $o['ordersn'] ?? '';
            $r['goods_name']   = $o['goods_name'] ?? '';
            $r['order_total']  = $o['total_price'] ?? '0.0000';
            $r['order_status'] = $o['status'] ?? 0;
            $r['vip_name']     = $sites[(int) $r['vip_id']] ?? '';
        }
        unset($r);
        return $this->ok(['list' => $rows, 'count' => $total]);
    }

    /**
     * 手动解冻结算（单条或指定分站全部到期）
     */
    public function settlement_settle()
    {
        $id    = (int) $this->request->param('id', 0);
        $vipId = (int) $this->request->param('vip_id', 0);
        if ($id > 0) {
            $row = OrderVip::where('id', $id)->findOrEmpty();
            if ($row->isEmpty()) {
                return $this->fail('结算记录不存在');
            }
            if ((int) $row->getAttr('sure_status') !== OrderVip::SURE_WAIT) {
                return $this->fail('仅待结算记录可解冻');
            }
            VipSiteService::settleRow($row);
            return $this->ok([], '已解冻');
        }
        if ($vipId <= 0) {
            return $this->fail('参数错误');
        }
        $rows = OrderVip::where('vip_id', $vipId)
            ->where('sure_status', OrderVip::SURE_WAIT)
            ->where('frozen_time', '>', 0)
            ->select();
        $settled = 0;
        foreach ($rows as $row) {
            if (VipSiteService::settleRow($row)) {
                $settled++;
            }
        }
        return $this->ok(['settled' => $settled], '已解冻 ' . $settled . ' 条');
    }

    /**
     * 分站配置读取
     */
    public function config_read()
    {
        $vipId = (int) $this->request->param('vip_id', 0);
        if ($vipId <= 0 || VipSite::findOrEmpty($vipId)->isEmpty()) {
            return $this->fail('分站不存在');
        }
        return $this->ok([
            'config'    => VipSiteService::configRead($vipId),
            'whitelist' => array_keys(VipSiteService::CONFIG_WHITELIST),
        ]);
    }

    /**
     * 分站配置保存（白名单键）
     */
    public function config_save()
    {
        $vipId = (int) $this->request->param('vip_id', 0);
        if ($vipId <= 0 || VipSite::findOrEmpty($vipId)->isEmpty()) {
            return $this->fail('分站不存在');
        }
        VipSiteService::configWrite($vipId, $this->request->param());
        return $this->ok([], '已保存');
    }

    /**
     * 域名列表（vip_id=0 时为全部）
     */
    public function domain_index()
    {
        $vipId = (int) $this->request->param('vip_id', -1);
        $query = SysUrl::order('is_main desc, id asc');
        if ($vipId >= 0) {
            $query = $query->where('vip_id', $vipId);
        }
        $rows = $query->select()->toArray();
        $vipIds = array_unique(array_column($rows, 'vip_id'));
        $sites  = $vipIds === [] ? [] : VipSite::whereIn('id', $vipIds)->column('name', 'id');
        foreach ($rows as &$r) {
            $r['vip_name'] = $r['is_main'] ? '主站' : ($sites[(int) $r['vip_id']] ?? '');
        }
        unset($r);
        return $this->ok($rows);
    }

    /**
     * 保存域名绑定
     */
    public function domain_save()
    {
        $id    = (int) $this->request->param('id', 0);
        $isMain = (int) $this->request->param('is_main', 0) === 1 ? 1 : 0;
        $vipId = $isMain ? 0 : (int) $this->request->param('vip_id', 0);
        $url   = $this->normalizeDomain((string) $this->request->param('url', ''));
        if ($url === '') {
            return $this->fail('请填写域名');
        }
        if (!$isMain && ($vipId <= 0 || VipSite::findOrEmpty($vipId)->isEmpty())) {
            return $this->fail('分站不存在');
        }
        $dup = SysUrl::where('url', $url);
        if ($id > 0) {
            $dup = $dup->where('id', '<>', $id);
        }
        if ($dup->count() > 0) {
            return $this->fail('域名已被绑定');
        }
        $data = [
            'vip_id'       => $vipId,
            'url'          => $url,
            'is_main'      => $isMain,
            'template'     => (string) $this->request->param('template', 'default'),
            'template_pc'  => (string) $this->request->param('template_pc', 'default'),
            'close_site'   => (string) $this->request->param('close_site', ''),
            'update_time'  => time(),
        ];
        if ($id > 0) {
            $row = SysUrl::findOrEmpty($id);
            if ($row->isEmpty()) {
                return $this->fail('域名记录不存在');
            }
            if ((int) $row->getAttr('is_main') === 1 && !$isMain) {
                return $this->fail('主站域名不可改为分站');
            }
            $row->save($data);
        } else {
            $data['type']        = $isMain ? 1 : 2;
            $data['create_time'] = time();
            SysUrl::create($data);
        }
        if ($isMain) {
            SysUrl::where('is_main', 1)->where('id', '<>', $id > 0 ? $id : 0)->update(['is_main' => 0]);
            SysUrl::where('url', $url)->update(['is_main' => 1]);
        } else {
            $site = VipSite::findOrEmpty($vipId);
            if (!$site->isEmpty() && (string) $site->getAttr('domain') === '') {
                $site->save(['domain' => $url, 'update_time' => time()]);
            }
        }
        return $this->ok([], '已保存');
    }

    /**
     * 删除域名绑定
     */
    public function domain_delete()
    {
        $id = (int) $this->request->param('id', 0);
        if ($id <= 0) {
            return $this->fail('参数错误');
        }
        $row = SysUrl::findOrEmpty($id);
        if ($row->isEmpty()) {
            return $this->fail('域名记录不存在');
        }
        if ((int) $row->getAttr('is_main') === 1) {
            return $this->fail('主站域名不可删除');
        }
        $vipId = (int) $row->getAttr('vip_id');
        $url   = (string) $row->getAttr('url');
        $row->delete();
        $site = VipSite::findOrEmpty($vipId);
        if (!$site->isEmpty() && (string) $site->getAttr('domain') === $url) {
            $next = (string) SysUrl::where('vip_id', $vipId)->order('id asc')->value('url');
            $site->save(['domain' => $next, 'update_time' => time()]);
        }
        return $this->ok([], '已删除');
    }

    protected function normalizeDomain(string $url): string
    {
        $url = strtolower(trim($url));
        $url = preg_replace('#^https?://#', '', $url);
        $url = rtrim((string) $url, '/');
        return mb_substr((string) $url, 0, 255, 'UTF-8');
    }

    protected function normalizePrefix(string $prefix): string
    {
        $prefix = strtolower(trim($prefix, " /"));
        return preg_match('/^[a-z0-9_-]{1,32}$/', $prefix) ? $prefix : '';
    }

    protected function upsertDomain(int $vipId, string $url): void
    {
        $row = SysUrl::where('url', $url)->findOrEmpty();
        if ($row->isEmpty()) {
            SysUrl::create([
                'vip_id'      => $vipId,
                'type'        => 2,
                'url'         => $url,
                'is_main'     => 0,
                'template'    => 'default',
                'template_pc' => 'default',
                'create_time' => time(),
                'update_time' => time(),
            ]);
        } elseif ((int) $row->getAttr('vip_id') !== $vipId) {
            $row->save(['vip_id' => $vipId, 'update_time' => time()]);
        }
    }

    /**
     * 删除分站（软删 + 解绑分站主）
     */
    public function delete(int $id)
    {
        $site = VipSite::findOrEmpty($id);
        if ($site->isEmpty()) {
            return $this->fail('分站不存在');
        }
        $uid = (int) $site->getAttr('uid');
        $site->save(['delete_time' => time(), 'status' => 0]);
        if ($uid > 0) {
            Member::where('id', $uid)->where('vip_id', $id)->update(['has_vip' => 0, 'vip_id' => 0]);
        }
        return $this->ok([], '删除成功');
    }
}
