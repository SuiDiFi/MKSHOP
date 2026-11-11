<?php
// +----------------------------------------------------------------------
// | 会员模型（M2）
// +----------------------------------------------------------------------
declare (strict_types = 1);

namespace app\common\model;

use think\model\concern\SoftDelete;

class Member extends BaseModel
{
    use SoftDelete;

    protected $name = 'member';

    protected $deleteTime = 'delete_time';
    protected $defaultSoftDelete = 0;

    protected $hidden = ['password', 'trade_password', 'income_key', 'salt', 'c'];

    /**
     * 密码哈希（bcrypt，与管理端同强度）
     */
    public function setPasswordAttr($value)
    {
        return $value === '' ? '' : password_hash((string) $value, PASSWORD_BCRYPT);
    }

    /**
     * 密码校验
     */
    public function verifyPassword(string $plain): bool
    {
        return password_verify($plain, (string) $this->getAttr('password'));
    }

    /**
     * API 密钥加密入库（AES-GCM；dockapi 下游验签用）
     */
    public function setIncomeKeyAttr($value)
    {
        return $value === '' ? '' : \mks\util\Crypt::encrypt((string) $value);
    }

    /**
     * API 密钥明文（用于 dockapi 验签；红线：仅服务层调用，不外露）
     */
    public function getIncomeKeyPlain(): string
    {
        return \mks\util\Crypt::decrypt((string) $this->getAttr('income_key'));
    }
}
