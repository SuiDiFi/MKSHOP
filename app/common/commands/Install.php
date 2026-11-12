<?php
// +----------------------------------------------------------------------
// | MKSHOP 一键安装命令（4 步：环境检测 → 建库导入 → 管理员 → 完成）
// 用法：
//   php think mks:install --db-host=mysql --db-port=3306 --db-name=mkshop --db-user=root --db-pass=root \
//        --redis-host=redis --redis-port=6379 --redis-pass= --redis-select=0 \
//        --admin-user=admin --admin-pass=Admin@123456
// 已安装（存在 public/install.lock）时跳过，可用 --force 重装（危险，会清库重建）。
// +----------------------------------------------------------------------
declare (strict_types = 1);

namespace app\common\commands;

use mks\util\SqlImport;
use PDO;
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
            ->addArgument('action', Argument::OPTIONAL, 'check|run', 'run')
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
        $root = $this->app->getRootPath();
        $lock = $root . 'public' . DIRECTORY_SEPARATOR . 'install.lock';

        if (is_file($lock) && $input->getArgument('action') !== 'force') {
            $output->writeln('<comment>已安装（存在 public/install.lock）。如需重装使用 --force（将清库重建）。</comment>');
            return 0;
        }

        // ── Step 1 环境检测 ───────────────────────────────────────────
        $output->writeln('<info>[1/4] 环境检测</info>');
        if (version_compare(PHP_VERSION, '8.0.0', '<')) {
            $output->error('PHP >= 8.0.0 required, current ' . PHP_VERSION);
            return 1;
        }
        foreach (['pdo_mysql', 'redis', 'bcmath', 'gd', 'fileinfo', 'mbstring'] as $ext) {
            if (!extension_loaded($ext)) {
                $output->error("缺少扩展: {$ext}");
                return 1;
            }
        }
        foreach (['runtime', 'public/uploads'] as $dir) {
            $path = $root . $dir;
            if (!is_dir($path)) {
                @mkdir($path, 0755, true);
            }
            if (!is_writable($path)) {
                $output->error("目录不可写: {$dir}");
                return 1;
            }
        }
        $output->writeln('  PHP ' . PHP_VERSION . ' / 扩展齐全 / 目录可写 — <info>OK</info>');

        // ── Step 2 建库 + 导入 SQL ────────────────────────────────────
        $dbHost = (string) $input->getOption('db-host');
        $dbPort = (string) $input->getOption('db-port');
        $dbName = (string) $input->getOption('db-name');
        $dbUser = (string) $input->getOption('db-user');
        $dbPass = (string) $input->getOption('db-pass');

        $output->writeln('<info>[2/4] 建库并导入 SQL（schema 106 表 → seed 字典 → extra 增量）</info>');
        try {
            $pdo = new PDO(
                sprintf('mysql:host=%s;port=%s;charset=utf8mb4', $dbHost, $dbPort),
                $dbUser,
                $dbPass,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 10]
            );
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");
            $pdo->exec("USE `{$dbName}`");

            $sqlDir = $root . 'deploy' . DIRECTORY_SEPARATOR . 'sql' . DIRECTORY_SEPARATOR;

            // 已初始化库（表已存在）跳过 schema/seed，只执行幂等增量 extra
            $exists = $pdo->query("SHOW TABLES LIKE 'mks_config'")->fetch();
            if ($exists && $input->getArgument('action') !== 'force') {
                $output->writeln('  检测到已初始化的数据库 — 跳过 schema/seed 导入');
            } else {
                foreach (['mks_schema.sql', 'mks_seed.sql'] as $file) {
                    $n = SqlImport::importFile($pdo, $sqlDir . $file);
                    $output->writeln("  导入 {$file}: {$n} 条语句 — <info>OK</info>");
                }
            }
            $n = SqlImport::importFile($pdo, $sqlDir . 'mks_extra.sql');
            $output->writeln("  导入 mks_extra.sql: {$n} 条语句（幂等） — <info>OK</info>");
            $n = SqlImport::importFile($pdo, $sqlDir . 'mks_m6.sql');
            $output->writeln("  导入 mks_m6.sql: {$n} 条语句（幂等） — <info>OK</info>");
            $n = SqlImport::importFile($pdo, $sqlDir . 'mks_m6b.sql');
            $output->writeln("  导入 mks_m6b.sql: {$n} 条语句（幂等） — <info>OK</info>");
            $n = SqlImport::importFile($pdo, $sqlDir . 'mks_m6c.sql');
            $output->writeln("  导入 mks_m6c.sql: {$n} 条语句（幂等） — <info>OK</info>");
            // 主站域名兜底（后续可在后台补真实域名）
            $hasMain = $pdo->query("SELECT id FROM `mks_sys_url` WHERE is_main = 1 LIMIT 1")->fetch();
            if (!$hasMain) {
                $pdo->exec("INSERT INTO `mks_sys_url` (vip_id,type,url,is_main,template,template_pc,create_time,update_time) VALUES (0,1,'',1,'default','default',UNIX_TIMESTAMP(),UNIX_TIMESTAMP())");
            }
        } catch (\Throwable $e) {
            $output->error('建库失败: ' . $e->getMessage());
            return 1;
        }

        // ── Step 3 管理员初始化 ───────────────────────────────────────
        $output->writeln('<info>[3/4] 初始化管理员</info>');
        $adminUser = (string) $input->getOption('admin-user');
        $adminPass = (string) $input->getOption('admin-pass');
        if ($adminPass === '') {
            $adminPass = 'Mk@' . bin2hex(random_bytes(4));
            $output->writeln("  未指定密码，已生成随机密码: <comment>{$adminPass}</comment>");
        }
        try {
            $stmt = $pdo->prepare('SELECT id FROM `' . $dbName . '`.`mks_admin` WHERE username = ? LIMIT 1');
            $stmt->execute([$adminUser]);
            $hash  = password_hash($adminPass, PASSWORD_BCRYPT);
            $now   = time();
            if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $pdo->prepare('UPDATE `' . $dbName . '`.`mks_admin` SET password = ?, status = 1, update_time = ? WHERE id = ?')
                    ->execute([$hash, $now, $row['id']]);
                $adminId = (int) $row['id'];
            } else {
                $pdo->prepare('INSERT INTO `' . $dbName . '`.`mks_admin` (roles, username, password, status, create_time, update_time) VALUES (?,?,?,?,?,?)')
                    ->execute(['1', $adminUser, $hash, 1, $now, $now]);
                $adminId = (int) $pdo->lastInsertId();
            }
            // 确保存在超管角色并绑定
            $pdo->exec("INSERT IGNORE INTO `{$dbName}`.`mks_admin_role` (id, name, value, create_time, status, role, level) VALUES (1, '超级管理员', 'super', {$now}, 1, '*', 1)");
            $pdo->prepare('UPDATE `' . $dbName . '`.`mks_admin` SET roles = ? WHERE id = ?')->execute(['1', $adminId]);
            $output->writeln("  管理员 {$adminUser}（id={$adminId}）— <info>OK</info>");
        } catch (\Throwable $e) {
            $output->error('管理员初始化失败: ' . $e->getMessage());
            return 1;
        }

        // ── Step 4 写 .env + lock ─────────────────────────────────────
        $output->writeln('<info>[4/4] 写入 .env 与安装锁</info>');
        $appKey  = bin2hex(random_bytes(32));
        $authKey = bin2hex(random_bytes(24));
        $signKey = bin2hex(random_bytes(24));
        $env = <<<ENV
APP_DEBUG = false

[APP]
DEFAULT_TIMEZONE = Asia/Shanghai

[DATABASE]
TYPE = mysql
HOSTNAME = {$dbHost}
DATABASE = {$dbName}
USERNAME = {$dbUser}
PASSWORD = {$dbPass}
HOSTPORT = {$dbPort}
CHARSET = utf8mb4
PREFIX = mks_

[REDIS]
HOSTNAME = {$input->getOption('redis-host')}
PORT = {$input->getOption('redis-port')}
PASSWORD = {$input->getOption('redis-pass')}
SELECT = {$input->getOption('redis-select')}

[MKS]
APP_KEY = {$appKey}
AUTH_KEY = {$authKey}
SIGN_KEY = {$signKey}
QUEUE_PREFIX = mks_queue

ENV;
        file_put_contents($root . '.env', $env);
        file_put_contents($lock, (string) time());

        $output->writeln('<info>安装完成。访问后台：/admin（登录后请立即修改密码）</info>');
        return 0;
    }
}
