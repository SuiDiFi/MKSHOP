<?php
// +----------------------------------------------------------------------
// | 卡密库（卡种）模型
// +----------------------------------------------------------------------
declare (strict_types = 1);

namespace app\common\model;

use think\model\concern\SoftDelete;

class CardCate extends BaseModel
{
    use SoftDelete;

    protected $name = 'card_cate';

    protected $deleteTime = 'delete_time';
    protected $defaultSoftDelete = 0;

    protected $hidden = [];
}
