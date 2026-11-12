<?php
// +----------------------------------------------------------------------
// | HTTP 安装向导（M6-D）：仅在未安装时可用；与 CLI 共用 InstallService
// +----------------------------------------------------------------------
declare (strict_types = 1);

namespace app\controller\install;

use app\common\service\InstallService;
use mks\BaseController;

class IndexController extends BaseController
{
    /**
     * 向导页面（单文件 HTML，无需前端构建）
     */
    public function index()
    {
        $file = app()->getAppPath() . 'view' . DIRECTORY_SEPARATOR . 'install' . DIRECTORY_SEPARATOR . 'index.html';
        if (!is_file($file)) {
            return response('安装向导文件缺失', 500);
        }
        $html = str_replace(
            ['{{INSTALLED}}', '{{PHP_VERSION}}'],
            [InstallService::isInstalled() ? '1' : '0', PHP_VERSION],
            (string) file_get_contents($file)
        );
        return response($html, 200, ['Content-Type' => 'text/html; charset=utf-8']);
    }

    /**
     * 环境检测（不触碰数据库）
     */
    public function check()
    {
        if (InstallService::isInstalled()) {
            return json_fail('系统已安装', 403, 1);
        }
        return json_ok(InstallService::envCheck());
    }

    /**
     * 执行安装
     */
    public function run()
    {
        if (InstallService::isInstalled()) {
            return json_fail('系统已安装；如需重装请先删除 install.lock', 403, 1);
        }
        try {
            $result = InstallService::run([
                'db_host'      => (string) $this->request->param('db_host', '127.0.0.1'),
                'db_port'      => (string) $this->request->param('db_port', '3306'),
                'db_name'      => (string) $this->request->param('db_name', 'mkshop'),
                'db_user'      => (string) $this->request->param('db_user', 'root'),
                'db_pass'      => (string) $this->request->param('db_pass', ''),
                'redis_host'   => (string) $this->request->param('redis_host', '127.0.0.1'),
                'redis_port'   => (string) $this->request->param('redis_port', '6379'),
                'redis_pass'   => (string) $this->request->param('redis_pass', ''),
                'redis_select' => (string) $this->request->param('redis_select', '0'),
                'admin_user'   => (string) $this->request->param('admin_user', 'admin'),
                'admin_pass'   => (string) $this->request->param('admin_pass', ''),
            ], true);
        } catch (\Throwable $e) {
            return json_fail($e->getMessage(), 200, 1);
        }
        return json_ok([
            'admin_user' => $result['admin_user'],
            'password'   => $result['password'],
            'admin_url'  => '/admin/',
        ], '安装完成');
    }
}
