<?php
// +----------------------------------------------------------------------
// | P1-B 现网「验收示例」配置 · 一键创建（幂等）
// 全部使用【验收示例】前缀 + 专属会员/商品/卡库，不改动任何真实商品价格
// 清理请执行 deploy/demo_verify_cleanup.php
// +----------------------------------------------------------------------
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';
(new think\App())->initialize();

use app\common\model\Goods;
use app\common\model\Member;
use app\common\service\UserPriceService;
use think\facade\Db;

const PFX = '【验收示例】';
const DEMO_GOODS_NO = 'DEMO-VERIFY-10';
const DEMO_USERNAME = 'verifydemo';
const DEMO_MEMBER_PWD = 'Verify@123456';
const DEMO_CATE_ID = 9100;

$now = time();

// 幂等：先清理旧的示例配置（不动真实数据）
require __DIR__ . '/demo_verify_cleanup.php';

echo "=== 1. 下单模板 ===\n";
$tplId = (int) Db::name('goods_tpl')->insertGetId([
    'name'    => PFX . '下单模板',
    'inputs'  => json_encode([
        ['label' => '充值账号', 'placeholder' => '请输入充值账号', 'required' => 1],
        ['label' => '充值密码', 'placeholder' => '请输入充值密码', 'required' => 1],
        ['label' => '备注', 'placeholder' => '选填', 'required' => 0],
    ], JSON_UNESCAPED_UNICODE),
    'sort' => 100, 'status' => 1, 'create_time' => $now, 'update_time' => $now, 'delete_time' => 0,
]);
echo "goods_tpl={$tplId}\n";

echo "=== 2. 示例商品 + 卡库 + 库存 ===\n";
$goodsId = (int) Db::name('goods')->insertGetId([
    'goods_no' => DEMO_GOODS_NO, 'goods_name' => PFX . '充值10元（勿真实下单）', 'goods_type' => 1,
    'goods_info' => '<p>这是验收示例商品，用于验证密价/限价/权限/定价模板/下单模板。</p>',
    'goods_notice' => '', 'order_notice' => '', 'category_ids' => '',
    'status' => 1, 'check_status' => 3, 'face_value' => '10.0000', 'real_price' => '10.0000', 'cost_value' => '5.00000',
    'start_count' => 1, 'end_count' => 10, 'multiple' => 0, 'tpl_id' => $tplId, 'ishot' => 0, 'sort' => 0,
    'vip_id' => 0, 'delete_time' => 0, 'create_time' => $now, 'update_time' => $now,
]);
Db::name('card_cate')->insert([
    'id' => DEMO_CATE_ID, 'name' => PFX . '卡库', 'card_type' => 1, 'card_num' => 5,
    'card_no_alias' => '卡号', 'card_password_alias' => '卡密',
    'cost' => 5, 'status' => 1, 'create_time' => $now, 'update_time' => $now, 'delete_time' => 0,
]);
Db::name('goods_stock')->insert([
    'goods_id' => $goodsId, 'type' => 1, 'card_cate_id' => DEMO_CATE_ID, 'status' => 1,
    'cost_value' => '5.00000', 'stock_num' => 5, 'create_time' => $now,
]);
echo "goods_id={$goodsId} card_cate={DEMO_CATE_ID}\n";

echo "=== 3. 示例会员 ===\n";
$memberData = [
    'username' => DEMO_USERNAME, 'nickname' => PFX . '会员',
    'password' => password_hash(DEMO_MEMBER_PWD, PASSWORD_BCRYPT),
    'salt' => substr(md5(DEMO_USERNAME . $now), 0, 12),
    'level_id' => 1, 'balance' => '100.0000', 'status' => 1, 'delete_time' => 0,
    'create_time' => $now, 'update_time' => $now,
];
// 补齐 member 表所有 NOT NULL 且无默认值的列（避免 1364）
$notNull = Db::query("SELECT COLUMN_NAME, DATA_TYPE FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'mks_member'
      AND IS_NULLABLE = 'NO' AND COLUMN_DEFAULT IS NULL AND EXTRA NOT LIKE '%auto_increment%'");
foreach ($notNull as $c) {
    $name = (string) $c['COLUMN_NAME'];
    if (!array_key_exists($name, $memberData)) {
        $memberData[$name] = in_array((string) $c['DATA_TYPE'], ['int', 'bigint', 'smallint', 'tinyint', 'decimal', 'float', 'double'], true) ? 0 : '';
    }
}
$memberId = (int) Db::name('member')->insertGetId($memberData);
echo "member_id={$memberId} username=" . DEMO_USERNAME . " password=" . DEMO_MEMBER_PWD . "\n";

echo "=== 4. 密价 / 限价 / 权限 / 定价模板 ===\n";
$mk = function (int $type, string $name, int $mode, string $value) use ($now): int {
    $id = (int) Db::name('user_price_tpl')->insertGetId([
        'type' => $type, 'name' => PFX . $name, 'mode' => $mode, 'value' => $value,
        'status' => 1, 'create_time' => $now, 'update_time' => $now, 'delete_time' => 0,
    ]);
    Db::name('user_price_tpl_user')->insert(['tpl_id' => $id, 'uid' => $GLOBALS['demoMemberId']]);
    Db::name('user_price_tpl_goods')->insert(['tpl_id' => $id, 'goods_id' => $GLOBALS['demoGoodsId']]);
    return $id;
};
$GLOBALS['demoMemberId'] = $memberId;
$GLOBALS['demoGoodsId']  = $goodsId;
$closeId = $mk(UserPriceService::TPL_CLOSE, '密价 8 元', 3, '8.00');
$limitId = $mk(UserPriceService::TPL_LIMIT, '限价 5 元', 1, '5.00');
$rightId = $mk(UserPriceService::TPL_RIGHT, '权限（仅示例商品）', 1, '1.00');
$readyId = (int) Db::name('goods_ready_price')->insertGetId([
    'vip_id' => 0, 'goods_id' => $goodsId, 'type' => 1, 'cha_price' => '30.0000',
    'start_price' => '0', 'end_price' => '0', 'status' => 1, 'price_arr' => '',
    'is_default' => 0, 'name' => PFX . '定价模板 +30%', 'sort' => 90, 'create_time' => $now, 'update_time' => $now,
]);
echo "close={$closeId} limit={$limitId} right={$rightId} ready={$readyId}\n";

echo "=== 5. 自检（不改任何真实商品） ===\n";
$g = Goods::findOrEmpty($goodsId);
$m = Member::findOrEmpty($memberId);
$q1 = UserPriceService::quote($g, $m);
$q2 = UserPriceService::quote($g, null);
$g21 = Goods::findOrEmpty(21);
$q3 = UserPriceService::quote($g21, $m);
echo "示例商品：示例会员价={$q1['price']}（{$q1['source']}） 游客价={$q2['price']}（{$q2['source']}）\n";
echo "示例会员看真实商品21：可见=" . (UserPriceService::isGoodsVisible(21, $memberId) ? '1' : '0') . "（权限白名单生效）\n";
echo "真实商品21 示例会员取价={$q3['price']}（{$q3['source']}）\n";
if ($q1['price'] !== '8.00' && $q1['price'] !== '8.0000') { echo "!! 密价未生效\n"; }
if ($q2['price'] !== '13.0000') { echo "!! 定价模板未生效（期望 13.0000）\n"; }
echo "完成。清理：docker exec mkshop-php php /var/www/mkshop/deploy/demo_verify_cleanup.php\n";
