<?php
// +----------------------------------------------------------------------
// | 用户密价/限价/权限模板服务（M7-P2 生效逻辑 · 唯一入口）
// |
// | 模板表：mks_user_price_tpl（type:1=密价 2=限价 3=权限；status=1 启用）
// | 绑定表：mks_user_price_tpl_user / mks_user_price_tpl_goods
// |
// | 生效规则（红线，改动须同步本注释与汇报文档）：
// |   1. 模板必须同时绑定用户与商品（任一端为空 = 不生效），防误配全站生效
// |   2. 密价仅在主站上下文生效（分站走 VipPriceService 三级定价，互不叠加）
// |   3. 取价优先级：分站价 > 密价模板 > 会员等级折扣 > 平台基准价
// |      命中密价时不再叠加会员等级折扣（二者互斥，避免双重让价）
// |   4. 多个密价模板同时命中：计算后价格最低者胜（对用户最优），同价取模板 id 最小
// |   5. 多个限价模板同时命中：取最高下限（最严格者），下单最终单价 < 下限 → 拒绝
// |   6. 权限=白名单：用户命中任一启用权限模板 → 可见集合 = 各模板绑定商品并集，
// |      列表/详情/下单三处同源过滤；白名单外商品一律按"商品不存在或已下架"处理
// |   7. 校验唯一入口：OrderService::createOrder → assertOrderAllowed()（服务层），
// |      前台控制器只做可见性过滤与展示，不各自写校验
// +----------------------------------------------------------------------
declare (strict_types = 1);

namespace app\common\service;

use app\common\model\Goods;
use app\common\model\Member;
use think\facade\Db;

class UserPriceService
{
    public const TPL_CLOSE = 1; // 会员密价
    public const TPL_LIMIT = 2; // 销售限价
    public const TPL_RIGHT = 3; // 商品权限

    /**
     * 用户绑定的启用模板 id 集（type 过滤；软删/停用排除）
     */
    protected static function boundTplIds(int $type, int $uid): array
    {
        if ($uid <= 0) {
            return [];
        }
        $tplIds = Db::name('user_price_tpl_user')->where('uid', $uid)->column('tpl_id');
        if ($tplIds === []) {
            return [];
        }
        return Db::name('user_price_tpl')
            ->where('id', 'in', $tplIds)
            ->where('type', $type)
            ->where('status', 1)
            ->where('delete_time', 0)
            ->column('id');
    }

    /**
     * 密价幅度计算（mode 语义对齐分站 VipGoodsPrice：1=比例加价% 2=固定加价 3=固定售价）
     */
    protected static function calcClose(string $base, int $mode, string $value): string
    {
        $price = match ($mode) {
            1 => bcadd($base, bcmul($base, bcdiv($value, '100', 6), 4), 4),
            2 => bcadd($base, $value, 4),
            3 => $value,
            default => $base,
        };
        if (bccomp($price, '0', 4) < 0) {
            $price = '0.0000';
        }
        return $price;
    }

    /**
     * 会员密价取价（唯一入口；仅主站上下文，分站调用方不走此方法）
     * @return array{price:string, tpl_id:int, mode:int, value:string, base:string} | null 未命中
     */
    public static function closePrice(Goods $goods, int $uid): ?array
    {
        $base = (string) $goods->getAttr('real_price');
        $tplIds = self::boundTplIds(self::TPL_CLOSE, $uid);
        if ($tplIds === []) {
            return null;
        }
        $rows = Db::name('user_price_tpl_goods')
            ->where('tpl_id', 'in', $tplIds)
            ->where('goods_id', (int) $goods->getAttr('id'))
            ->field('tpl_id')
            ->select()->toArray();
        if ($rows === []) {
            return null;
        }
        $meta = Db::name('user_price_tpl')
            ->where('id', 'in', array_column($rows, 'tpl_id'))
            ->column('mode,value', 'id');
        $best = null;
        foreach ($rows as $r) {
            $tplId = (int) $r['tpl_id'];
            $mode  = (int) ($meta[$tplId]['mode'] ?? 0);
            $value = (string) ($meta[$tplId]['value'] ?? '0');
            $price = self::calcClose($base, $mode, $value);
            if (
                $best === null
                || bccomp($price, $best['price'], 4) < 0
                || (bccomp($price, $best['price'], 4) === 0 && $tplId < $best['tpl_id'])
            ) {
                $best = ['price' => $price, 'tpl_id' => $tplId, 'mode' => $mode, 'value' => $value, 'base' => $base];
            }
        }
        return $best;
    }

    /**
     * 密价批量取价（列表展示用）
     * @param array $goodsList 每行须含 id 与 real_price
     * @return array<int, array{price:string,tpl_id:int,mode:int,value:string}> goods_id => 命中
     */
    public static function closePriceMap(array $goodsList, int $uid): array
    {
        if ($uid <= 0 || $goodsList === []) {
            return [];
        }
        $tplIds = self::boundTplIds(self::TPL_CLOSE, $uid);
        if ($tplIds === []) {
            return [];
        }
        $goodsIds = array_map('intval', array_column($goodsList, 'id'));
        $rows = Db::name('user_price_tpl_goods')
            ->where('tpl_id', 'in', $tplIds)
            ->where('goods_id', 'in', $goodsIds)
            ->field('tpl_id,goods_id')
            ->select()->toArray();
        if ($rows === []) {
            return [];
        }
        $meta = Db::name('user_price_tpl')
            ->where('id', 'in', array_column($rows, 'tpl_id'))
            ->column('mode,value', 'id');
        $bases = [];
        foreach ($goodsList as $g) {
            $bases[(int) $g['id']] = (string) ($g['real_price'] ?? '0');
        }
        $map = [];
        foreach ($rows as $r) {
            $gid   = (int) $r['goods_id'];
            $tplId = (int) $r['tpl_id'];
            $mode  = (int) ($meta[$tplId]['mode'] ?? 0);
            $value = (string) ($meta[$tplId]['value'] ?? '0');
            $price = self::calcClose($bases[$gid] ?? '0', $mode, $value);
            if (
                !isset($map[$gid])
                || bccomp($price, $map[$gid]['price'], 4) < 0
                || (bccomp($price, $map[$gid]['price'], 4) === 0 && $tplId < $map[$gid]['tpl_id'])
            ) {
                $map[$gid] = ['price' => $price, 'tpl_id' => $tplId, 'mode' => $mode, 'value' => $value];
            }
        }
        return $map;
    }

    /**
     * 限价下限（命中返回最高下限；未命中返回 null）
     */
    public static function limitPriceOf(int $uid, int $goodsId): ?string
    {
        $tplIds = self::boundTplIds(self::TPL_LIMIT, $uid);
        if ($tplIds === []) {
            return null;
        }
        $hit = Db::name('user_price_tpl_goods')
            ->where('tpl_id', 'in', $tplIds)
            ->where('goods_id', $goodsId)
            ->column('tpl_id');
        if ($hit === []) {
            return null;
        }
        $values = Db::name('user_price_tpl')->where('id', 'in', $hit)->column('value');
        if ($values === []) {
            return null;
        }
        $max = null;
        foreach ($values as $v) {
            if ($max === null || bccomp((string) $v, $max, 2) > 0) {
                $max = (string) $v;
            }
        }
        return $max;
    }

    /**
     * 商品权限白名单集合（用户受约束时返回商品 id 集；无约束返回 null；约束但无授权返回 []）
     */
    public static function rightGoodsIds(int $uid): ?array
    {
        $tplIds = self::boundTplIds(self::TPL_RIGHT, $uid);
        if ($tplIds === []) {
            return null;
        }
        $ids = Db::name('user_price_tpl_goods')
            ->where('tpl_id', 'in', $tplIds)
            ->column('goods_id');
        return array_values(array_unique(array_map('intval', $ids)));
    }

    /**
     * 是否可见（详情/下单判定用）
     */
    public static function isGoodsVisible(int $goodsId, int $uid): bool
    {
        if ($uid <= 0) {
            return true;
        }
        $ids = self::rightGoodsIds($uid);
        if ($ids === null) {
            return true;
        }
        return in_array($goodsId, $ids, true);
    }

    /**
     * 列表 SQL 级可见性过滤（白名单交集；不受约束的用户原样返回）
     */
    public static function applyRightScope($query, ?int $uid)
    {
        if ($uid === null || $uid <= 0) {
            return $query;
        }
        $ids = self::rightGoodsIds($uid);
        if ($ids === null) {
            return $query;
        }
        if ($ids === []) {
            return $query->whereRaw('1=0');
        }
        return $query->whereIn('id', $ids);
    }

    /**
     * 下单统一校验（服务层唯一入口，OrderService::createOrder 调用）：
     *   1. 商品权限：白名单外 → 按不可见处理（不泄露商品存在性）
     *   2. 限价：最终单价低于命中模板下限 → 拒绝并给出可读提示
     */
    public static function assertOrderAllowed(Goods $goods, ?Member $member, string $price): void
    {
        if ($member === null) {
            return;
        }
        $uid = (int) $member->getAttr('id');
        if ($uid <= 0) {
            return;
        }
        $goodsId = (int) $goods->getAttr('id');
        if (!self::isGoodsVisible($goodsId, $uid)) {
            throw_fail('商品不存在或已下架', 1);
        }
        $limit = self::limitPriceOf($uid, $goodsId);
        if ($limit !== null && bccomp($price, $limit, 4) < 0) {
            throw_fail("该商品最低售价为 {$limit} 元，当前价格 {$price} 元低于限制，无法下单", 1);
        }
    }
}
