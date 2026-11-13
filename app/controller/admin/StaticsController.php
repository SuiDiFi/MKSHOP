<?php
// +----------------------------------------------------------------------
// | 后台 · 运营统计（M8 对齐 KASUSHOU 首页统计/商品统计/用户统计/交易分析）
// | 接口名与种子菜单的 api_url 一一对应（index_statics / goods_statics / ...）
// | 全部只读聚合，不写业务表；唯一写操作 statics_aggregate 用于物化表回算
// +----------------------------------------------------------------------
declare (strict_types = 1);

namespace app\controller\admin;

use app\common\service\StaticsService;
use mks\BaseController;

class StaticsController extends BaseController
{
    /**
     * 首页统计（看板）
     * GET /admin/index_statics
     */
    public function indexStatics()
    {
        return $this->ok(StaticsService::overview());
    }

    /**
     * 交易统计
     * GET /admin/buy_statics?start=&end=
     */
    public function buyStatics()
    {
        return $this->ok(StaticsService::buyStatics(
            (string) $this->request->param('start', ''),
            (string) $this->request->param('end', '')
        ));
    }

    /**
     * 交易统计图表（按日）
     * GET /admin/buy_statics_trend?start=&end=
     */
    public function buyStaticsTrend()
    {
        return $this->ok(StaticsService::buyTrend(
            (string) $this->request->param('start', ''),
            (string) $this->request->param('end', '')
        ));
    }

    /**
     * 交易统计今日图表（按小时）
     * GET /admin/buy_statics_today
     */
    public function buyStaticsToday()
    {
        return $this->ok(StaticsService::buyToday());
    }

    /**
     * 商品统计
     * GET /admin/goods_statics?start=&end=
     */
    public function goodsStatics()
    {
        return $this->ok(StaticsService::goodsStatics(
            (string) $this->request->param('start', ''),
            (string) $this->request->param('end', '')
        ));
    }

    /**
     * 商品排行统计
     * GET /admin/goods_statics_list?start=&end=&sort=sales|amount|orders|browse&limit=20
     */
    public function goodsStaticsList()
    {
        return $this->ok(StaticsService::goodsRank(
            (string) $this->request->param('start', ''),
            (string) $this->request->param('end', ''),
            (string) $this->request->param('sort', 'sales'),
            (int) $this->request->param('limit', 20)
        ));
    }

    /**
     * 商品统计图表（按日）
     * GET /admin/goods_statics_trend?start=&end=
     */
    public function goodsStaticsTrend()
    {
        return $this->ok(StaticsService::goodsTrend(
            (string) $this->request->param('start', ''),
            (string) $this->request->param('end', '')
        ));
    }

    /**
     * 用户统计
     * GET /admin/user_statics?start=&end=
     */
    public function userStatics()
    {
        return $this->ok(StaticsService::userStatics(
            (string) $this->request->param('start', ''),
            (string) $this->request->param('end', '')
        ));
    }

    /**
     * 用户统计图表（按日）
     * GET /admin/user_statics_trend?start=&end=
     */
    public function userStaticsTrend()
    {
        return $this->ok(StaticsService::userTrend(
            (string) $this->request->param('start', ''),
            (string) $this->request->param('end', '')
        ));
    }

    /**
     * 手动回算按日成交物化表（默认最近 10 天，可指定区间）
     * POST /admin/statics_aggregate { days } 或 { start, end }
     */
    public function aggregate()
    {
        $start = (string) $this->request->param('start', '');
        $end   = (string) $this->request->param('end', '');
        if ($start !== '' && $end !== '') {
            [$from, $to] = StaticsService::resolveRange($start, $end, 10);
            $days = 0;
            $cur  = strtotime($from);
            $stop = strtotime($to);
            while ($cur <= $stop) {
                StaticsService::writeDay(date('Y-m-d', $cur));
                $cur += 86400;
                $days++;
            }
            return $this->ok(['count' => $days, 'range' => [$from, $to]], "已回算 {$from} ~ {$to} 共 {$days} 天");
        }
        $days = max(1, min(90, (int) $this->request->param('days', StaticsService::AGG_DAYS)));
        $count = StaticsService::aggregate($days);
        return $this->ok(['count' => $count], "已回算最近 {$count} 天成交汇总");
    }
}
