<?php
// +----------------------------------------------------------------------
// | MKSHOP 渠道补单/同步任务命令（M3）
// | 用法：php think mks:dockbu [--limit=50]
// | 行为：1) 渠道下单失败自动补单  2) 处理中订单同步兜底
// | 生产接入：sys_timer cron 周期执行（建议 1~5 分钟一次）
// +----------------------------------------------------------------------
declare (strict_types = 1);

namespace app\common\commands;

use app\common\service\DockBuService;
use think\console\Command;
use think\console\input\Option;
use think\console\Input;
use think\console\Output;

class DockBu extends Command
{
    protected function configure()
    {
        $this->setName('mks:dockbu')
            ->setDescription('渠道补单 + 处理中订单同步兜底（M3）')
            ->addOption('limit', null, Option::VALUE_REQUIRED, '单轮处理订单数上限', '50');
    }

    protected function execute(Input $input, Output $output)
    {
        $limit = max(1, (int) ($input->getOption('limit') ?: 50));
        $bu    = DockBuService::buOrders($limit);
        $output->writeln(sprintf(
            '[buOrders] scanned=%d submitted=%d failed=%d',
            $bu['scanned'], $bu['submitted'], $bu['failed']
        ));
        $sync  = DockBuService::syncDealing($limit);
        $output->writeln(sprintf(
            '[syncDealing] scanned=%d changed=%d failed=%d',
            $sync['scanned'], $sync['changed'], $sync['failed']
        ));
        return 0;
    }
}
