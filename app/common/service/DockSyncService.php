<?php
// +----------------------------------------------------------------------
// | MKSHOP 渠道补货（库存/成本同步）服务（M3）
// | 拉取上游 /api/v1/goods/list，按 goods_stock(type=2, docking_goods_id) 回写：
// |   stock_num=上游库存、cost_value=上游进价、名称/面值/图片快照、sync_time
// | 上游已下架/查无此商品 → stock_num=0 + docking_goods_delete_status=1
// | 入口：php think mks:docksync（sys_timer 定时触发）
// +----------------------------------------------------------------------
declare (strict_types = 1);

namespace app\common\service;

use app\common\model\Docking;
use app\common\model\GoodsStock;
use think\facade\Log;

class DockSyncService
{
    /**
     * 同步全部启用渠道的库存/成本
     * @return array{dockings:int, scanned:int, updated:int, missing:int, failed:int}
     */
    public static function syncAll(int $limit = 100): array
    {
        $stat = ['dockings' => 0, 'scanned' => 0, 'updated' => 0, 'missing' => 0, 'failed' => 0];
        $ids = Docking::where('status', 1)->order('sort desc, id asc')->column('id');
        foreach ($ids as $id) {
            $docking = Docking::findOrEmpty((int) $id);
            if ($docking->isEmpty()) {
                continue;
            }
            $stat['dockings']++;
            try {
                $r = self::syncDocking($docking, $limit);
                $stat['scanned'] += $r['scanned'];
                $stat['updated'] += $r['updated'];
                $stat['missing'] += $r['missing'];
                $stat['failed'] += $r['failed'];
            } catch (\Throwable $e) {
                $stat['failed']++;
                self::log('渠道 ' . $id . ' 库存同步失败：' . $e->getMessage());
            }
        }
        return $stat;
    }

    /**
     * 同步单个渠道
     * @return array{scanned:int, updated:int, missing:int, failed:int}
     */
    public static function syncDocking(Docking $docking, int $limit = 100): array
    {
        $stat = ['scanned' => 0, 'updated' => 0, 'missing' => 0, 'failed' => 0];
        $dockingId = (int) $docking->getAttr('id');
        if ((int) $docking->getAttr('status') !== 1) {
            throw_fail('渠道已停用', 1);
        }
        // 未绑定任何本地商品则跳过（避免无意义拉取）
        $bound = GoodsStock::where('docking_id', $dockingId)
            ->where('type', GoodsStock::TYPE_DOCKING)
            ->where('docking_goods_delete_status', 0)
            ->count();
        if ($bound === 0) {
            return $stat;
        }

        $seen = [];
        $page = 1;
        while ($page <= 100) {
            $data = DockingV2Service::goodsList($docking, $page, '', 0, $limit);
            $list = (array) ($data['list'] ?? []);
            if ($list === []) {
                break;
            }
            foreach ($list as $remote) {
                $stat['scanned']++;
                $remoteId = (string) ($remote['id'] ?? '');
                if ($remoteId === '') {
                    continue;
                }
                $seen[$remoteId] = true;
                $rows = GoodsStock::where('docking_id', $dockingId)
                    ->where('type', GoodsStock::TYPE_DOCKING)
                    ->where('docking_goods_id', $remoteId)
                    ->select();
                if ($rows->isEmpty()) {
                    continue;
                }
                $remoteSku = (string) ($remote['goods_sku_id'] ?? $remote['sku_id'] ?? '');
                foreach ($rows as $row) {
                    $localSku = (string) $row->getAttr('docking_goods_sku_id');
                    if ($remoteSku !== '' && $localSku !== '' && $remoteSku !== $localSku) {
                        continue; // 同一渠道商品多规格：按规格精确匹配
                    }
                    $save = [
                        'stock_num'                   => max(0, (int) ($remote['stock_num'] ?? $remote['stock'] ?? 0)),
                        'sync_time'                   => time(),
                        'docking_goods_delete_status' => 0,
                        'docking_type'                => (string) $docking->getAttr('type'),
                        'goods_img'                   => (string) ($remote['goods_img'] ?? $row->getAttr('goods_img')),
                        'goods_name'                  => (string) ($remote['goods_name'] ?? $row->getAttr('goods_name')),
                        'face_value'                  => (string) ($remote['face_value'] ?? $row->getAttr('face_value')),
                    ];
                    $cost = (string) ($remote['goods_price'] ?? $remote['cost_value'] ?? $remote['price'] ?? '');
                    if ($cost !== '' && is_numeric($cost) && bccomp($cost, '0', 5) >= 0) {
                        $save['cost_value'] = $cost;
                    }
                    try {
                        $row->save($save);
                        $stat['updated']++;
                    } catch (\Throwable $e) {
                        $stat['failed']++;
                        self::log('库存行 ' . $row->getAttr('id') . ' 回写失败：' . $e->getMessage());
                    }
                }
            }
            $total = (int) ($data['total'] ?? $data['count'] ?? 0);
            if (count($list) < $limit || ($total > 0 && $page * $limit >= $total)) {
                break;
            }
            $page++;
        }

        // 渠道侧已下架：绑定行库存清零 + 标记删除（不自动删本地商品，保留人工处理）
        $missingRows = GoodsStock::where('docking_id', $dockingId)
            ->where('type', GoodsStock::TYPE_DOCKING)
            ->where('docking_goods_delete_status', 0)
            ->field('id,docking_goods_id')
            ->select();
        foreach ($missingRows as $row) {
            if (isset($seen[(string) $row->getAttr('docking_goods_id')])) {
                continue;
            }
            $row->save(['stock_num' => 0, 'docking_goods_delete_status' => 1, 'sync_time' => time()]);
            $stat['missing']++;
        }
        $docking->save(['sync_goods_status' => 1]);
        return $stat;
    }

    protected static function log(string $message): void
    {
        try {
            Log::warning('[docksync] ' . mb_substr($message, 0, 500, 'UTF-8'));
        } catch (\Throwable $ignore) {
        }
    }
}
