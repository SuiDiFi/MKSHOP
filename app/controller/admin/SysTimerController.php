<?php
// +----------------------------------------------------------------------
// | 后台 · 定时任务管理（对齐权限点 sys_timer/*；执行引擎由队列/Timer 承担）
// +----------------------------------------------------------------------
declare (strict_types = 1);

namespace app\controller\admin;

use app\common\model\SysTimer;
use mks\traits\CurdTrait;

class SysTimerController extends Base
{
    use CurdTrait;

    protected string $crudModel = SysTimer::class;

    protected array $crudSearchable = [
        'name'   => 'like',
        'title'  => 'like',
        'status' => 'eq',
    ];

    protected array $crudModifiable = ['status'];

    protected array $crudFields = ['name', 'mark', 'type', 'title', 'status', 'cycle', 'url'];

    protected string $crudOrder = 'id asc';

    /**
     * 任务名称选项（下拉）
     */
    public function task_name()
    {
        // 任务注册表：随里程碑扩充（M0 内置核心任务）
        $tasks = [
            ['name' => 'order_auto_cancel',  'title' => '订单超时自动取消'],
            ['name' => 'order_auto_receive', 'title' => '订单自动收货'],
            ['name' => 'docking_sync_stock', 'title' => '上游库存价格同步'],
            ['name' => 'money_day_report',   'title' => '资金日报汇总'],
            ['name' => 'card_stock_warning', 'title' => '卡密库存预警'],
        ];
        return $this->ok($tasks);
    }
}
