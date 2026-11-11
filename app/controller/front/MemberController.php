<?php
// +----------------------------------------------------------------------
// | 前台 · 会员（M2：登录/我的信息；注册/充值 M4）
// +----------------------------------------------------------------------
declare (strict_types = 1);

namespace app\controller\front;

use app\common\model\Member;
use mks\BaseController;
use mks\util\Jwt;

class MemberController extends BaseController
{
    /**
     * 登录（账号+密码）
     */
    public function login()
    {
        $username = (string) $this->request->param('username', '');
        $password = (string) $this->request->param('password', '');
        if ($username === '' || $password === '') {
            return $this->fail('请输入账号和密码');
        }
        $member = Member::where('username', $username)->where('delete_time', 0)->findOrEmpty();
        if ($member->isEmpty() || !$member->verifyPassword($password)) {
            return $this->fail('账号或密码错误');
        }
        if ((int) $member->getAttr('status') !== 1 || (int) $member->getAttr('close') === 1) {
            return $this->fail('账号已被禁用');
        }
        $jwt  = Jwt::issue('member', (int) $member->getAttr('id'));
        $member->save([
            'last_time'   => time(),
            'last_ip'     => (string) $this->request->ip(),
            'login_count' => (int) $member->getAttr('login_count') + 1,
        ]);
        return $this->ok([
            'token'   => $jwt['token'] ?? '',
            'userinfo' => $member->hidden(['password', 'trade_password'])->toArray(),
        ], '登录成功');
    }

    /**
     * 注册（M4：支持邀请码绑定上级）
     * @param {username, password, invite_code?} invite_code=上级会员ID 或账号
     */
    public function register()
    {
        $username = (string) $this->request->param('username', '');
        $password = (string) $this->request->param('password', '');
        $invite   = trim((string) $this->request->param('invite_code', ''));
        if ($username === '' || $password === '') {
            return $this->fail('请输入账号和密码');
        }
        if (mb_strlen($username, 'UTF-8') < 3 || strlen($password) < 6) {
            return $this->fail('账号至少 3 位、密码至少 6 位');
        }
        if (Member::where('username', $username)->where('delete_time', 0)->count() > 0) {
            return $this->fail('账号已被注册');
        }
        $pid = 0;
        if ($invite !== '') {
            $parent = Member::where('delete_time', 0)->where('status', 1)
                ->where(function ($q) use ($invite) {
                    $q->whereOr('id', (int) $invite)->whereOr('username', $invite);
                })->findOrEmpty();
            if ($parent->isEmpty()) {
                return $this->fail('邀请码无效');
            }
            $pid = (int) $parent->getAttr('id');
        }
        $member = new Member();
        $member->save([
            'username'    => $username,
            'password'    => $password,
            'nickname'    => '用户' . substr((string) time(), -6),
            'user_where'  => 'pc',
            'reg_ip'      => (string) $this->request->ip(),
            'reg_host'    => (string) $this->request->host(),
            'create_time' => time(),
            'pid'         => $pid,
        ]);
        if ($pid > 0) {
            Member::where('id', $pid)->inc('child_num')->update();
        }
        $jwt = Jwt::issue('member', (int) $member->getAttr('id'));
        return $this->ok([
            'token'    => $jwt['token'] ?? '',
            'userinfo' => $member->hidden(['password', 'trade_password'])->toArray(),
        ], '注册成功');
    }

    /**
     * 绑定上级（登录态补绑；pid=0 不可用；防环校验）
     */
    public function bind_parent()
    {
        $member = Member::findOrEmpty((int) ($this->request->memberId ?? 0));
        if ($member->isEmpty()) {
            return $this->fail('请先登录');
        }
        $invite = trim((string) $this->request->param('invite_code', ''));
        if ($invite === '') {
            return $this->fail('请输入邀请码');
        }
        $parent = Member::where('delete_time', 0)->where('status', 1)
            ->where(function ($q) use ($invite) {
                $q->whereOr('id', (int) $invite)->whereOr('username', $invite);
            })->findOrEmpty();
        if ($parent->isEmpty()) {
            return $this->fail('邀请码无效');
        }
        \app\common\service\CommissionService::bindParent(
            (int) $member->getAttr('id'),
            (int) $parent->getAttr('id')
        );
        return $this->ok(['pid' => (int) $parent->getAttr('id')], '绑定成功');
    }

    /**
     * 我的信息（登录态）
     */
    public function info()
    {
        $member = Member::findOrEmpty((int) ($this->request->memberId ?? 0));
        if ($member->isEmpty()) {
            return $this->fail('会员不存在');
        }
        return $this->ok(['userinfo' => $member->hidden(['password', 'trade_password'])->toArray()]);
    }

    /**
     * 退出登录
     */
    public function logout()
    {
        $jti = (string) ($this->request->jti ?? '');
        if ($jti !== '') {
            Jwt::revoke('member', $jti);
        }
        return $this->ok([], '已退出登录');
    }
}
