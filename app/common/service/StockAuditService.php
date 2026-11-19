<?php
// +----------------------------------------------------------------------
// | MKSHOP 库存每日对账服务（§5.2 收口配套，红线：只读对账，漂移仅告警不改数）
// | 三条不变量（口径与写入侧完全一致，逐条对应）：
// |   I1 card_cate.card_num == 该卡库 status=1 卡数（deliverByCards 重算口径，不过滤 delete_time）
// |   I2 goods_stock.stock_num(type=1,status=1) == 绑定卡库实际可售卡数（save_goods_stock 绑定语义）
// |   I3 goods.stock_num == SUM(goods_stock.stock_num status=1)（goods_type=1 卡密商品，deliverByCards 汇总口径）
// | type=2 渠道库存行的 stock_num 由上游同步维护（DockSyncService），不在本对账范围
// +----------------------------------------------------------------------
declare (strict_types = 1);

namespace app\common\service;

use app\common\model\Card;
use app\common\model\GoodsStock;
use think\facade\Db;
use think\facade\Log;

class StockAuditService
{
    /**
     * 只读对账：返回三类计数与漂移明细（漂移同时写 Log::error 告警）
     * @return array{cates:int,stocks:int,goods:int,cateDrift:int,stockDrift:int,goodsDrift:int,total:int,detail:array}
     */
    public static function audit(): array
    {
        // I1：卡库计数 vs 实际可售卡数（与 deliverByCards 同款查询：status=1，不过滤 delete_time）
        $cateNums = Db::name('card_cate')->where('delete_time', 0)->column('card_num', 'id');
        $cateDrift = [];
        $realByCate = [];
        foreach ($cateNums as $cateId => $cardNum) {
            $cateId = (int) $cateId;
            $real = (int) Card::where('card_cate_id', $cateId)->where('status', 1)->count();
            $realByCate[$cateId] = $real;
            if ((int) $cardNum !== $real) {
                $cateDrift[] = 'cate#' . $cateId . ' card_num=' . (int) $cardNum . ' real=' . $real;
            }
        }

        // I2：卡密库存桥 stock_num vs 绑定卡库实际可售卡数（与 save_goods_stock 绑定语义一致）
        $stockRows = Db::name('goods_stock')
            ->where('type', GoodsStock::TYPE_CARD)
            ->where('status', 1)
            ->field('id,goods_id,card_cate_id,stock_num')
            ->select()->toArray();
        $stockDrift = [];
        foreach ($stockRows as $row) {
            $cateId = (int) $row['card_cate_id'];
            $real = $realByCate[$cateId] ?? null;
            if ($real === null) {
                $stockDrift[] = 'stock#' . (int) $row['id'] . '(goods#' . (int) $row['goods_id'] . ') 绑定卡库#' . $cateId . ' 不存在或已删';
                continue;
            }
            if ((int) $row['stock_num'] !== $real) {
                $stockDrift[] = 'stock#' . (int) $row['id'] . '(goods#' . (int) $row['goods_id'] . ') stock_num=' . (int) $row['stock_num'] . ' real=' . $real;
            }
        }

        // I3：卡密商品 goods.stock_num vs 其 status=1 库存行之和（与 deliverByCards 汇总口径一致）
        $goodsNums = Db::name('goods')->where('goods_type', 1)->where('delete_time', 0)->column('stock_num', 'id');
        $goodsDrift = [];
        foreach ($goodsNums as $goodsId => $stockNum) {
            $goodsId = (int) $goodsId;
            $sum = (int) Db::name('goods_stock')->where('goods_id', $goodsId)->where('status', 1)->sum('stock_num');
            if ((int) $stockNum !== $sum) {
                $goodsDrift[] = 'goods#' . $goodsId . ' stock_num=' . (int) $stockNum . ' sum=' . $sum;
            }
        }

        $total = count($cateDrift) + count($stockDrift) + count($goodsDrift);
        $detail = array_merge($cateDrift, $stockDrift, $goodsDrift);
        if ($total > 0) {
            try {
                Log::error('[stock_audit] 库存漂移 ' . $total . ' 处：' . implode('; ', array_slice($detail, 0, 20)));
            } catch (\Throwable $e) {
                // 日志失败不阻断对账输出
            }
        }

        return [
            'cates'     => count($cateNums),
            'stocks'    => count($stockRows),
            'goods'     => count($goodsNums),
            'cateDrift' => count($cateDrift),
            'stockDrift' => count($stockDrift),
            'goodsDrift' => count($goodsDrift),
            'total'     => $total,
            'detail'    => $detail,
        ];
    }
}
