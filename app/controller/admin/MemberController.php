<?php
// +----------------------------------------------------------------------
// | 后台 · 会员管理（M4；对齐权限点 member/*）
// 能力：列表/详情/新建/编辑/状态与等级快捷改/重置密码/解绑上级/软删还原
// 红线：余额与佣金不经通用保存接口直接改，必须走对应资金服务/提现流程
// +----------------------------------------------------------------------
declare (strict_types = 1);

namespace app\controller\admin;

use app\common\model\Member;
use app\common\model\MemberLevel;
use app\common\model\Order;
use mks\traits\CurdTrait;

class MemberController extends Base
{
    use CurdTrait;

    protected string $crudModel = Member::class;

    protected array $crudSearchable = [
        'id'         => 'eq',
        'username'   => 'like',
        'nickname'   => 'like',
        'mobile'     => 'like',
        'email'      => 'like',
        'level_id'   => 'eq',
        'status'     => 'eq',
        'pid'        => 'eq',
        'group_id'   => 'eq',
        'merchant_id' => 'eq',
        'vip_id'     => 'eq',
    ];

    protected array $crudModifiable = ['status', 'level_id', 'nickname', 'mark', 'group_id'];

    protected array $crudFields = [
        'username', 'password', 'nickname', 'mobile', 'email', 'avatar',
        'level_id', 'status', 'mark', 'group_id', 'pid', 'sex',
    ];

    protected string $crudOrder = 'id desc';

    protected array $crudOrderable = ['id', 'create_time', 'last_time', 'cost', 'balance', 'commission_money'];

    /**
     * 会员列表（排除软删；补等级名/上级）
     */
    public function index()
    {
        $page  = max(1, (int) $this->request->param('page', 1));
        $limit = min(200, max(1, (int) $this->request->param('limit', 15)));
        $query = $this->crudQuery(new Member())->where('delete_time', 0);
        $keyword = trim((string) $this->request->param('keyword', ''));
        if ($keyword !== '') {
            $query = $query->where(function ($q) use ($keyword) {
                $q->whereOr('username', 'like', '%' . $keyword . '%')
                    ->whereOr('nickname', 'like', '%' . $keyword . '%')
                    ->whereOr('mobile', 'like', '%' . $keyword . '%');
            });
        }
        $total = (clone $query)->count();
        $list  = $query->page($page, $limit)->field(
            'id,username,nickname,avatar,mobile,email,level_id,pid,child_num,status,balance,commission_money,'
            . 'cost,total_recharge_money,total_commission_money,group_id,vip_id,merchant_id,reg_ip,last_time,create_time,mark'
        )->select()->toArray();

        if ($list !== []) {
            $levelIds = array_values(array_unique(array_filter(array_column($list, 'level_id'))));
            $pids     = array_values(array_unique(array_filter(array_column($list, 'pid'))));
            $levelMap = $levelIds === [] ? [] : MemberLevel::whereIn('id', $levelIds)->column('name', 'id');
            $nameMap  = $pids === [] ? [] : Member::whereIn('id', $pids)->column('nickname', 'id');
            foreach ($list as &$row) {
                $row['level_name']  = (string) ($levelMap[(int) $row['level_id']] ?? '');
                $row['parent_name'] = (int) $row['pid'] > 0 ? (string) ($nameMap[(int) $row['pid']] ?? '') : '';
            }
            unset($row);
        }
        return $this->ok(['list' => $list, 'count' => $total]);
    }

    /**
     * 会员详情（含等级、上级、订单统计）
     */
    public function read(int $id)
    {
        $row = Member::findOrEmpty($id);
        if ($row->isEmpty()) {
            return $this->fail('会员不存在');
        }
        $data = $row->toArray();
        $level = MemberLevel::findOrEmpty((int) $row->getAttr('level_id'));
        $data['level_name'] = $level->isEmpty() ? '' : (string) $level->getAttr('name');
        $data['pay_rate']   = $level->isEmpty() ? '0.00' : (string) $level->getAttr('pay_rate');
        $data['parent_name'] = '';
        if ((int) $row->getAttr('pid') > 0) {
            $data['parent_name'] = (string) Member::where('id', (int) $row->getAttr('pid'))->value('nickname');
        }
        $data['order_count']   = Order::where('uid', $id)->count();
        $data['order_success'] = Order::where('uid', $id)->where('status', Order::ST_SUCCESS)->count();
        unset($data['password'], $data['trade_password'], $data['income_key'], $data['salt'], $data['c']);
        return $this->ok($data);
    }

    /**
     * 新建会员（后台代注册，默认等级/头像兜底）
     * @param {username, password, nickname?, level_id?, status?}
     */
    public function create()
    {
        $data = $this->crudFormData();
        $username = (string) ($data['username'] ?? '');
        $password = (string) ($data['password'] ?? '');
        if ($username === '' || $password === '') {
            return $this->fail('请填写账号与密码');
        }
        if (Member::where('username', $username)->where('delete_time', 0)->count() > 0) {
            return $this->fail('账号已存在');
        }
        $defaultLevel = (int) MemberLevel::where('is_default', 1)->value('id');
        $data['nickname']    ??= $username;
        $data['avatar']      ??= '';
        $data['level_id']    ??= $defaultLevel > 0 ? $defaultLevel : 1;
        $data['status']      ??= 1;
        $data['create_time'] = time();
        $member = new Member();
        $member->allowField($this->crudFields)->save($data);
        return $this->ok(['id' => (int) $member->getAttr('id')], '创建成功');
    }

    /**
     * 编辑会员（不含资金字段；password 非空才重置）
     */
    public function save(int $id)
    {
        $row = Member::findOrEmpty($id);
        if ($row->isEmpty()) {
            return $this->fail('会员不存在');
        }
        $data = $this->crudFormData();
        if (isset($data['password']) && (string) $data['password'] === '') {
            unset($data['password']);
        }
        if ($data === []) {
            return $this->fail('没有需要保存的数据');
        }
        $row->allowField($this->crudFields)->save($data);
        return $this->ok([], '保存成功');
    }

    /**
     * 重置登录密码
     */
    public function reset_password(int $id)
    {
        $password = (string) $this->request->param('password', '');
        if (strlen($password) < 6) {
            return $this->fail('密码至少 6 位');
        }
        $row = Member::findOrEmpty($id);
        if ($row->isEmpty()) {
            return $this->fail('会员不存在');
        }
        $row->save(['password' => $password]);
        return $this->ok([], '密码已重置');
    }

    /**
     * 解绑上级（分销关系解除；同步上级下级数）
     */
    public function unbind(int $id)
    {
        $row = Member::findOrEmpty($id);
        if ($row->isEmpty()) {
            return $this->fail('会员不存在');
        }
        $pid = (int) $row->getAttr('pid');
        if ($pid <= 0) {
            return $this->fail('该会员没有上级');
        }
        $row->save(['pid' => 0]);
        $childNum = max(0, (int) Member::where('id', $pid)->value('child_num') - 1);
        Member::where('id', $pid)->update(['child_num' => $childNum]);
        return $this->ok([], '已解绑');
    }

    /**
     * 软删（回收站）
     */
    public function delete(int $id)
    {
        $row = Member::findOrEmpty($id);
        if ($row->isEmpty()) {
            return $this->fail('会员不存在');
        }
        $row->delete();
        return $this->ok([], '已删除');
    }

    /**
     * 还原软删会员
     */
    public function restore(int $id)
    {
        $row = Member::onlyTrashed()->find($id);
        if (empty($row)) {
            return $this->fail('会员不存在或未删除');
        }
        $row->restore();
        return $this->ok([], '已还原');
    }

    /**
     * 会员状态字典（对齐 sys_dict member_status）
     */
    public function status_type()
    {
        return $this->ok([
            ['label' => '正常', 'value' => 1],
            ['label' => '禁用', 'value' => 0],
        ]);
    }
}
