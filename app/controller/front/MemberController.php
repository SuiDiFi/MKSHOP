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
