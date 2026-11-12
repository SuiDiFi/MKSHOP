<?php
// +----------------------------------------------------------------------
// | 商户控制台（M6-B）：概览 / 订单 / 商品 / 货款钱包 / 提现 / 入驻申请
// | 鉴权：MemberToken + MerchantAuth（member.has_merchant + merchant.status）
// | 订单归属：order.supplier_id（下单时已固化），老订单回退 goods.uid
// +----------------------------------------------------------------------
declare (strict_types = 1);

namespace app\controller\merchant;

use app\common\model\Goods;
use app\common\model\Member;
use app\common\model\MemberCollect;
use app\common\model\MemberCollectWay;
use app\common\model\MemberSupply;
use app\common\model\Merchant;
use app\common\model\MerchantApply;
use app\common\model\Order;
use app\common\model\OrderSupply;
use app\common\service\MerchantService;
use app\common\service\WithdrawService;
use mks\BaseController;
use mks\util\SysConfig;
use think\facade\Db;

class ConsoleController extends BaseController
{
    protected function memberId(): int
    {
        return (int) ($this->request->memberId ?? 0);
    }

    protected function merchant(): Merchant
    {
        return $this->request->merchant;
    }

    /**
     * 入驻申请信息（未开通商户也可访问）
     */
    public function apply_info()
    {
        $uid    = $this->memberId();
        $member = Member::findOrEmpty($uid);
        if ($member->isEmpty()) {
            return $this->fail('请先登录');
        }
        $merchant = (int) $member->getAttr('has_merchant') === 1
            ? Merchant::where('uid', $uid)->where('delete_time', 0)->findOrEmpty()
            : null;
        $apply = MerchantApply::where('uid', $uid)->order('id desc')->findOrEmpty();
        return $this->ok([
            'enabled'      => (int) SysConfig::get('merchant_apply_enable', 1) === 1,
            'has_merchant' => (int) $member->getAttr('has_merchant'),
            'merchant'     => $merchant && !$merchant->isEmpty() ? $merchant->toArray() : null,
            'apply'        => $apply->isEmpty() ? null : $apply->toArray(),
            'apply_status_text' => $apply->isEmpty() ? '' : MerchantApply::statusText((int) $apply->getAttr('status')),
        ]);
    }

    /**
     * 提交入驻申请
     * @param {merchant_name, contact, mobile, intro?, license_img?}
     */
    public function apply_submit()
    {
        if ((int) SysConfig::get('merchant_apply_enable', 1) !== 1) {
            return $this->fail('商户入驻暂未开放');
        }
        $apply = MerchantService::apply($this->memberId(), [
            'merchant_name' => $this->request->param('merchant_name', ''),
            'contact'       => $this->request->param('contact', ''),
            'mobile'        => $this->request->param('mobile', ''),
            'intro'         => $this->request->param('intro', ''),
            'license_img'   => $this->request->param('license_img', ''),
        ]);
        return $this->ok([
            'id'     => (int) $apply->getAttr('id'),
            'status' => MerchantApply::statusText((int) $apply->getAttr('status')),
        ], '提交成功，等待平台审核');
    }

    /**
     * 概览
     */
    public function overview()
    {
        $merchant = $this->merchant();
        $uid      = (int) $merchant->getAttr('uid');
        $member   = Member::findOrEmpty($uid);
        $today    = strtotime('today');
        $orderQ   = Order::where('supplier_id', $uid)->where('delete_time', 0);
        $supplyQ  = OrderSupply::where('supply_id', $uid);

        $weekStart = strtotime('-6 days', $today);
        $daily = Db::name('order')
            ->where('supplier_id', $uid)
            ->where('delete_time', 0)
            ->where('create_time', '>=', $weekStart)
            ->field("FROM_UNIXTIME(create_time,'%Y-%m-%d') AS day, COUNT(*) AS orders, SUM(total_price) AS sales")
            ->group('day')
            ->order('day asc')
            ->select()->toArray();

        return $this->ok([
            'merchant' => [
                'id'     => (int) $merchant->getAttr('id'),
                'name'   => (string) $merchant->getAttr('name'),
                'logo'   => (string) $merchant->getAttr('logo'),
                'status' => (int) $merchant->getAttr('status'),
                'settlement_type' => (int) $merchant->getAttr('settlement_type'),
            ],
            'orders' => [
                'total'     => (clone $orderQ)->count(),
                'today'     => (clone $orderQ)->where('create_time', '>=', $today)->count(),
                'sales'     => (string) (clone $orderQ)->sum('total_price'),
                'today_sales' => (string) (clone $orderQ)->where('create_time', '>=', $today)->sum('total_price'),
                'pending'   => (clone $orderQ)->whereIn('status', [1, 2])->count(),
            ],
            'goods' => [
                'total'  => Goods::where('uid', $uid)->where('delete_time', 0)->count(),
                'online' => Goods::where('uid', $uid)->where('delete_time', 0)->where('status', 1)->count(),
            ],
            'supply' => [
                'pending'  => (string) (clone $supplyQ)->where('sure_status', OrderSupply::SURE_WAIT)->sum('supply_price'),
                'settled'  => (string) (clone $supplyQ)->where('sure_status', OrderSupply::SURE_SETTLED)->sum('supply_price'),
                'refunded' => (string) $supplyQ->sum('back_supply_price'),
            ],
            'wallet' => [
                'supply_money'       => $member->isEmpty() ? '0.0000' : (string) $member->getAttr('supply_money'),
                'freez_supply_money' => $member->isEmpty() ? '0.0000' : (string) $member->getAttr('freez_supply_money'),
            ],
            'daily' => $daily,
        ]);
    }

    /**
     * 订单列表（仅本商户供货订单）
     */
    public function order_index()
    {
        $uid    = (int) $this->merchant()->getAttr('uid');
        $page   = max(1, (int) $this->request->param('page', 1));
        $limit  = min(100, max(1, (int) $this->request->param('limit', 20)));
        $query  = Order::where('supplier_id', $uid)->where('delete_time', 0);
        $status = (string) $this->request->param('status', '');
        if ($status !== '') {
            $query = $query->where('status', (int) $status);
        }
        $keyword = trim((string) $this->request->param('keyword', ''));
        if ($keyword !== '') {
            $query = $query->where(function ($q) use ($keyword) {
                $q->whereLike('ordersn', '%' . $keyword . '%')
                    ->whereOr('goods_name', 'like', '%' . $keyword . '%');
            });
        }
        $total = (clone $query)->count();
        $list  = $query->order('id desc')->page($page, $limit)
            ->field('id,ordersn,uid,nickname,goods_id,goods_name,goods_img,quantity,total_price,status,pay_time,create_time')
            ->select()->toArray();
        return $this->ok(['list' => $list, 'count' => $total]);
    }

    /**
     * 订单详情（限本商户供货订单）
     */
    public function order_read(int $id)
    {
        $uid   = (int) $this->merchant()->getAttr('uid');
        $order = Order::where('id', $id)->where('supplier_id', $uid)->where('delete_time', 0)->findOrEmpty();
        if ($order->isEmpty()) {
            return $this->fail('订单不存在');
        }
        return $this->ok($order->toArray());
    }

    /**
     * 我的商品（只读，不含渠道成本/内部利润字段）
     */
    public function goods_index()
    {
        $uid   = (int) $this->merchant()->getAttr('uid');
        $page  = max(1, (int) $this->request->param('page', 1));
        $limit = min(100, max(1, (int) $this->request->param('limit', 20)));
        $query = Goods::where('uid', $uid)->where('delete_time', 0);
        $keyword = trim((string) $this->request->param('keyword', ''));
        if ($keyword !== '') {
            $query = $query->whereLike('goods_name', '%' . $keyword . '%');
        }
        $total = (clone $query)->count();
        $list  = $query->order('id desc')->page($page, $limit)
            ->field('id,goods_name,goods_img,real_price,status,sales_num,create_time')
            ->select()->toArray();
        return $this->ok(['list' => $list, 'count' => $total]);
    }

    /**
     * 货款钱包
     */
    public function wallet()
    {
        $uid    = (int) $this->merchant()->getAttr('uid');
        $member = Member::findOrEmpty($uid);
        if ($member->isEmpty()) {
            return $this->fail('会员不存在');
        }
        return $this->ok([
            'supply_money'       => (string) $member->getAttr('supply_money'),
            'freez_supply_money' => (string) $member->getAttr('freez_supply_money'),
        ]);
    }

    /**
     * 货款流水
     */
    public function supply_flow()
    {
        $uid   = (int) $this->merchant()->getAttr('uid');
        $page  = max(1, (int) $this->request->param('page', 1));
        $limit = min(100, max(1, (int) $this->request->param('limit', 20)));
        $total = MemberSupply::where('uid', $uid)->count();
        $list  = MemberSupply::where('uid', $uid)->order('id desc')->page($page, $limit)
            ->field('id,money,trade_type,change_type,before,after,extend_id,mark,order_id,create_time')
            ->select()->toArray();
        $text = self::tradeText();
        foreach ($list as &$row) {
            $row['trade_type_text'] = $text[(int) $row['trade_type']] ?? '其他';
        }
        unset($row);
        return $this->ok(['list' => $list, 'count' => $total]);
    }

    /**
     * 提现配置（货款账户）
     */
    public function withdraw_config()
    {
        $uid    = (int) $this->merchant()->getAttr('uid');
        $member = Member::findOrEmpty($uid);
        if ($member->isEmpty()) {
            return $this->fail('会员不存在');
        }
        $tiers = SysConfig::get('member_collect_config', []);
        if (is_string($tiers)) {
            $tiers = json_decode($tiers, true) ?: [];
        }
        return $this->ok([
            'supply_money'       => (string) $member->getAttr('supply_money'),
            'freez_supply_money' => (string) $member->getAttr('freez_supply_money'),
            'fee_tiers'          => $tiers,
            'min_money'          => WithdrawService::minMoney(),
            'tip'                => (string) SysConfig::get('member_collect_tip', ''),
            'ways'               => $this->waysOf($uid),
        ]);
    }

    /**
     * 申请货款提现
     * @param {money, way_id?, account?, true_name?, way_name?, mark?}
     */
    public function withdraw_apply()
    {
        $uid   = (int) $this->merchant()->getAttr('uid');
        $money = trim((string) $this->request->param('money', ''));
        if (!is_numeric($money)) {
            return $this->fail('请输入正确的提现金额');
        }
        $collect = WithdrawService::apply(
            $uid,
            MemberCollect::T_SUPPLY,
            $money,
            (int) $this->request->param('way_id', 0),
            [
                'account'   => trim((string) $this->request->param('account', '')),
                'true_name' => trim((string) $this->request->param('true_name', '')),
                'way_name'  => trim((string) $this->request->param('way_name', '')),
            ],
            trim((string) $this->request->param('mark', ''))
        );
        return $this->ok([
            'id'     => (int) $collect->getAttr('id'),
            'status' => MemberCollect::statusText((int) $collect->getAttr('status')),
        ], '提交成功，等待审核');
    }

    /**
     * 我的货款提现记录
     */
    public function withdraw_index()
    {
        $uid   = (int) $this->merchant()->getAttr('uid');
        $page  = max(1, (int) $this->request->param('page', 1));
        $limit = min(50, max(1, (int) $this->request->param('limit', 10)));
        $query = MemberCollect::where('apply_uid', $uid)->where('type', MemberCollect::T_SUPPLY);
        $total = (clone $query)->count();
        $list  = $query->order('id desc')->page($page, $limit)
            ->field('id,way_name,account,true_name,money,fee,status,type,mark,create_time,check_time')
            ->select()->toArray();
        foreach ($list as &$row) {
            $row['status_text'] = MemberCollect::statusText((int) $row['status']);
            $row['type_text']   = MemberCollect::typeText((int) $row['type']);
        }
        unset($row);
        return $this->ok(['list' => $list, 'count' => $total]);
    }

    /**
     * 可用提现方式：本人绑定 + 平台全局方式
     */
    protected function waysOf(int $uid): array
    {
        return MemberCollectWay::where(function ($q) use ($uid) {
            $q->whereOr('uid', $uid)->whereOr(function ($q2) {
                $q2->where('uid', 0)->where('status', MemberCollectWay::ST_APPROVED);
            });
        })->where('status', MemberCollectWay::ST_APPROVED)
            ->order('is_default desc, id desc')
            ->field('id,uid,name,company,account,true_name,is_default,status')
            ->select()->toArray();
    }

    /**
     * 货款流水类型文案
     */
    public static function tradeText(): array
    {
        return [
            MemberSupply::T_ORDER_SUPPLY        => '订单货款入账',
            MemberSupply::T_SUPPLY_BACK         => '退款货款回冲',
            MemberSupply::T_ORDER_SUPPLY_FREEZE => '订单货款冻结',
            MemberSupply::T_SUPPLY_UNFREEZE     => '货款解冻',
            MemberSupply::T_WITHDRAW_APPLY      => '货款提现申请',
            MemberSupply::T_WITHDRAW_REFUND     => '提现驳回退回',
            MemberSupply::T_ADMIN_ADD           => '平台加款',
            MemberSupply::T_ADMIN_SUB           => '平台扣款',
        ];
    }
}
