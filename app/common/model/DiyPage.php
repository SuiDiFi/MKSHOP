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
        ['name' => 'Search',      'title' => '搜索框',   'desc' => '商品搜索入口'],
        ['name' => 'Banner',      'title' => '轮播图',   'desc' => '图片轮播，支持跳转链接'],
        ['name' => 'Notice',      'title' => '公告条',   'desc' => '公告文案'],
        ['name' => 'Menus',       'title' => '金刚区',   'desc' => '图标快捷导航'],
        ['name' => 'CategoryNav', 'title' => '分类导航', 'desc' => '按分类快捷进入'],
        ['name' => 'ImageAd',     'title' => '单图广告', 'desc' => '整行广告图'],
        ['name' => 'GoodsList',   'title' => '商品楼层', 'desc' => '按分类或指定商品展示'],
        ['name' => 'RichText',    'title' => '富文本',   'desc' => '自定义图文内容'],
    ];

    /**
     * 首页模板预设（MVP：三个内置模板，应用后覆盖当前组件序列）
     */
    public const TEMPLATES = [
        [
            'key'   => 'classic',
            'name'  => '经典商城',
            'desc'  => '轮播 + 金刚区 + 公告 + 热卖楼层',
            'components' => [
                ['name' => 'Banner', 'title' => '轮播图', 'value' => ['height' => 300, 'list' => [['img' => '', 'url' => ''], ['img' => '', 'url' => '']]]],
                ['name' => 'Menus', 'title' => '金刚区', 'value' => ['list' => [
                    ['icon' => '📱', 'title' => '话费充值', 'url' => '#/category'],
                    ['icon' => '🎮', 'title' => '游戏中心', 'url' => '#/category'],
                    ['icon' => '📋', 'title' => '我的订单', 'url' => '#/orders'],
                    ['icon' => '💰', 'title' => '分销中心', 'url' => '#/commission'],
                ]]],
                ['name' => 'Notice', 'title' => '公告条', 'value' => ['text' => '欢迎使用 MKSHOP 虚拟商城，全场自动发货！']],
                ['name' => 'GoodsList', 'title' => '热卖推荐', 'value' => ['title' => '热卖推荐', 'source' => 'cate', 'cate_id' => 0, 'goods_ids' => '', 'num' => 8]],
                ['name' => 'RichText', 'title' => '富文本', 'value' => ['html' => '<div style="padding:20px;text-align:center;color:#909399;font-size:12px">MKSHOP · 自动发卡商城</div>']],
            ],
        ],
        [
            'key'   => 'minimal',
            'name'  => '简约列表',
            'desc'  => '搜索 + 分类 + 商品列表，转化优先',
            'components' => [
                ['name' => 'Search', 'title' => '搜索框', 'value' => ['placeholder' => '搜索商品']],
                ['name' => 'CategoryNav', 'title' => '分类导航', 'value' => ['title' => '商品分类', 'limit' => 8]],
                ['name' => 'GoodsList', 'title' => '全部商品', 'value' => ['title' => '全部商品', 'source' => 'cate', 'cate_id' => 0, 'goods_ids' => '', 'num' => 12]],
            ],
        ],
        [
            'key'   => 'promo',
            'name'  => '活动推广',
            'desc'  => '广告图 + 轮播 + 金刚区 + 限时特惠',
            'components' => [
                ['name' => 'ImageAd', 'title' => '单图广告', 'value' => ['img' => '', 'url' => '', 'height' => 120]],
                ['name' => 'Banner', 'title' => '轮播图', 'value' => ['height' => 240, 'list' => [['img' => '', 'url' => '']]]],
                ['name' => 'Menus', 'title' => '金刚区', 'value' => ['list' => [
                    ['icon' => '🔥', 'title' => '限时特惠', 'url' => '#/category'],
                    ['icon' => '🎁', 'title' => '新人福利', 'url' => '#/help'],
                    ['icon' => '📢', 'title' => '商城公告', 'url' => '#/notices'],
                    ['icon' => '🎧', 'title' => '在线客服', 'url' => '#/kefu'],
                ]]],
                ['name' => 'GoodsList', 'title' => '限时特惠', 'value' => ['title' => '限时特惠', 'source' => 'cate', 'cate_id' => 0, 'goods_ids' => '', 'num' => 8]],
            ],
        ],
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
