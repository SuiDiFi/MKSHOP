<?php
// +----------------------------------------------------------------------
// | P1-B 现网「验收示例」配置 · 一键清理（幂等，可重复执行）
// 直接执行：docker exec mkshop-php php /var/www/mkshop/deploy/demo_verify_cleanup.php
// 只删除【验收示例】前缀/专属账号商品，不触碰真实数据
// +----------------------------------------------------------------------
declare(strict_types=1);

if (!function_exists('demo_verify_cleanup')) {
    /**
     * @return array 清理统计
     */
    function demo_verify_cleanup(): array
    {
        $pfx   = '【验收示例】';
        $stat  = [];
        $goodsIds = \think\facade\Db::name('goods')->where('goods_no', 'like', 'DEMO-VERIFY%')->column('id');
        $memberIds = \think\facade\Db::name('member')->where('username', 'verifydemo')->column('id');
        $tplIds = \think\facade\Db::name('user_price_tpl')->where('name', 'like', $pfx . '%')->column('id');

        if ($tplIds !== []) {
            \think\facade\Db::name('user_price_tpl_user')->whereIn('tpl_id', $tplIds)->delete();
            \think\facade\Db::name('user_price_tpl_goods')->whereIn('tpl_id', $tplIds)->delete();
            \think\facade\Db::name('user_price_tpl')->whereIn('id', $tplIds)->delete();
        }
        $stat['price_tpl'] = count($tplIds);

        $readyIds = \think\facade\Db::name('goods_ready_price')->where('name', 'like', $pfx . '%')->column('id');
        if ($readyIds === [] && $goodsIds !== []) {
            $readyIds = \think\facade\Db::name('goods_ready_price')->whereIn('goods_id', $goodsIds)->column('id');
        }
        if ($readyIds !== []) {
            \think\facade\Db::name('goods_ready_price')->whereIn('id', $readyIds)->delete();
        }
        $stat['ready_tpl'] = count($readyIds);

        $gtIds = \think\facade\Db::name('goods_tpl')->where('name', 'like', $pfx . '%')->column('id');
        if ($gtIds !== []) {
            \think\facade\Db::name('goods_tpl')->whereIn('id', $gtIds)->delete();
        }
        $stat['goods_tpl'] = count($gtIds);

        if ($goodsIds !== []) {
            $orderIds = \think\facade\Db::name('order')->whereIn('goods_id', $goodsIds)->column('id');
            \think\facade\Db::name('goods_stock')->whereIn('goods_id', $goodsIds)->delete();
            \think\facade\Db::name('goods')->whereIn('id', $goodsIds)->delete();
        } else {
            $orderIds = [];
        }
        if ($memberIds !== []) {
            $more = \think\facade\Db::name('order')->whereIn('uid', $memberIds)->column('id');
            $orderIds = array_values(array_unique(array_merge($orderIds, $more)));
            \think\facade\Db::name('member')->whereIn('id', $memberIds)->delete();
        }
        $stat['member'] = count($memberIds);

        if ($orderIds !== []) {
            \think\facade\Db::name('recharge_log')->whereIn('order_id', $orderIds)->delete();
            \think\facade\Db::name('order_log')->whereIn('order_id', $orderIds)->delete();
            \think\facade\Db::name('order_dolog')->whereIn('order_id', $orderIds)->delete();
            \think\facade\Db::name('order_refund')->whereIn('order_id', $orderIds)->delete();
            \think\facade\Db::name('order')->whereIn('id', $orderIds)->delete();
        }
        $stat['order'] = count($orderIds);

        $cateIds = \think\facade\Db::name('card_cate')->where('name', 'like', $pfx . '%')->column('id');
        if ($cateIds !== []) {
            \think\facade\Db::name('card')->whereIn('card_cate_id', $cateIds)->delete();
            \think\facade\Db::name('card_cate')->whereIn('id', $cateIds)->delete();
        }
        $stat['card_cate'] = count($cateIds);

        $stat['goods'] = count($goodsIds);
        return $stat;
    }
}

if (realpath((string) ($_SERVER['SCRIPT_FILENAME'] ?? '')) === realpath(__FILE__)) {
    require __DIR__ . '/../vendor/autoload.php';
    (new think\App())->initialize();
    $stat = demo_verify_cleanup();
    echo "已清理：" . json_encode($stat, JSON_UNESCAPED_UNICODE) . "\n";
    $left = \think\facade\Db::name('goods')->where('goods_no', 'like', 'DEMO-VERIFY%')->count()
        + \think\facade\Db::name('user_price_tpl')->where('name', 'like', '【验收示例】%')->count()
        + \think\facade\Db::name('goods_ready_price')->where('name', 'like', '【验收示例】%')->count()
        + \think\facade\Db::name('goods_tpl')->where('name', 'like', '【验收示例】%')->count();
    echo "残留检查：" . ($left === 0 ? '无残留' : $left . ' 条未清理') . "\n";
}
