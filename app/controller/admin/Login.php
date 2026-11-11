<?php
// +----------------------------------------------------------------------
// | 后台登录（JWT + Redis 会话；操作日志）
// +----------------------------------------------------------------------
declare (strict_types = 1);

namespace app\controller\admin;

use app\common\model\Admin;
use app\common\model\AdminLog;
use mks\middleware\AdminAuth;
use mks\util\Jwt;

class Login extends Base
{
    /**
     * 登录
     */
    public function login()
    {
        $username = (string) $this->request->param('username', '');
        $password = (string) $this->request->param('password', '');
        if ($username === '' || $password === '') {
            return $this->fail('请输入账号和密码');
        }

        $admin = Admin::where('username', $username)->where('delete_time', 0)->findOrEmpty();
        if ($admin->isEmpty() || !$admin->verifyPassword($password)) {
            return $this->fail('账号或密码错误');
        }
        if ((int) $admin->getAttr('status') !== 1) {
            return $this->fail('账号已被禁用');
        }

        $token = Jwt::issue('admin', (int) $admin->getAttr('id'));

        // 登录统计
        $admin->save([
            'last_time'   => time(),
            'last_ip'     => (string) $this->request->ip(),
            'login_count' => (int) $admin->getAttr('login_count') + 1,
        ]);

        // 登录日志
        try {
            AdminLog::create([
                'uid'         => (int) $admin->getAttr('id'),
                'title'       => 'login/login',
                'ip'          => (string) $this->request->ip(),
                'type'        => 1,
                'change_type' => 0,
                'mark'        => '后台登录',
                'create_time' => time(),
                'update_time' => time(),
            ]);
        } catch (\Throwable $e) {
            // 日志失败不影响登录
        }

        return $this->ok([
            'token'      => $token['token'],
            'expires_at' => $token['expires_at'],
            'userinfo'   => $admin->toArray(),
            'is_super'   => AdminAuth::isSuper($admin->toArray()),
        ], '登录成功');
    }

    /**
     * 当前管理员信息
     */
    public function getInfo()
    {
        $info = $this->adminInfo();
        return $this->ok([
            'userinfo' => $info,
            'is_super' => AdminAuth::isSuper($info),
        ]);
    }

    /**
     * 后台菜单树（按角色过滤）
     */
    public function getMenus()
    {
        return $this->ok(AdminAuth::menusOf($this->adminInfo()));
    }

    /**
     * 登出
     */
    public function logout()
    {
        $jti = (string) ($this->request->jti ?? '');
        if ($jti !== '') {
            Jwt::revoke('admin', $jti);
        }
        return $this->ok([], '已退出登录');
    }
}
