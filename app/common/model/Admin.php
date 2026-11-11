<?php
// +----------------------------------------------------------------------
// | 管理员模型
// +----------------------------------------------------------------------
declare (strict_types = 1);

namespace app\common\model;

class Admin extends BaseModel
{
    protected $name = 'admin';

    protected $hidden = ['password', 'trade_password', 'cao_pass'];

    /**
     * 密码校验（bcrypt）
     */
    public function verifyPassword(string $plain): bool
    {
        return password_verify($plain, (string) $this->getAttr('password'));
    }
}
