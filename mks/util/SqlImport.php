<?php
// +----------------------------------------------------------------------
// | SQL 导入工具（引号感知的语句切分，正确处理字符串内的分号/注释）
// +----------------------------------------------------------------------
declare (strict_types = 1);

namespace mks\util;

use PDO;
use RuntimeException;

class SqlImport
{
    /**
     * 导入 SQL 文件（逐语句执行，事务由语句自身决定）
     */
    public static function importFile(PDO $pdo, string $path): int
    {
        if (!is_file($path)) {
            throw new RuntimeException('SQL 文件不存在: ' . $path);
        }
        $sql    = (string) file_get_contents($path);
        $count  = 0;
        // 防复发：强制连接字符集（历史上曾因导入时连接非 utf8mb4 导致菜单标题存成双重编码乱码）
        $pdo->exec("SET NAMES utf8mb4");
        foreach (self::split($sql) as $stmt) {
            if (trim($stmt) === '') {
                continue;
            }
            $pdo->exec($stmt);
            $count++;
        }
        return $count;
    }

    /**
     * 引号感知切分：' " ` 三类引号 + -- 注释 + # 注释 + /* *\/ 块注释
     * @return string[]
     */
    public static function split(string $sql): array
    {
        $out     = [];
        $buf     = '';
        $len     = strlen($sql);
        $i       = 0;
        $quote   = null;

        while ($i < $len) {
            $ch = $sql[$i];

            // 引号状态
            if ($quote !== null) {
                $buf .= $ch;
                // 转义（\ 与双写引号）
                if ($ch === '\\' && $i + 1 < $len) {
                    $buf .= $sql[$i + 1];
                    $i += 2;
                    continue;
                }
                if ($ch === $quote) {
                    if ($i + 1 < $len && $sql[$i + 1] === $quote) {
                        $buf .= $sql[$i + 1];
                        $i += 2;
                        continue;
                    }
                    $quote = null;
                }
                $i++;
                continue;
            }

            // 进入引号
            if ($ch === "'" || $ch === '"' || $ch === '`') {
                $quote = $ch;
                $buf .= $ch;
                $i++;
                continue;
            }

            // 行注释 -- / #
            if (($ch === '-' && $i + 1 < $len && $sql[$i + 1] === '-') || $ch === '#') {
                while ($i < $len && $sql[$i] !== "\n") {
                    $i++;
                }
                continue;
            }

            // 块注释
            if ($ch === '/' && $i + 1 < $len && $sql[$i + 1] === '*') {
                $i += 2;
                while ($i < $len && !($sql[$i] === '*' && $i + 1 < $len && $sql[$i + 1] === '/')) {
                    $i++;
                }
                $i += 2;
                continue;
            }

            // 语句结束
            if ($ch === ';') {
                $out[] = $buf;
                $buf   = '';
                $i++;
                continue;
            }

            $buf .= $ch;
            $i++;
        }
        if (trim($buf) !== '') {
            $out[] = $buf;
        }
        return $out;
    }
}
