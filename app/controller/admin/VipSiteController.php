<?php
// +----------------------------------------------------------------------
// | 后台 · 分站管理（M6；对齐权限点 vip/*）
// +----------------------------------------------------------------------
declare (strict_types = 1);

namespace app\controller\admin;

use app\common\model\Member;
use app\common\model\VipSite;
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

    protected array $crudModifiable = ['status', 'rate', 'share_rate', 'expire_time', 'mark'];

    protected array $crudFields = ['uid', 'name', 'domain', 'rate', 'share_rate', 'status', 'expire_time', 'mark'];

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
        $data['create_time'] = time();
        $data['update_time'] = time();
        $site = new VipSite();
        $site->save($data);
        // 分站主标记
        Member::where('id', $uid)->update(['has_vip' => 1, 'vip_id' => (int) $site->getAttr('id')]);
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
        $data['update_time'] = time();
        $site->save($data);
        return $this->ok($site, '保存成功');
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
