<?php
// +----------------------------------------------------------------------
// | MKSHOP 模型基类（软删统一 delete_time；字段严格；int 时间戳）
// +----------------------------------------------------------------------
declare (strict_types = 1);

namespace app\common\model;

use think\Model;

abstract class BaseModel extends Model
{
    // 全库无 update_time 自动管理表也兼容：开启自动时间戳（int）
    protected $autoWriteTimestamp = true;

    // 全局软删条件（delete_time = 0），统一走 scope，禁止裸查询漏删数据
    public function scopeNotDeleted($query)
    {
        return $query->where($this->getTable() . '.delete_time', 0);
    }
}
