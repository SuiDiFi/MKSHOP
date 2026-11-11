<?php
// +----------------------------------------------------------------------
// | 卡密模型（红线：卡号/卡密 AES-256-GCM 加密存储 + MD5 去重索引）
// +----------------------------------------------------------------------
declare (strict_types = 1);

namespace app\common\model;

use mks\util\Crypt;
use think\model\concern\SoftDelete;

class Card extends BaseModel
{
    use SoftDelete;

    protected $name = 'card';

    protected $deleteTime = 'delete_time';
    protected $defaultSoftDelete = 0;

    /**
     * 写入加密：card_no / card_password（已加密文本防二次加密由 Crypt 内部保证）
     */
    public function setCardNoAttr($value): string
    {
        $plain = (string) $value;
        if ($plain === '' || str_starts_with($plain, 'mksv1:')) {
            return $plain;
        }
        return Crypt::encrypt($plain);
    }

    public function setCardPasswordAttr($value): string
    {
        $plain = (string) $value;
        if ($plain === '' || str_starts_with($plain, 'mksv1:')) {
            return $plain;
        }
        return Crypt::encrypt($plain);
    }

    /**
     * 读取解密（管理端查看）
     */
    public function getCardNoAttr($value): string
    {
        return Crypt::decrypt((string) $value);
    }

    public function getCardPasswordAttr($value): string
    {
        return Crypt::decrypt((string) $value);
    }
}
