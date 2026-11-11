<?php
// +----------------------------------------------------------------------
// | 后台 · 导卡历史（对齐权限点 card_history/*，只读）
// +----------------------------------------------------------------------
declare (strict_types = 1);

namespace app\controller\admin;

use app\common\model\CardHistory;
use mks\traits\CurdTrait;

class CardHistoryController extends Base
{
    use CurdTrait;

    protected string $crudModel = CardHistory::class;

    protected array $crudSearchable = [
        'card_cate_id' => 'eq',
        'card_cate_name' => 'like',
        'uid'          => 'eq',
    ];

    protected array $crudModifiable = [];

    protected array $crudFields = [];

    protected string $crudOrder = 'id desc';
}
