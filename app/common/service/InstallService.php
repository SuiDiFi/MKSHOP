<?php
// +----------------------------------------------------------------------
// | 安装服务（M6-D）：CLI `mks:install` 与 HTTP 安装向导共用
// | 锁文件：项目根 install.lock（权威）；兼容读取历史 public/install.lock
// | 步骤：环境检测 → 建库导入 → 初始化管理员 → 写 .env 与安装锁
// +----------------------------------------------------------------------
declare (strict_types = 1);

namespace app\common\service;

use mks\util\SqlImport;
use PDO;
use Throwable;

class InstallService
{
    /** 核心增量 SQL（按顺序执行，均幂等） */
    public const CORE_SQL = ['mks_extra.sql', 'mks_m6.sql', 'mks_m6b.sql', 'mks_m6c.sql'];

    /** 运行目录检查项 */
    public const WRITABLE_DIRS = ['runtime', 'public/uploads'];

    /**
     * 项目根目录
     */
    public static function root(): string
    {
        return rtrim(app()->getRootPath(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    }

    /**
     * 安装锁（项目根，权威）
     */
    public static function lockPath(): string
    {
        return self::root() . 'install.lock';
    }

    /**
     * 历史安装锁（public/ 下，兼容旧部署）
     */
    public static function legacyLockPath(): string
    {
        return self::root() . 'public' . DIRECTORY_SEPARATOR . 'install.lock';
    }

    /**
     * 是否已安装
     */
    public static function isInstalled(): bool
    {
        return is_file(self::lockPath()) || is_file(self::legacyLockPath());
    }

    /**
     * 环境检测（不触碰数据库）
     * @return array{ok:bool,items:array}
     */
    public static function envCheck(): array
    {
        $items = [];
        $ok    = true;

        $phpOk = version_compare(PHP_VERSION, '8.0.0', '>=');
        $items[] = ['name' => 'PHP 版本', 'ok' => $phpOk, 'value' => PHP_VERSION, 'need' => '>= 8.0.0'];
        $ok = $ok && $phpOk;

        foreach (['pdo_mysql', 'redis', 'bcmath', 'gd', 'fileinfo', 'mbstring'] as $ext) {
            $loaded  = extension_loaded($ext);
            $items[] = ['name' => '扩展 ' . $ext, 'ok' => $loaded, 'value' => $loaded ? '已安装' : '缺失', 'need' => '必装'];
            $ok      = $ok && $loaded;
        }

        foreach (self::WRITABLE_DIRS as $dir) {
            $path = self::root() . $dir;
            if (!is_dir($path)) {
                @mkdir($path, 0755, true);
            }
            $writable = is_dir($path) && is_writable($path);
            $items[]  = ['name' => '目录 ' . $dir, 'ok' => $writable, 'value' => $writable ? '可写' : '不可写', 'need' => '可写'];
            $ok       = $ok && $writable;
        }

        $rootWritable = is_writable(self::root());
        $items[] = ['name' => '项目根目录可写（.env / install.lock）', 'ok' => $rootWritable, 'value' => $rootWritable ? '可写' : '不可写', 'need' => '可写'];
        $ok = $ok && $rootWritable;

        return ['ok' => $ok, 'items' => $items];
    }

    /**
     * 执行安装
     * @param array $opt 键：db_host/db_port/db_name/db_user/db_pass、redis_xxx、admin_user/admin_pass
     * @param bool  $writeEnv 是否写 .env 与安装锁（E2E 可关闭）
     * @param callable|null $log 进度回调 fn(string $msg)
     * @return array{admin_id:int,admin_user:string,password:?string,fresh:bool}
     */
    public static function run(array $opt, bool $writeEnv = true, ?callable $log = null): array
    {
        $log = $log ?? static function (string $msg): void {
        };

        $check = self::envCheck();
        if (!$check['ok']) {
            $bad = array_filter($check['items'], fn ($i) => !$i['ok']);
            throw_fail('环境检测未通过：' . implode('；', array_column($bad, 'name')));
        }
        $log('环境检测通过');

        $dbHost = trim((string) ($opt['db_host'] ?? '127.0.0.1'));
        $dbPort = trim((string) ($opt['db_port'] ?? '3306'));
        $dbName = trim((string) ($opt['db_name'] ?? 'mkshop'));
        $dbUser = trim((string) ($opt['db_user'] ?? 'root'));
        $dbPass = (string) ($opt['db_pass'] ?? '');
        if ($dbHost === '' || $dbName === '' || $dbUser === '') {
            throw_fail('数据库主机/库名/用户名不能为空');
        }
        if (!preg_match('/^[A-Za-z0-9_]{1,64}$/', $dbName)) {
            throw_fail('数据库名仅允许字母/数字/下划线');
        }

        // ── 建库 + 导入 ───────────────────────────────────────────────
        try {
            $pdo = new PDO(
                sprintf('mysql:host=%s;port=%s;charset=utf8mb4', $dbHost, $dbPort),
                $dbUser,
                $dbPass,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 10, PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true]
            );
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");
            $pdo->exec("USE `{$dbName}`");
        } catch (Throwable $e) {
            throw_fail('数据库连接失败：' . $e->getMessage());
        }

        $sqlDir = self::root() . 'deploy' . DIRECTORY_SEPARATOR . 'sql' . DIRECTORY_SEPARATOR;
        $fresh  = !$pdo->query("SHOW TABLES LIKE 'mks_config'")->fetch();
        if ($fresh) {
            foreach (['mks_schema.sql', 'mks_seed.sql'] as $file) {
                $n = SqlImport::importFile($pdo, $sqlDir . $file);
                $log("导入 {$file}: {$n} 条语句");
            }
        } else {
            $log('检测到已初始化数据库，跳过 schema/seed');
        }
        foreach (self::CORE_SQL as $file) {
            $n = SqlImport::importFile($pdo, $sqlDir . $file);
            $log("导入 {$file}: {$n} 条语句（幂等）");
        }

        // 主站域名兜底（后续在后台补真实域名）
        $hasMain = $pdo->query('SELECT id FROM `mks_sys_url` WHERE is_main = 1 LIMIT 1')->fetch();
        if (!$hasMain) {
            $pdo->exec("INSERT INTO `mks_sys_url` (vip_id,type,url,is_main,template,template_pc,create_time,update_time) VALUES (0,1,'',1,'default','default',UNIX_TIMESTAMP(),UNIX_TIMESTAMP())");
        }

        // ── 管理员 ───────────────────────────────────────────────────
        $adminUser = trim((string) ($opt['admin_user'] ?? 'admin'));
        $adminPass = (string) ($opt['admin_pass'] ?? '');
        if ($adminUser === '') {
            $adminUser = 'admin';
        }
        $generated = null;
        if ($adminPass === '') {
            $adminPass = 'Mk@' . bin2hex(random_bytes(4));
            $generated = $adminPass;
        }
        if (strlen($adminPass) < 6) {
            throw_fail('管理员密码至少 6 位');
        }
        $stmt = $pdo->prepare('SELECT id FROM `mks_admin` WHERE username = ? LIMIT 1');
        $stmt->execute([$adminUser]);
        $hash = password_hash($adminPass, PASSWORD_BCRYPT);
        $now  = time();
        if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $pdo->prepare('UPDATE `mks_admin` SET password = ?, status = 1, update_time = ? WHERE id = ?')
                ->execute([$hash, $now, $row['id']]);
            $adminId = (int) $row['id'];
        } else {
            $pdo->prepare('INSERT INTO `mks_admin` (roles, username, password, status, create_time, update_time) VALUES (?,?,?,?,?,?)')
                ->execute(['1', $adminUser, $hash, 1, $now, $now]);
            $adminId = (int) $pdo->lastInsertId();
        }
        $pdo->exec("INSERT IGNORE INTO `mks_admin_role` (id, name, value, create_time, status, role, level) VALUES (1, '超级管理员', 'super', {$now}, 1, '*', 1)");
        $pdo->prepare('UPDATE `mks_admin` SET roles = ? WHERE id = ?')->execute(['1', $adminId]);
        $log("管理员 {$adminUser}（id={$adminId}）");

        // ── .env + 安装锁 ────────────────────────────────────────────
        if ($writeEnv) {
            self::writeEnv([
                'db_host'      => $dbHost,
                'db_port'      => $dbPort,
                'db_name'      => $dbName,
                'db_user'      => $dbUser,
                'db_pass'      => $dbPass,
                'redis_host'   => (string) ($opt['redis_host'] ?? '127.0.0.1'),
                'redis_port'   => (string) ($opt['redis_port'] ?? '6379'),
                'redis_pass'   => (string) ($opt['redis_pass'] ?? ''),
                'redis_select' => (string) ($opt['redis_select'] ?? '0'),
            ]);
            file_put_contents(self::lockPath(), (string) $now);
            $log('写入 .env 与安装锁');
        }

        return ['admin_id' => $adminId, 'admin_user' => $adminUser, 'password' => $generated, 'fresh' => $fresh];
    }

    /**
     * 写 .env（覆盖）；APP_KEY 等随机生成
     */
    public static function writeEnv(array $cfg): void
    {
        $appKey  = bin2hex(random_bytes(32));
        $authKey = bin2hex(random_bytes(24));
        $signKey = bin2hex(random_bytes(24));
        $env = <<<ENV
APP_DEBUG = false

[APP]
DEFAULT_TIMEZONE = Asia/Shanghai

[DATABASE]
TYPE = mysql
HOSTNAME = {$cfg['db_host']}
DATABASE = {$cfg['db_name']}
USERNAME = {$cfg['db_user']}
PASSWORD = {$cfg['db_pass']}
HOSTPORT = {$cfg['db_port']}
CHARSET = utf8mb4
PREFIX = mks_

[REDIS]
HOSTNAME = {$cfg['redis_host']}
PORT = {$cfg['redis_port']}
PASSWORD = {$cfg['redis_pass']}
SELECT = {$cfg['redis_select']}

[MKS]
APP_KEY = {$appKey}
AUTH_KEY = {$authKey}
SIGN_KEY = {$signKey}
QUEUE_PREFIX = mks_queue

ENV;
        file_put_contents(self::root() . '.env', $env);
        if (function_exists('opcache_reset')) {
            @opcache_reset();
        }
    }
}
