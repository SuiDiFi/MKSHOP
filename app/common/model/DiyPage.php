<?php
// +----------------------------------------------------------------------
// | DIY 页面模型（M5b；pid=20000 PC 首页，template_id=2 PC 端）
// | 设计决策：整页组件数组 JSON 存 diy_page.value（规避 diy_page_component 的
// | (name,template_id) 唯一键与多楼层冲突问题；组件实例表留后续扩展）
// +----------------------------------------------------------------------
declare (strict_types = 1);

namespace app\common\model;

class DiyPage extends BaseModel
{
    protected $name = 'diy_page';

    /** 页面类型（MKSHOP 使用子集） */
    public const PID_PC_HOME = 20000; // PC 首页

    /** 端类型 */
    public const TYPE_PC = 1;

    /** MKSHOP 内置组件库（编辑器与前台渲染器共用清单） */
    public const COMPONENTS = [
        ['name' => 'Banner',    'title' => '轮播图',   'desc' => '图片轮播，支持跳转链接'],
        ['name' => 'Notice',    'title' => '公告条',   'desc' => '公告文案'],
        ['name' => 'Menus',     'title' => '金刚区',   'desc' => '图标快捷导航'],
        ['name' => 'GoodsList', 'title' => '商品楼层', 'desc' => '按分类或指定商品展示'],
        ['name' => 'RichText',  'title' => '富文本',   'desc' => '自定义图文内容'],
    ];

    /**
     * value 列安全解码（数组；兼容脏数据）
     */
    public function componentsValue(): array
    {
        $raw = (string) ($this->getAttr('value') ?? '');
        $data = json_decode($raw !== '' ? $raw : '[]', true);
        return is_array($data) ? $data : [];
    }
}
