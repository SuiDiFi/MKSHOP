<?php
// +----------------------------------------------------------------------
// | 前台 · 首页聚合（M5：站点信息/分类/热销/公告，减少首屏请求）
// +----------------------------------------------------------------------
declare (strict_types = 1);

namespace app\controller\front;

use app\common\model\CmsArticle;
use app\common\model\Goods;
use app\common\model\GoodsCategory;
use mks\BaseController;
use mks\util\SysConfig;

class IndexController extends BaseController
{
    public function index()
    {
        // 站点基础信息（配置缺省时给出可用默认）
        $site = [
            'name'    => (string) SysConfig::get('site_name', 'MKSHOP 商城'),
            'logo'    => (string) SysConfig::get('site_logo', ''),
            'keyword' => (string) SysConfig::get('site_keyword', '自动发卡,虚拟商品'),
            'desc'    => (string) SysConfig::get('site_desc', 'MKSHOP 自动发卡商城'),
        ];

        $cates = GoodsCategory::where('status', 1)
            ->order('sort desc, id asc')
            ->field('id,pid,name,goods_num')
            ->limit(12)
            ->select()->toArray();

        $hot = Goods::where('status', 1)
            ->whereIn('check_status', [3, 9])
            ->where('delete_time', 0)
            ->order('ishot desc, sort desc, id desc')
            ->limit(8)
            ->field('id,goods_name,goods_img,real_price,face_value,stock_num,sales_num,virtual_num,ishot')
            ->select()->toArray();

        $notices = CmsArticle::where('type', CmsArticle::TYPE_NOTICE)
            ->where('status', 1)
            ->order('is_top desc, sort desc, id desc')
            ->limit(5)
            ->field('id,title,summary,create_time')
            ->select()->toArray();

        return $this->ok([
            'site'    => $site,
            'cates'   => $cates,
            'hot'     => $hot,
            'notices' => $notices,
        ]);
    }
}
