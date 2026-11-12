<?php
// +----------------------------------------------------------------------
// | 插件钩子分发（M6-C）
// | 语义：按 mks_addon.hooks 反查启用插件 → 调用 Plugin::on{CamelName}
// | 红线：任何插件异常只记日志，绝不阻断主流程（下单/支付/退款）
// +----------------------------------------------------------------------
declare (strict_types = 1);

namespace mks\util;

use app\common\service\AddonService;
use think\facade\Log;

class Hook
{
    /**
     * 触发钩子
     * @param string $name    钩子名，如 order.paid
     * @param array  $payload 上下文（订单/会员等只读数据）
     */
    public static function fire(string $name, array $payload = []): void
    {
        try {
            if (!SysConfig::get('addon_enable', true)) {
                return;
            }
        } catch (\Throwable $e) {
            return; // 未安装/配置表不存在时静默跳过
        }
        foreach (AddonService::listeners($name) as $addon) {
            try {
                $plugin = AddonService::instance($addon);
                if ($plugin === null) {
                    continue;
                }
                $method = self::methodName($name);
                if (!method_exists($plugin, $method)) {
                    continue;
                }
                $plugin->{$method}($payload);
            } catch (\Throwable $e) {
                Log::error('[hook] ' . $name . ' @' . $addon . ': ' . $e->getMessage());
            }
        }
    }

    /**
     * 钩子名 → 方法名：order.paid → onOrderPaid
     */
    public static function methodName(string $name): string
    {
        $method = 'on';
        foreach (explode('.', $name) as $part) {
            $part = preg_replace('/[^a-zA-Z0-9_]/', '', $part) ?: '';
            $method .= ucfirst($part);
        }
        return $method;
    }
}
