<?php
// +----------------------------------------------------------------------
// | MKSHOP 渠道补货命令（M3）
// | 用法：php think mks:docksync [--limit=100]
// | 行为：拉取上游商品列表，回写本地 goods_stock(type=2) 库存与成本
// | 生产接入：sys_timer（mark=docking_sync_stock）或系统 cron
// +----------------------------------------------------------------------
declare (strict_types = 1);

namespace app\common\commands;

use app\common\service\DockSyncService;
use think\console\Command;
use think\console\input\Option;
use think\console\Input;
use think\console\Output;

class DockSync extends Command
{
    protected function configure()
    {
        $this->setName('mks:docksync')
            ->setDescription('渠道库存/成本补货同步（M3）')
            ->addOption('limit', null, Option::VALUE_REQUIRED, '上游商品列表分页大小', '100');
    }

    protected function execute(Input $input, Output $output)
    {
        $limit = min(500, max(1, (int) ($input->getOption('limit') ?: 100)));
        $stat = DockSyncService::syncAll($limit);
        $output->writeln(sprintf(
            '[docksync] dockings=%d scanned=%d updated=%d missing=%d failed=%d',
            $stat['dockings'], $stat['scanned'], $stat['updated'], $stat['missing'], $stat['failed']
        ));
        return 0;
    }
}
