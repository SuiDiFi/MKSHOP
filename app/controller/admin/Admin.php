<?php
// +----------------------------------------------------------------------
// | 后台 · 管理员管理（对齐权限点 admin/*）
// 注意：use app\common\model\Admin as AdminModel —— 别名导入避免与本类名 Admin 冲突（PHP 类名不区分大小写）
// +----------------------------------------------------------------------
declare (strict_types = 1);

namespace app\controller\admin;

use app\common\model\Admin as AdminModel;
use app\common\model\AdminLoginStatus;
use app\common\model\AdminLog as AdminLogModel;
use mks\traits\CurdTrait;

class Admin extends Base
{
    use CurdTrait;

    protected string $crudModel = AdminModel::class;

    protected array $crudSearchable = [
        'username' => 'like',
        'realname' => 'like',
        'mobile'   => 'like',
        'status'   => 'eq',
    ];

    protected array $crudModifiable = ['status'];

    protected array $crudFields = [
        'username', 'password', 'trade_password', 'email', 'realname', 'mobile',
        'avatar', 'roles', 'status', 'see_uids', 'auth_str',
    ];

    protected string $crudOrder = 'id asc';

    /**
     * 新建管理员（账号唯一性 + 密码必填）
     */
    public function create()
    {
        $form = $this->crudFormData();
        $username = (string) ($form['username'] ?? '');
        $password = (string) ($form['password'] ?? '');
        if ($username === '' || $password === '') {
            return $this->fail('账号和密码不能为空');
        }
        if (strlen($password) < 8) {
            return $this->fail('密码至少 8 位');
        }
        if (AdminModel::where('username', $username)->count() > 0) {
            return $this->fail('账号已存在');
        }
        $row = new AdminModel();
        $row->allowField($this->crudFields ?: true)->save($form);
        return $this->ok($row, '创建成功');
    }

    /**
     * 更新管理员（密码留空则不修改；账号不可改）
     */
    public function save(int $id)
    {
        $row = AdminModel::findOrEmpty($id);
        if ($row->isEmpty()) {
            return $this->fail('管理员不存在');
        }
        $form = $this->crudFormData();
        unset($form['username']);
        if (($form['password'] ?? '') === '') {
            unset($form['password']);
        } elseif (strlen((string) $form['password']) < 8) {
            return $this->fail('密码至少 8 位');
        }
        if (($form['trade_password'] ?? '') === '') {
            unset($form['trade_password']);
        }
        $row->allowField($this->crudFields ?: true)->save($form);
        return $this->ok($row, '保存成功');
    }

    /**
     * 登录日志
     */
    public function login_log()
    {
        return $this->logQuery(1);
    }

    /**
     * 操作日志
     */
    public function do_log()
    {
        return $this->logQuery(0);
    }

    /**
     * 日志类型统计
     */
    public function do_log_type()
    {
        $days = min(90, max(1, (int) $this->request->param('days', 7)));
        $list = AdminLogModel::where('create_time', '>=', time() - $days * 86400)
            ->field('type, count(*) as num')
            ->group('type')
            ->select();
        return $this->ok($list);
    }

    /**
     * 在线会话列表
     */
    public function login_status()
    {
        $uid  = (int) $this->request->param('uid', 0);
        $page = max(1, (int) $this->request->param('page', 1));
        $limit = min(100, max(1, (int) $this->request->param('limit', 15)));
        $query = AdminLoginStatus::order('create_time', 'desc');
        if ($uid > 0) {
            $query->where('uid', $uid);
        }
        $total = (clone $query)->count();
        return $this->ok(['list' => $query->page($page, $limit)->select(), 'count' => $total]);
    }

    /**
     * 踢下线（删除会话记录）
     */
    public function del_login_status()
    {
        $id  = (int) $this->request->param('id', 0);
        $row = AdminLoginStatus::findOrEmpty($id);
        if ($row->isEmpty()) {
            return $this->fail('会话不存在');
        }
        $row->delete();
        return $this->ok([], '已下线');
    }

    /**
     * 日志查询（type: 0=操作 1=登录）
     */
    protected function logQuery(int $type)
    {
        $page  = max(1, (int) $this->request->param('page', 1));
        $limit = min(200, max(1, (int) $this->request->param('limit', 15)));
        $uid   = (int) $this->request->param('uid', 0);
        $query = AdminLogModel::where('type', $type)->order('id', 'desc');
        if ($uid > 0) {
            $query->where('uid', $uid);
        }
        $total = (clone $query)->count();
        return $this->ok(['list' => $query->page($page, $limit)->select(), 'count' => $total]);
    }
}
