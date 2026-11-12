<?php
// +----------------------------------------------------------------------
// | MKSHOP 一键安装命令（与 HTTP 安装向导共用 InstallService）
// 用法：
//   php think mks:install check
//   php think mks:install run --db-host=mysql --db-port=3306 --db-name=mkshop --db-user=root --db-pass=root \
//        --redis-host=redis --redis-port=6379 --redis-pass= --redis-select=0 \
//        --admin-user=admin --admin-pass=Admin@123456
// 已安装（项目根 install.lock）时跳过；action=force 可重装（危险）。
// +----------------------------------------------------------------------
declare (strict_types = 1);

namespace app\common\commands;

use app\common\service\InstallService;
use think\console\Command;
use think\console\input\Argument;
use think\console\input\Option;
use think\console\Input;
use think\console\Output;

class Install extends Command
{
    protected function configure()
    {
        $this->setName('mks:install')
            ->setDescription('MKSHOP 一键安装（建库/导入字典/初始化管理员/写入 .env）')
            ->addArgument('action', Argument::OPTIONAL, 'check|run|force', 'run')
            ->addOption('db-host', null, Option::VALUE_REQUIRED, '数据库主机', '127.0.0.1')
            ->addOption('db-port', null, Option::VALUE_REQUIRED, '数据库端口', '3306')
            ->addOption('db-name', null, Option::VALUE_REQUIRED, '数据库名', 'mkshop')
            ->addOption('db-user', null, Option::VALUE_REQUIRED, '数据库用户', 'root')
            ->addOption('db-pass', null, Option::VALUE_REQUIRED, '数据库密码', '')
            ->addOption('redis-host', null, Option::VALUE_REQUIRED, 'Redis 主机', '127.0.0.1')
            ->addOption('redis-port', null, Option::VALUE_REQUIRED, 'Redis 端口', '6379')
            ->addOption('redis-pass', null, Option::VALUE_REQUIRED, 'Redis 密码', '')
            ->addOption('redis-select', null, Option::VALUE_REQUIRED, 'Redis 库号', '0')
            ->addOption('admin-user', null, Option::VALUE_REQUIRED, '管理员账号', 'admin')
            ->addOption('admin-pass', null, Option::VALUE_REQUIRED, '管理员密码', '');
    }

    protected function execute(Input $input, Output $output)
    {
        $action = (string) $input->getArgument('action');

        // ── 环境检测 ─────────────────────────────────────────────────
        if ($action === 'check') {
            $check = InstallService::envCheck();
            foreach ($check['items'] as $item) {
                $output->writeln(($item['ok'] ? '<info>[OK]</info> ' : '<error>[FAIL]</error> ')
                    . $item['name'] . ' — ' . $item['value']);
            }
            return $check['ok'] ? 0 : 1;
        }

        if (InstallService::isInstalled() && $action !== 'force') {
            $output->writeln('<comment>已安装（存在 install.lock）。如需重装使用 action=force（将覆盖 .env 与管理员密码）。</comment>');
            return 0;
        }

        $output->writeln('<info>[1/4] 环境检测</info>');
        $check = InstallService::envCheck();
        foreach ($check['items'] as $item) {
            if (!$item['ok']) {
                $output->error('检测失败：' . $item['name'] . ' — ' . $item['value']);
                return 1;
            }
        }
        $output->writeln('  PHP ' . PHP_VERSION . ' / 扩展齐全 / 目录可写 — <info>OK</info>');

        $output->writeln('<info>[2/4] 建库并导入 SQL</info>');
        $output->writeln('<info>[3/4] 初始化管理员</info>');
        $output->writeln('<info>[4/4] 写入 .env 与安装锁</info>');
        try {
            $result = InstallService::run([
                'db_host'      => (string) $input->getOption('db-host'),
                'db_port'      => (string) $input->getOption('db-port'),
                'db_name'      => (string) $input->getOption('db-name'),
                'db_user'      => (string) $input->getOption('db-user'),
                'db_pass'      => (string) $input->getOption('db-pass'),
                'redis_host'   => (string) $input->getOption('redis-host'),
                'redis_port'   => (string) $input->getOption('redis-port'),
                'redis_pass'   => (string) $input->getOption('redis-pass'),
                'redis_select' => (string) $input->getOption('redis-select'),
                'admin_user'   => (string) $input->getOption('admin-user'),
                'admin_pass'   => (string) $input->getOption('admin-pass'),
            ], true, static function (string $msg) use ($output): void {
                $output->writeln('  ' . $msg);
            });
        } catch (\Throwable $e) {
            $output->error('安装失败: ' . $e->getMessage());
            return 1;
        }

        if (!empty($result['password'])) {
            $output->writeln("  未指定管理员密码，已生成随机密码: <comment>{$result['password']}</comment>");
        }
        $output->writeln('<info>安装完成。访问后台：/admin（登录后请立即修改密码）</info>');
        return 0;
    }
}
