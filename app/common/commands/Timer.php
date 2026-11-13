<?php
// +----------------------------------------------------------------------
// | MKSHOP 定时任务执行器（sys_timer 机制，M3）
// | 用法：php think mks:timer   （建议宿主机 cron 每分钟执行一次）
// | 语义：
// |   type=0 → cycle=秒（每隔 N 秒）
// |   type=1 → cycle=分钟/秒（如 "1/0" 每 1 分钟）
// | 仅执行已注册 mark；未知 mark 跳过（不阻塞其它任务）
// | 并发安全：以 last_execution_time 为 CAS 抢占，多实例只会有一个执行
// +----------------------------------------------------------------------
declare (strict_types = 1);

namespace app\common\commands;

use app\common\model\SysTimer;
use app\common\service\DockBuService;
use app\common\service\DockSyncService;
use app\common\service\StaticsService;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use think\facade\Log;

class Timer extends Command
{
    /**
     * 任务注册表：mark => callable
     * @var array<string, callable>
     */
    private array $handlers = [];

    protected function configure()
    {
        $this->setName('mks:timer')->setDescription('sys_timer 定时任务执行器（M3）');
    }

    protected function execute(Input $input, Output $output)
    {
        $this->handlers = [
            // 渠道补单 + 处理中订单同步兜底
            'docking_order_bu' => function () {
                $bu = DockBuService::buOrders(50);
                $sync = DockBuService::syncDealing(100);
                return sprintf(
                    'bu{scanned=%d,submitted=%d,failed=%d} sync{scanned=%d,changed=%d,failed=%d}',
                    $bu['scanned'], $bu['submitted'], $bu['failed'],
                    $sync['scanned'], $sync['changed'], $sync['failed']
                );
            },
            // 渠道库存/成本同步（补货）
            'docking_sync_stock' => function () {
                $r = DockSyncService::syncAll(100);
                return sprintf(
                    'dockings=%d,scanned=%d,updated=%d,missing=%d,failed=%d',
                    $r['dockings'], $r['scanned'], $r['updated'], $r['missing'], $r['failed']
                );
            },
            // 分站抽成冻结到期解冻（M6）
            'order_vip_sure_time' => function () {
                $r = \app\common\service\VipSiteService::settleDue(200);
                return sprintf('scanned=%d,settled=%d,failed=%d', $r['scanned'], $r['settled'], $r['failed']);
            },
            // 供货货款冻结到期解冻（M6）
            'order_supply_sure_time' => function () {
                $r = \app\common\service\SupplyService::settleDue(200);
                return sprintf('scanned=%d,settled=%d,failed=%d', $r['scanned'], $r['settled'], $r['failed']);
            },
            // 财务日结对账（M7：昨日客户+渠道对账单）
            'money_day' => function () {
                $date = date('Y-m-d', strtotime('-1 day'));
                $member = \app\common\service\MoneyDayService::generate($date);
                $channel = \app\common\service\MoneyDayService::generateChannel($date);
                return sprintf('date=%s,member=%d,channel=%d', $date, $member, $channel);
            },
            // 按日成交汇总物化（M8：回算近 10 天写 mks_statics，供长区间图表读取）
            'statics_order' => function () {
                $count = StaticsService::aggregate(StaticsService::AGG_DAYS);
                return sprintf('days=%d,until=%s', $count, date('Y-m-d'));
            },
        ];

        $now = time();
        $rows = SysTimer::where('status', 1)
            ->whereIn('mark', array_keys($this->handlers))
            ->where('delete_time', 0)
            ->order('id asc')
            ->select();
        $ran = 0;
        foreach ($rows as $row) {
            $mark = (string) $row->getAttr('mark');
            if (!isset($this->handlers[$mark])) {
                continue;
            }
            $last = (int) $row->getAttr('last_execution_time');
            if (!$this->due((int) $row->getAttr('type'), (string) $row->getAttr('cycle'), $last, $now)) {
                continue;
            }
            // CAS 抢占：只有把 last_execution_time 从旧值改成功的实例才执行
            $affected = SysTimer::where('id', (int) $row->getAttr('id'))
                ->where('last_execution_time', $last)
                ->update(['last_execution_time' => $now]);
            if ($affected === 0) {
                continue;
            }
            $ran++;
            try {
                $msg = ($this->handlers[$mark])();
                $output->writeln("[{$mark}] OK {$msg}");
            } catch (\Throwable $e) {
                $output->writeln("[{$mark}] FAIL " . $e->getMessage());
                try {
                    Log::error('[timer] ' . $mark . ' ' . $e->getMessage());
                } catch (\Throwable $ignore) {
                }
            }
        }
        if ($ran === 0) {
            $output->writeln('[timer] no due task');
        }
        return 0;
    }

    /**
     * 是否到期
     * type=0：cycle 为秒；type=1：cycle 为 "分钟/秒"
     */
    protected function due(int $type, string $cycle, int $last, int $now): bool
    {
        $parts = explode('/', $cycle);
        if ($type === 0) {
            $interval = max(1, (int) ($parts[0] ?? 1));
        } elseif ($type === 1) {
            $minutes = max(0, (int) ($parts[0] ?? 1));
            $seconds = max(0, (int) ($parts[1] ?? 0));
            $interval = $minutes * 60 + $seconds;
            if ($interval <= 0) {
                $interval = 60;
            }
        } else {
            return false; // 未支持的周期类型（小时/天/周/月），交由其它调度器
        }
        if ($last <= 0) {
            return true; // 首次执行
        }
        return ($now - $last) >= $interval;
    }
}
