<?php
// +----------------------------------------------------------------------
// | MKSHOP 后台操作日志中间件（写操作落 mks_admin_log）
// +----------------------------------------------------------------------
declare (strict_types = 1);

namespace mks\middleware;

use app\common\model\AdminLog as AdminLogModel; // 别名导入：避免与本类名 AdminLog 冲突（PHP 类名不区分大小写）
use think\Request;
use think\Response;

class AdminLog
{
    public function handle(Request $request, \Closure $next): Response
    {
        $response = $next($request);

        // 仅记录写操作
        $method = strtoupper((string) $request->method());
        if (!in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return $response;
        }

        try {
            $adminId = (int) ($request->adminId ?? 0);
            if ($adminId <= 0) {
                return $response; // 登录接口未注入，由登录逻辑自行记录
            }
            $path   = (string) $request->pathinfo();
            $res    = is_object($response) && method_exists($response, 'getData') ? (array) $response->getData() : [];
            $isOk   = isset($res['code']) && (int) $res['code'] === 0;

            AdminLogModel::create([
                'uid'         => $adminId,
                'title'       => $path . ($isOk ? '' : ' [失败]'),
                'ip'          => (string) $request->ip(),
                'type'        => 0,
                'change_type' => 0,
                'mark'        => $method . ' ' . $path . ' 参数:' . json_encode($request->only(array_keys($request->param())), JSON_UNESCAPED_UNICODE),
                'create_time' => time(),
                'update_time' => time(),
            ]);
        } catch (\Throwable $e) {
            // 日志失败不影响主流程
        }

        return $response;
    }
}
