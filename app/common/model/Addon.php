<?php
// +----------------------------------------------------------------------
// | 插件注册表模型（M6-C）
// +----------------------------------------------------------------------
declare (strict_types = 1);

namespace app\common\model;

class Addon extends BaseModel
{
    protected $name = 'addon';

    // 本表无 create_time（用 install_time），关闭自动时间戳由业务显式写入
    protected $autoWriteTimestamp = false;

    /** 状态 */
    public const ST_DISABLE = 0;
    public const ST_ENABLE  = 1;

    public static function statusText(int $status): string
    {
        return $status === self::ST_ENABLE ? '已启用' : '已停用';
    }
}
