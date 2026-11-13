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
        // 站点基础信息（主站配置 / 分站覆盖）
        $vipSite = $this->request->vipSite ?? null;
        $info    = \app\common\service\VipSiteService::siteInfo($vipSite);
        $site = [
            'vip_id'      => $info['vip_id'],
            'name'        => $info['name'] !== '' ? $info['name'] : (string) SysConfig::get('site_name', 'MKSHOP 商城'),
            'logo'        => $info['logo'] !== '' ? $info['logo'] : (string) SysConfig::get('site_logo', ''),
            'keyword'     => $info['seo_title'] !== '' ? $info['seo_title'] : (string) SysConfig::get('site_keyword', '自动发卡,虚拟商品'),
            'desc'        => $info['seo_description'] !== '' ? $info['seo_description'] : (string) SysConfig::get('site_desc', 'MKSHOP 自动发卡商城'),
            'theme_color' => $info['theme_color'],
            'close_site'  => $info['close_site'],
            'close_tip'   => $info['close_tip'],
            'kefu_qq'     => $info['kefu_qq'],
            'kefu_wx'     => $info['kefu_wx'],
        ];

        $cates = GoodsCategory::where('status', 1)
            ->order('sort desc, id asc')
            ->field('id,pid,name,goods_num')
            ->limit(12)
            ->select()->toArray();

        $hotQuery = Goods::where('status', 1)
            ->whereIn('check_status', [3, 9])
            ->where('delete_time', 0);
        if ($vipSite) {
            $hotQuery = \app\common\service\VipPriceService::applyVisibleScope($hotQuery, $vipSite);
        } else {
            $hotQuery = $hotQuery->where('vip_id', 0);
        }
        // 商品权限白名单（M7-P2：登录会员仅见授权商品；游客不受约束）
        $memberId = (int) ($this->request->memberId ?? 0);
        $hotQuery = \app\common\service\UserPriceService::applyRightScope($hotQuery, $memberId > 0 ? $memberId : null);
        $hot = $hotQuery->order('ishot desc, sort desc, id desc')
            ->limit(8)
            ->field('id,goods_name,goods_img,real_price,face_value,stock_num,sales_num,virtual_num,ishot,vip_id')
            ->select()->toArray();
        if ($vipSite) {
            $ovRows = \app\common\model\VipGoodsPrice::where('vip_id', (int) $vipSite->getAttr('id'))
                ->whereIn('goods_id', array_column($hot, 'id'))
                ->where('status', 1)
                ->select()->toArray();
            $overrides = array_column($ovRows, null, 'goods_id');
            foreach ($hot as &$h) {
                $ov = $overrides[(int) $h['id']] ?? null;
                $ovModel = $ov === null ? null : (new \app\common\model\VipGoodsPrice())->data($ov, true);
                $p = \app\common\service\VipPriceService::price((string) $h['real_price'], $vipSite, $ovModel);
                $h['base_price'] = $p['base'];
                $h['real_price'] = $p['price'];
            }
            unset($h);
        }

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
