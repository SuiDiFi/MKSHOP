<?php
// +----------------------------------------------------------------------
// | MKSHOP 升级器（M6-D）：按文件名顺序执行 deploy/sql/upgrade/*.sql
// 用法：
//   php think mks:upgrade           执行未执行的升级脚本
//   php think mks:upgrade --status  查看版本与待执行脚本
//   php think mks:upgrade --dry-run 仅列出待执行脚本
//   php think mks:upgrade --baseline 将现有升级脚本标记为已执行（存量部署接管）
// 约定：文件命名 YYYYMMDD_描述.sql；失败即停，修复后重跑（已成功的不会重复执行）
// +----------------------------------------------------------------------
declare (strict_types = 1);

namespace app\common\commands;

use mks\util\SqlImport;
use PDO;
use think\console\Command;
use think\console\input\Option;
use think\console\Input;
use think\console\Output;

class Upgrade extends Command
{
    protected function configure()
    {
        $this->setName('mks:upgrade')
            ->setDescription('执行 deploy/sql/upgrade/*.sql 增量升级')
            ->addOption('status', null, Option::VALUE_NONE, '查看版本与待执行脚本')
            ->addOption('dry-run', null, Option::VALUE_NONE, '仅列出待执行脚本，不执行')
            ->addOption('baseline', null, Option::VALUE_NONE, '将现有脚本标记为已执行（不执行 SQL）');
    }

    protected function execute(Input $input, Output $output)
    {
        $root = $this->app->getRootPath();
        $dir  = $root . 'deploy' . DIRECTORY_SEPARATOR . 'sql' . DIRECTORY_SEPARATOR . 'upgrade' . DIRECTORY_SEPARATOR;

        try {
            $pdo = $this->pdo();
            $this->ensureTable($pdo);
        } catch (\Throwable $e) {
            $output->error('数据库连接失败: ' . $e->getMessage());
            return 1;
        }

        $files = is_dir($dir) ? glob($dir . '*.sql') : [];
        $files = $files ?: [];
        sort($files, SORT_STRING);
        $done = $pdo->query('SELECT version FROM `mks_schema_version`')->fetchAll(PDO::FETCH_COLUMN) ?: [];
        $pending = [];
        foreach ($files as $file) {
            $version = basename($file);
            if (!in_array($version, $done, true)) {
                $pending[] = $file;
            }
        }

        if ($input->getOption('status')) {
            $output->writeln('<info>已执行版本</info>');
            $rows = $pdo->query('SELECT version, executed_time FROM `mks_schema_version` ORDER BY id asc')->fetchAll(PDO::FETCH_ASSOC);
            if (!$rows) {
                $output->writeln('  （无）');
            }
            foreach ($rows as $row) {
                $output->writeln('  ' . $row['version'] . '  ' . date('Y-m-d H:i:s', (int) $row['executed_time']));
            }
            $output->writeln('<info>待执行</info>');
            if (!$pending) {
                $output->writeln('  （无）');
            }
            foreach ($pending as $file) {
                $output->writeln('  ' . basename($file));
            }
            return 0;
        }

        if ($input->getOption('baseline')) {
            foreach ($pending as $file) {
                $this->record($pdo, basename($file), 'baseline');
                $output->writeln('  标记已执行: ' . basename($file));
            }
            $output->writeln('<info>baseline 完成</info>');
            return 0;
        }

        if (!$pending) {
            $output->writeln('<info>没有待执行的升级脚本</info>');
            return 0;
        }

        $dry = (bool) $input->getOption('dry-run');
        foreach ($pending as $file) {
            $version = basename($file);
            if ($dry) {
                $output->writeln('  [dry-run] ' . $version);
                continue;
            }
            try {
                $n = $this->importFile($pdo, $file);
                $this->record($pdo, $version, 'success');
                $output->writeln("  {$version}: {$n} 条语句 — <info>OK</info>");
            } catch (\Throwable $e) {
                $this->record($pdo, $version, 'fail');
                $output->error("  {$version} 失败: " . $e->getMessage());
                $output->writeln('<comment>已停止；修复后重跑 mks:upgrade（成功文件不会重复执行）。</comment>');
                return 1;
            }
        }
        if ($dry) {
            $output->writeln('<info>dry-run 结束</info>');
            return 0;
        }
        $output->writeln('<info>升级完成。如本次改动涉及表结构，请重启 PHP 并清理缓存。</info>');
        return 0;
    }

    /**
     * 执行 SQL 文件（逐语句；SELECT 等结果集需 closeCursor，避免 unbuffered 报错）
     */
    protected function importFile(PDO $pdo, string $file): int
    {
        $n = 0;
        foreach (SqlImport::split((string) file_get_contents($file)) as $stmt) {
            $stmt = trim($stmt);
            if ($stmt === '') {
                continue;
            }
            $res = $pdo->query($stmt);
            if ($res instanceof \PDOStatement) {
                $res->closeCursor();
            }
            $n++;
        }
        return $n;
    }

    /**
     * 建立 PDO（复用 .env 的 mysql 配置）
     */
    protected function pdo(): PDO
    {
        $cfg = (array) config('database.connections.mysql');
        $dsn = !empty($cfg['dsn'])
            ? (string) $cfg['dsn']
            : sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s',
                $cfg['hostname'] ?? '127.0.0.1',
                $cfg['hostport'] ?? '3306',
                $cfg['database'] ?? 'mkshop',
                $cfg['charset'] ?? 'utf8mb4');
        return new PDO($dsn, (string) ($cfg['username'] ?? 'root'), (string) ($cfg['password'] ?? ''), [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT            => 10,
            // 升级脚本可能包含 SELECT，强制缓冲避免后续语句报 unbuffered 错误
            PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
        ]);
    }

    /**
     * 版本记录表（幂等）
     */
    protected function ensureTable(PDO $pdo): void
    {
        $pdo->exec("CREATE TABLE IF NOT EXISTS `mks_schema_version` (
            `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT 'ID',
            `version` varchar(120) NOT NULL DEFAULT '' COMMENT '升级脚本文件名',
            `status` varchar(20) NOT NULL DEFAULT '' COMMENT 'success/fail/baseline',
            `executed_time` int NOT NULL DEFAULT 0 COMMENT '执行时间',
            PRIMARY KEY (`id`),
            UNIQUE KEY `uk_version` (`version`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='数据库升级版本记录'");
    }

    /**
     * 记录版本（同一文件重跑时更新状态）
     */
    protected function record(PDO $pdo, string $version, string $status): void
    {
        $stmt = $pdo->prepare('INSERT INTO `mks_schema_version` (`version`,`status`,`executed_time`) VALUES (?,?,?)
            ON DUPLICATE KEY UPDATE `status` = VALUES(`status`), `executed_time` = VALUES(`executed_time`)');
        $stmt->execute([$version, $status, time()]);
    }
}
