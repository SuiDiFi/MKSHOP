<?php
// +----------------------------------------------------------------------
// | 管理员模型
// +----------------------------------------------------------------------
declare (strict_types = 1);

namespace app\common\model;

use think\model\concern\SoftDelete;

class Admin extends BaseModel
{
    use SoftDelete;

    protected $name = 'admin';

    /** 软删字段（0=未删，红线：统一 delete_time） */
    protected $deleteTime = 'delete_time';
    protected $defaultSoftDelete = 0;

    protected $hidden = ['password', 'trade_password', 'cao_pass'];

    /**
     * 密码校验（bcrypt）
     */
    public function verifyPassword(string $plain): bool
    {
        return password_verify($plain, (string) $this->getAttr('password'));
    }

    /**
     * 密码哈希（修改器）
     */
    public function setPasswordAttr($value)
    {
        return $value === '' ? '' : password_hash((string) $value, PASSWORD_BCRYPT);
    }
}
