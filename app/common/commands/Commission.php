<?php
// +----------------------------------------------------------------------
// | MKSHOP 分销佣金结算命令（M4）
// | 用法：php think mks:commission [--limit=100]
// | 行为：冻结期（config mks.commission.sure_time>0）到期的 order_commission 入账
// | 生产接入：sys_timer cron 周期执行（建议 1~5 分钟一次）
// +----------------------------------------------------------------------
declare (strict_types = 1);

namespace app\common\commands;

use app\common\service\CommissionService;
use think\console\Command;
use think\console\input\Option;
use think\console\Input;
use think\console\Output;

class Commission extends Command
{
    protected function configure()
    {
        $this->setName('mks:commission')
            ->setDescription('分销佣金到期结算（M4）')
            ->addOption('limit', null, Option::VALUE_REQUIRED, '单轮处理条数上限', '100');
    }

    protected function execute(Input $input, Output $output)
    {
        $limit = max(1, (int) ($input->getOption('limit') ?: 100));
        $stat  = CommissionService::settleDue($limit);
        $output->writeln(sprintf(
            '[settleDue] scanned=%d settled=%d failed=%d',
            $stat['scanned'], $stat['settled'], $stat['failed']
        ));
        return 0;
    }
}
