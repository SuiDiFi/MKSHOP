<?php
// +----------------------------------------------------------------------
// | 取价/限价/权限模板服务（M7-P2 + P2-G 生效逻辑 · 唯一入口）
// |
// | 数据源：
// |   用户模板 mks_user_price_tpl（type:1=密价 2=限价 3=权限；status=1 启用）
// |   + 绑定表 mks_user_price_tpl_user / mks_user_price_tpl_goods
// |   定价模板 mks_goods_ready_price（type:1=比例加价% 2=固定加价 3=固定售价；price_arr=等级价格档）
// |
// | 取价优先级（主站 quote() 唯一入口；分站整体优先，走 VipPriceService，不进入 quote）：
// |   分站价 > 用户密价 > 定价模板 > 会员等级折扣 > 平台基准价 real_price
// |   1. 分站：VipPriceService 三级定价（商品级覆盖 > 分站比例 > 基准价）；
// |      分站内不叠加密价/定价模板/会员折扣
// |   2. 密价：模板必须同时绑定用户与商品（任一端为空 = 不生效），防误配全站生效；
// |      基于平台基准价 real_price 计算；多模板命中取最低价（同价取 id 小）；
// |      命中后不再叠加定价模板与会员折扣（最具体者胜，避免双重让价）
// |   3. 定价模板：仅"商品显式绑定"生效（goods_ready_price.goods_id=商品 id、status=1、vip_id=0）；
// |      goods_id=0 的全局预置行（如 id=1「默认预置定价」）不参与取价，避免误配全站改价；
// |      同商品多条绑定按 sort desc, id desc 取第一条（确定性）；
// |      price_arr 含该会员等级价 {"等级id":价格} → 直接取等级价（mode=9），不再叠加折扣；
// |      否则按 type 对基准价计算；命中后不再叠加会员折扣（模板价即标准售价）；
// |      游客同样使用绑定模板价（商品标准售价，与下单/列表/详情同源）
// |   4. 会员折扣：member_level.pay_rate（0=无折扣，0~100=折后百分比）作用于基准价
// |   5. 多个限价模板命中：取最高下限（最严格者），下单最终单价 < 下限 → 拒绝
// |   6. 权限=白名单：用户命中任一启用权限模板 → 可见集合 = 各模板绑定商品并集，
// |      列表/详情/下单三处同源过滤；白名单外商品一律按"商品不存在或已下架"处理
// |   7. 校验唯一入口：OrderService::createOrder → assertOrderAllowed()（服务层）；
// |      前台控制器只做可见性过滤与展示（quote/quoteMap），不各自写价格计算与校验
// +----------------------------------------------------------------------
declare (strict_types = 1);

namespace app\common\service;

use app\common\model\Goods;
use app\common\model\Member;
use app\common\model\MemberLevel;
use think\facade\Db;

class UserPriceService
{
    public const TPL_CLOSE = 1; // 会员密价
    public const TPL_LIMIT = 2; // 销售限价
    public const TPL_RIGHT = 3; // 商品权限

    /** 定价模板：price_arr 命中会员等级价时的伪 mode */
    public const READY_MODE_LEVEL = 9;

    /** 取价来源（写入订单 price_set_content 与前台展示） */
    public const SRC_BASE     = 'base';       // 平台基准价
    public const SRC_DISCOUNT = 'discount';   // 会员等级折扣
    public const SRC_CLOSE    = 'close_tpl';  // 用户密价模板
    public const SRC_READY    = 'ready_tpl';  // 定价模板

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
     * 定价模板批量取价（仅商品显式绑定生效；goods_id=0 全局预置行不参与取价）
     * @param array $goodsList 每行须含 id 与 real_price
     * @return array<int, array{price:string,tpl_id:int,mode:int,value:string}> goods_id => 命中
     */
    public static function readyPriceMap(array $goodsList, int $levelId = 0): array
    {
        if ($goodsList === []) {
            return [];
        }
        $goodsIds = array_map('intval', array_column($goodsList, 'id'));
        $rows = Db::name('goods_ready_price')
            ->where('goods_id', 'in', $goodsIds)
            ->where('vip_id', 0)
            ->where('status', 1)
            ->field('id,goods_id,type,cha_price,price_arr')
            ->order('sort desc, id desc')
            ->select()->toArray();
        if ($rows === []) {
            return [];
        }
        $bases = [];
        foreach ($goodsList as $g) {
            $bases[(int) $g['id']] = (string) ($g['real_price'] ?? '0');
        }
        $map = [];
        foreach ($rows as $r) {
            $gid = (int) $r['goods_id'];
            if (isset($map[$gid])) {
                continue; // 已按 sort desc, id desc 取到该商品最高优先模板
            }
            $base  = $bases[$gid] ?? '0';
            $type  = (int) $r['type'];
            $value = (string) $r['cha_price'];
            $price = self::calcReady($base, $type, $value);
            $mode  = $type;
            $arr   = json_decode((string) $r['price_arr'], true);
            if ($levelId > 0 && is_array($arr) && isset($arr[(string) $levelId]) && is_numeric($arr[(string) $levelId])) {
                $levelPrice = (string) $arr[(string) $levelId];
                if (bccomp($levelPrice, '0', 4) > 0) {
                    $price = bcadd($levelPrice, '0', 4);
                    $mode  = self::READY_MODE_LEVEL;
                    $value = $levelPrice;
                }
            }
            $map[$gid] = ['price' => $price, 'tpl_id' => (int) $r['id'], 'mode' => $mode, 'value' => $value];
        }
        return $map;
    }

    /**
     * 定价模板加价计算（type 1=比例加价% 2=固定加价 3=固定售价）
     */
    protected static function calcReady(string $base, int $type, string $value): string
    {
        $price = match ($type) {
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
     * 定价模板单品取价（未绑定返回 null）
     */
    public static function readyPrice(Goods $goods, int $levelId = 0): ?array
    {
        $gid = (int) $goods->getAttr('id');
        $map = self::readyPriceMap([['id' => $gid, 'real_price' => (string) $goods->getAttr('real_price')]], $levelId);
        return $map[$gid] ?? null;
    }

    /**
     * 主站取价唯一入口（分站由调用方先行走 VipPriceService，不进入本方法）
     * 优先级：密价 > 定价模板 > 会员等级折扣 > 基准价
     * @return array{price:string, source:string, tpl_id:int, mode:int, value:string, base:string, level_id:int}
     */
    public static function quote(Goods $goods, ?Member $member): array
    {
        $base    = (string) $goods->getAttr('real_price');
        $uid     = $member !== null ? (int) $member->getAttr('id') : 0;
        $levelId = $member !== null ? (int) $member->getAttr('level_id') : 0;
        $out = [
            'price'    => $base,
            'source'   => self::SRC_BASE,
            'tpl_id'   => 0,
            'mode'     => 0,
            'value'    => '0',
            'base'     => $base,
            'level_id' => $levelId,
        ];
        // 1) 用户密价（用户+商品双绑定，最具体）
        if ($uid > 0) {
            $close = self::closePrice($goods, $uid);
            if ($close !== null) {
                return array_merge($out, [
                    'price'  => $close['price'],
                    'source' => self::SRC_CLOSE,
                    'tpl_id' => $close['tpl_id'],
                    'mode'   => $close['mode'],
                    'value'  => $close['value'],
                ]);
            }
        }
        // 2) 定价模板（商品标准售价；含会员等级价格档）
        $ready = self::readyPrice($goods, $levelId);
        if ($ready !== null) {
            return array_merge($out, [
                'price'  => $ready['price'],
                'source' => self::SRC_READY,
                'tpl_id' => $ready['tpl_id'],
                'mode'   => $ready['mode'],
                'value'  => $ready['value'],
            ]);
        }
        // 3) 会员等级折扣（不与密价/定价模板叠加）
        if ($uid > 0 && $levelId > 0) {
            $payRate = (string) MemberLevel::where('id', $levelId)->value('pay_rate');
            if (bccomp($payRate, '0', 2) > 0 && bccomp($payRate, '100', 2) < 0) {
                return array_merge($out, [
                    'price'  => bcdiv(bcmul($base, $payRate, 6), '100', 4),
                    'source' => self::SRC_DISCOUNT,
                    'value'  => $payRate,
                ]);
            }
        }
        return $out;
    }

    /**
     * 批量取价（列表展示用，与 quote 同源同优先级）
     * @param array $goodsList 每行须含 id 与 real_price
     * @return array<int, array{price:string, source:string, tpl_id:int}>
     */
    public static function quoteMap(array $goodsList, ?Member $member): array
    {
        if ($goodsList === []) {
            return [];
        }
        $uid     = $member !== null ? (int) $member->getAttr('id') : 0;
        $levelId = $member !== null ? (int) $member->getAttr('level_id') : 0;
        $closeMap = $uid > 0 ? self::closePriceMap($goodsList, $uid) : [];
        $readyMap = self::readyPriceMap($goodsList, $levelId);
        $payRate  = '';
        if ($uid > 0 && $levelId > 0) {
            $payRate = (string) MemberLevel::where('id', $levelId)->value('pay_rate');
        }
        $out = [];
        foreach ($goodsList as $g) {
            $gid  = (int) $g['id'];
            $base = (string) ($g['real_price'] ?? '0');
            if (isset($closeMap[$gid])) {
                $out[$gid] = ['price' => $closeMap[$gid]['price'], 'source' => self::SRC_CLOSE, 'tpl_id' => $closeMap[$gid]['tpl_id']];
            } elseif (isset($readyMap[$gid])) {
                $out[$gid] = ['price' => $readyMap[$gid]['price'], 'source' => self::SRC_READY, 'tpl_id' => $readyMap[$gid]['tpl_id']];
            } elseif ($payRate !== '' && bccomp($payRate, '0', 2) > 0 && bccomp($payRate, '100', 2) < 0) {
                $out[$gid] = ['price' => bcdiv(bcmul($base, $payRate, 6), '100', 4), 'source' => self::SRC_DISCOUNT, 'tpl_id' => 0];
            } else {
                $out[$gid] = ['price' => $base, 'source' => self::SRC_BASE, 'tpl_id' => 0];
            }
        }
        return $out;
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
