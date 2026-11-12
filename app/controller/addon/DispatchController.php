<?php
// +----------------------------------------------------------------------
// | 插件路由分发（M6-C）：/addons/:addon/:controller/:action
// | 校验：插件已安装且启用；控制器文件必须位于 addons/<name>/controller/
// | 返回：插件控制器可直接返回 Response / 数组 / 字符串
// +----------------------------------------------------------------------
declare (strict_types = 1);

namespace app\controller\addon;

use app\common\model\Addon;
use app\common\service\AddonService;
use mks\BaseController;
use think\facade\Log;
use think\Response;

class DispatchController extends BaseController
{
    public function index(string $addon = '', string $controller = 'index', string $action = 'index')
    {
        $addon = strtolower(preg_replace('/[^a-zA-Z0-9_]/', '', $addon) ?: '');
        if (!AddonService::validName($addon)) {
            return json_fail('插件不存在', 404, 1);
        }
        $row = Addon::where('name', $addon)->where('status', Addon::ST_ENABLE)
            ->where('delete_time', 0)->findOrEmpty();
        if ($row->isEmpty()) {
            return json_fail('插件未安装或已停用', 404, 1);
        }
        $controller = preg_replace('/[^a-zA-Z0-9_]/', '', $controller) ?: 'index';
        $action     = preg_replace('/[^a-zA-Z0-9_]/', '', $action) ?: 'index';
        $file = AddonService::dir() . $addon . DIRECTORY_SEPARATOR . 'controller'
            . DIRECTORY_SEPARATOR . ucfirst($controller) . '.php';
        if (!is_file($file)) {
            return json_fail('插件控制器不存在', 404, 1);
        }
        require_once $file;
        $class = 'addons\\' . $addon . '\\controller\\' . ucfirst($controller);
        if (!class_exists($class)) {
            return json_fail('插件控制器类不存在', 404, 1);
        }
        try {
            $obj = new $class($this->app);
            $method = lcfirst($action);
            if (!method_exists($obj, $method)) {
                return json_fail('插件方法不存在', 404, 1);
            }
            $result = $obj->{$method}();
        } catch (\Throwable $e) {
            Log::error('[addon] dispatch ' . $addon . '/' . $controller . '/' . $action . ': ' . $e->getMessage());
            return json_fail('插件执行异常', 200, 1);
        }
        if ($result instanceof Response) {
            return $result;
        }
        if (is_array($result) || is_scalar($result) || $result === null) {
            return json_ok($result ?? []);
        }
        return response((string) $result);
    }
}
