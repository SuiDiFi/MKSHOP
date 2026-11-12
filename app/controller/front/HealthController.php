<?php
// +----------------------------------------------------------------------
// | 健康检查 /healthz（M6-D）：DB / Redis / 版本；异常返回 503
// +----------------------------------------------------------------------
declare (strict_types = 1);

namespace app\controller\front;

use mks\BaseController;
use think\facade\Cache;
use think\facade\Db;

class HealthController extends BaseController
{
    public function index()
    {
        $start  = microtime(true);
        $checks = [];
        $ok     = true;

        try {
            Db::query('SELECT 1');
            $checks['db'] = ['ok' => true];
        } catch (\Throwable $e) {
            $checks['db'] = ['ok' => false, 'msg' => $e->getMessage()];
            $ok = false;
        }

        try {
            Cache::store('redis')->set('mks:healthz', time(), 10);
            $checks['redis'] = ['ok' => true];
        } catch (\Throwable $e) {
            $checks['redis'] = ['ok' => false, 'msg' => $e->getMessage()];
            $ok = false;
        }

        $version = '';
        try {
            $version = (string) Db::name('schema_version')->order('id desc')->value('version');
        } catch (\Throwable $e) {
            // 升级表未建时忽略
        }

        return json([
            'status'   => $ok ? 'ok' : 'fail',
            'version'  => $version !== '' ? $version : 'unknown',
            'php'      => PHP_VERSION,
            'time'     => date('c'),
            'cost_ms'  => round((microtime(true) - $start) * 1000, 2),
            'checks'   => $checks,
        ], $ok ? 200 : 503);
    }
}
