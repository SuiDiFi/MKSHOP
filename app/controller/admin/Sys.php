<?php
// +----------------------------------------------------------------------
// | 后台 · 系统配置中心 + 字典（对齐权限点 sys/*）
// +----------------------------------------------------------------------
declare (strict_types = 1);

namespace app\controller\admin;

use app\common\model\Config;
use app\common\model\SysDict;
use mks\util\Dict;
use mks\util\SysConfig;

class Sys extends Base
{
    /**
     * 配置分组表单（数据驱动渲染：name/info/type/parameter/props/value）
     */
    public function config_group_form()
    {
        $group = (string) $this->request->param('group', '');
        if ($group === '') {
            return $this->fail('缺少配置分组');
        }
        $rows = Config::where('group', $group)
            ->where('vip_id', 0)
            ->order('sort', 'asc')
            ->order('id', 'asc')
            ->select();
        // 输出为表单定义（value 已按控件类型反解）
        $list = [];
        foreach ($rows as $row) {
            $item = $row->toArray();
            $item['value'] = SysConfig::get((string) $row->getAttr('name'), null, 0);
            $list[] = $item;
        }
        return $this->ok(['group' => $group, 'list' => $list]);
    }

    /**
     * 保存配置分组（form: {config_name: value, ...}）
     */
    public function save_config_group()
    {
        $group = (string) $this->request->param('group', '');
        $form  = (array) ($this->request->param('form') ?: []);
        if ($group === '' || $form === []) {
            return $this->fail('没有需要保存的配置');
        }
        // 只允许保存该分组下真实存在的配置键
        $names = Config::where('group', $group)->where('vip_id', 0)->column('name');
        $saved = 0;
        foreach ($form as $name => $value) {
            if (!in_array((string) $name, $names, true)) {
                continue; // 白名单外键丢弃
            }
            $store = is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : (string) $value;
            Config::update(['value' => $store, 'update_time' => time()], ['name' => (string) $name, 'vip_id' => 0]);
            SysConfig::flush((string) $name, 0);
            $saved++;
        }
        return $this->ok(['saved' => $saved], '配置已保存');
    }

    /**
     * 配置分组清单（后台 Tab）
     */
    public function config_group_list()
    {
        $rows = Config::field('`group`, count(*) as num')->group('`group`')->select();
        return $this->ok($rows);
    }

    /**
     * 字典选项（公共下拉；已加入白名单）
     */
    public function dict_options()
    {
        $type = (string) $this->request->param('type', '');
        if ($type === '') {
            return $this->fail('缺少字典类型');
        }
        return $this->ok(Dict::options($type));
    }

    /**
     * 字典管理列表
     */
    public function dict_index()
    {
        $type  = (string) $this->request->param('type', '');
        $page  = max(1, (int) $this->request->param('page', 1));
        $limit = min(200, max(1, (int) $this->request->param('limit', 50)));
        $query = SysDict::order('dict_type', 'asc')->order('sort', 'asc');
        if ($type !== '') {
            $query->where('dict_type', $type);
        }
        $total = (clone $query)->count();
        return $this->ok(['list' => $query->page($page, $limit)->select(), 'count' => $total]);
    }

    /**
     * 字典保存/新增
     */
    public function dict_save()
    {
        $id    = (int) $this->request->param('id', 0);
        $form  = (array) ($this->request->param('form') ?: []);
        $allow = ['dict_type', 'code', 'label', 'color', 'sort', 'status'];
        $data  = array_intersect_key($form, array_flip($allow));
        if (!isset($data['dict_type'], $data['code'], $data['label'])) {
            return $this->fail('缺少必要字段');
        }
        $data['dict_type'] = (string) $data['dict_type'];
        $data['code']      = (string) $data['code'];
        if ($id > 0) {
            SysDict::update($data, ['id' => $id]);
        } else {
            $exists = SysDict::where('dict_type', $data['dict_type'])->where('code', $data['code'])->count();
            if ($exists > 0) {
                return $this->fail('该码值已存在');
            }
            SysDict::create($data);
        }
        Dict::flush();
        return $this->ok([], '保存成功');
    }

    /**
     * 字典删除
     */
    public function dict_delete()
    {
        $id  = (int) $this->request->param('id', 0);
        $row = SysDict::findOrEmpty($id);
        if ($row->isEmpty()) {
            return $this->fail('字典项不存在');
        }
        $row->delete();
        Dict::flush();
        return $this->ok([], '删除成功');
    }
}
