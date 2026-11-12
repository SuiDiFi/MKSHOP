<?php
// +----------------------------------------------------------------------
// | 后台 · 插件管理（M6-C；权限点 addon/*）
// | MVP：仅允许从服务器本地 addons/ 目录安装（后台上传 zip 不开放，见 D15）
// +----------------------------------------------------------------------
declare (strict_types = 1);

namespace app\controller\admin;

use app\common\model\Addon;
use app\common\service\AddonService;
use think\facade\Db;

class AddonController extends Base
{
    /**
     * 插件列表（本地目录扫描 + 已安装注册表合并）
     */
    public function index()
    {
        $local = AddonService::scan();
        $exists = array_column($local, 'name');
        // 已安装但本地目录已删除：仍列出以便卸载
        foreach (Addon::where('delete_time', 0)->order('id asc')->select() as $row) {
            $name = (string) $row->getAttr('name');
            if (in_array($name, $exists, true)) {
                continue;
            }
            $local[] = [
                'name'        => $name,
                'title'       => (string) $row->getAttr('title'),
                'version'     => (string) $row->getAttr('version'),
                'author'      => (string) $row->getAttr('author'),
                'description' => (string) $row->getAttr('description'),
                'hooks'       => json_decode((string) $row->getAttr('hooks'), true) ?: [],
                'menus'       => json_decode((string) $row->getAttr('menus'), true) ?: [],
                'installed'   => true,
                'db_id'       => (int) $row->getAttr('id'),
                'status'      => (int) $row->getAttr('status'),
                'status_text' => Addon::statusText((int) $row->getAttr('status')),
                'missing'     => true,
            ];
        }
        return $this->ok(['list' => $local, 'count' => count($local)]);
    }

    /**
     * 本地扫描（仅目录，不读注册表）
     */
    public function scan()
    {
        return $this->ok(AddonService::scan());
    }

    /**
     * 插件详情（含配置表单 schema 与菜单快照）
     */
    public function read(int $id)
    {
        $row = Addon::where('id', $id)->where('delete_time', 0)->findOrEmpty();
        if ($row->isEmpty()) {
            return $this->fail('插件不存在');
        }
        $name = (string) $row->getAttr('name');
        return $this->ok([
            'addon'  => $row->toArray(),
            'schema' => AddonService::schema($name),
            'config' => AddonService::config($name),
            'local'  => AddonService::manifest($name),
            'hooks'  => json_decode((string) $row->getAttr('hooks'), true) ?: [],
            'menus'  => json_decode((string) $row->getAttr('menus'), true) ?: [],
        ]);
    }

    /**
     * 框架内置钩子清单
     */
    public function hooks()
    {
        $out = [];
        foreach (AddonService::HOOKS as $name => $title) {
            $out[] = ['name' => $name, 'title' => $title];
        }
        return $this->ok($out);
    }

    /**
     * 安装（默认安装后启用）
     * @param {name}
     */
    public function install()
    {
        $name = trim((string) $this->request->param('name', ''));
        $row  = AddonService::install($name, true);
        return $this->ok($row, '安装成功');
    }

    /**
     * 启用
     */
    public function enable()
    {
        AddonService::enable((int) $this->request->param('id', 0));
        return $this->ok([], '已启用');
    }

    /**
     * 停用
     */
    public function disable()
    {
        AddonService::disable((int) $this->request->param('id', 0));
        return $this->ok([], '已停用');
    }

    /**
     * 卸载（默认保留数据表）
     * @param {id, drop_data:0/1}
     */
    public function uninstall()
    {
        AddonService::uninstall(
            (int) $this->request->param('id', 0),
            (int) $this->request->param('drop_data', 0) === 1
        );
        return $this->ok([], '已卸载');
    }

    /**
     * 保存插件配置（仅 config.php 声明字段）
     */
    public function config_save()
    {
        $id   = (int) $this->request->param('id', 0);
        $form = $this->request->param('form', []);
        if (!is_array($form)) {
            $form = [];
        }
        $config = AddonService::saveConfig($id, $form);
        return $this->ok($config, '保存成功');
    }
}
