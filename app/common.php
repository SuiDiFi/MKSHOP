<?php
// +----------------------------------------------------------------------
// | MKSHOP 全局公共函数
// +----------------------------------------------------------------------
declare (strict_types = 1);

use think\facade\Config;
use think\Response\Json;

if (!function_exists('json_ok')) {
    /**
     * 统一成功响应
     */
    function json_ok(mixed $data = [], string $msg = 'ok', int $code = 0): Json
    {
        return json(['code' => $code, 'msg' => $msg, 'data' => $data, 'time' => time()]);
    }
}

if (!function_exists('json_fail')) {
    /**
     * 统一失败响应（HTTP 状态码与业务码分离：httpCode 传输层，code 业务层）
     */
    function json_fail(string $msg = '操作失败', int $httpCode = 200, int $code = 1, mixed $data = []): Json
    {
        return json(['code' => $code, 'msg' => $msg, 'data' => $data, 'time' => time()], $httpCode);
    }
}

if (!function_exists('mks_config')) {
    /**
     * 读取系统配置（mks_config 表，带 Redis 缓存；未安装时回退 config('mks.')）
     */
    function mks_config(string $name, mixed $default = null, int $vipId = 0): mixed
    {
        try {
            return \mks\util\SysConfig::get($name, $default, $vipId);
        } catch (Throwable $e) {
            return Config::get('mks.' . $name, $default);
        }
    }
}

if (!function_exists('mks_money')) {
    /**
     * 金额格式化（红线：bcmath，HALF_UP；内部统一 4 位小数）
     */
    function mks_money(string|float|int $amount, int $scale = 4): string
    {
        return bcadd((string) $amount, '0', $scale);
    }
}

if (!function_exists('mks_time')) {
    /**
     * 当前时间戳（统一入口）
     */
    function mks_time(): int
    {
        return time();
    }
}

if (!function_exists('throw_fail')) {
    /**
     * 抛出业务失败异常（由 ExceptionHandle 统一渲染）
     */
    function throw_fail(string $msg, int $code = 1): never
    {
        throw new \mks\exception\BizException($msg, $code);
    }
}

if (!function_exists('response_csv')) {
    /**
     * CSV 下载响应（带 BOM，Excel 直开不乱码）
     * @param string $filename 下载文件名
     * @param array  $header   表头（一维数组）
     * @param array  $rows     数据行（二维数组）
     */
    function response_csv(string $filename, array $header, array $rows): \think\Response
    {
        $fp = fopen('php://temp', 'r+');
        fwrite($fp, "\xEF\xBB\xBF");
        fputcsv($fp, $header);
        foreach ($rows as $row) {
            fputcsv($fp, array_map(fn ($v) => (string) ($v ?? ''), array_values($row)));
        }
        rewind($fp);
        $csv = (string) stream_get_contents($fp);
        fclose($fp);
        return response($csv, 200, [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . rawurlencode($filename) . '"',
            'Cache-Control'       => 'no-store',
        ]);
    }
}
