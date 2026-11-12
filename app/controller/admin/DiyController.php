<?php
// +----------------------------------------------------------------------
// | 后台 · DIY 装修（M5b；PC 首页 pid=20000，整页组件 JSON 存 diy_page.value）
// +----------------------------------------------------------------------
declare (strict_types = 1);

namespace app\controller\admin;

use app\common\model\DiyPage;
use mks\BaseController;
use think\facade\Db;

class DiyController extends BaseController
{
    /**
     * 页面列表（MVP：PC 首页一条，不存在则自动创建）
     */
    public function index()
    {
        $page = DiyPage::where('pid', DiyPage::PID_PC_HOME)->where('delete_time', 0)->findOrEmpty();
        if ($page->isEmpty()) {
            $page = new DiyPage();
            $page->save([
                'pid'         => DiyPage::PID_PC_HOME,
                'template_id' => 2,
                'type'        => DiyPage::TYPE_PC,
                'name'        => 'pc_home',
                'title'       => '商城首页',
                'desc'        => 'PC 商城首页装修',
                'value'       => '[]',
                'status'      => 0,
                'is_show'     => 1,
                'create_time' => time(),
                'update_time' => time(),
            ]);
        }
        return $this->ok([
            'list' => [DiyPage::findOrEmpty((int) $page->getAttr('id'))->toArray()],
            'components' => DiyPage::COMPONENTS,
        ]);
    }

    /**
     * 页面详情（value 解码为组件数组）
     */
    public function read(int $id)
    {
        $page = DiyPage::where('id', $id)->where('delete_time', 0)->findOrEmpty();
        if ($page->isEmpty()) {
            return $this->fail('页面不存在');
        }
        $data = $page->toArray();
        $data['components'] = $page->componentsValue();
        return $this->ok($data);
    }

    /**
     * 保存页面（title/value 整页组件数组；值校验：仅允许内置组件）
     */
    public function save(int $id)
    {
        $page = DiyPage::where('id', $id)->where('delete_time', 0)->findOrEmpty();
        if ($page->isEmpty()) {
            return $this->fail('页面不存在');
        }
        $allowed = array_column(DiyPage::COMPONENTS, 'name');
        $title = mb_substr((string) $this->request->param('title', ''), 0, 100, 'UTF-8');
        $status = (int) $this->request->param('status', 0);
        $comps = $this->request->param('components');
        $clean = [];
        if (is_array($comps)) {
            $sort = 0;
            foreach ($comps as $c) {
                if (!is_array($c)) {
                    continue;
                }
                $name = (string) ($c['name'] ?? '');
                if (!in_array($name, $allowed, true)) {
                    return $this->fail('未知组件：' . $name);
                }
                $clean[] = [
                    'name'  => $name,
                    'title' => mb_substr((string) ($c['title'] ?? ''), 0, 100, 'UTF-8'),
                    'sort'  => $sort++,
                    'value' => is_array($c['value'] ?? null) ? $c['value'] : [],
                ];
            }
        }
        $page->save([
            'title'       => $title,
            'status'      => $status === 1 ? 1 : 0,
            'value'       => json_encode($clean, JSON_UNESCAPED_UNICODE),
            'update_time' => time(),
        ]);
        return $this->ok(['id' => (int) $page->getAttr('id')], '保存成功');
    }

    /**
     * 组件库清单 + 内置模板
     */
    public function components()
    {
        return $this->ok([
            'components' => DiyPage::COMPONENTS,
            'templates'  => DiyPage::TEMPLATES,
        ]);
    }
}
