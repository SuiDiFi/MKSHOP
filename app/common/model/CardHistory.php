<?php
// +----------------------------------------------------------------------
// | 导卡历史模型（批次记录，只增不改；表无 delete_time/update_time）
// +----------------------------------------------------------------------
declare (strict_types = 1);

namespace app\common\model;

class CardHistory extends BaseModel
{
    protected $name = 'card_history';

    protected $updateTime = false; // 表无 update_time 字段
}
