<?php
// +----------------------------------------------------------------------
// | 后台 · 商户管理（M6-B；对齐权限点 merchant/*）
// | 商户=会员打标（member.has_merchant/merchant_id），货款走 supply_money
// +----------------------------------------------------------------------
declare (strict_types = 1);

namespace app\controller\admin;

use app\common\model\Member;
use app\common\model\MemberSupply;
use app\common\model\Merchant;
use app\common\model\Order;
use app\common\model\OrderSupply;
use app\common\service\MerchantService;
use think\facade\Db;

class MerchantController extends Base
{
    /**
     * 商户列表（含会员账号、货款余额、商品/订单数）
     */
    public function index()
    {
        $page  = max(1, (int) $this->request->param('page', 1));
        $limit = min(100, max(1, (int) $this->request->param('limit', 15)));
        $query = Merchant::where('delete_time', 0);
        $keyword = trim((string) $this->request->param('keyword', ''));
        if ($keyword !== '') {
            $query = $query->where(function ($q) use ($keyword) {
                $q->whereLike('name', '%' . $keyword . '%')
                    ->whereOr('contact', 'like', '%' . $keyword . '%')
                    ->whereOr('mobile', 'like', '%' . $keyword . '%');
            });
        }
        $status = $this->request->param('status', '');
        if ($status !== '' && $status !== null) {
            $query = $query->where('status', (int) $status);
        }
        $total = (clone $query)->count();
        $list  = $query->order('id desc')->page($page, $limit)->select()->toArray();
        $uids  = array_filter(array_column($list, 'uid'));
        $members = $uids ? Member::whereIn('id', $uids)->column('username,nickname,mobile,has_merchant', 'id') : [];
        $goodsCount = [];
        $orderCount = [];
        if ($uids) {
            foreach (Db::name('goods')->whereIn('uid', $uids)->where('delete_time', 0)
                ->group('uid')->field('uid,COUNT(*) AS c')->select() as $g) {
                $goodsCount[(int) $g['uid']] = (int) $g['c'];
            }
            foreach (Db::name('order')->whereIn('supplier_id', $uids)->where('delete_time', 0)
                ->group('supplier_id')->field('supplier_id,COUNT(*) AS c')->select() as $o) {
                $orderCount[(int) $o['supplier_id']] = (int) $o['c'];
            }
        }
        foreach ($list as &$row) {
            $uid = (int) $row['uid'];
            $row['username']     = $members[$uid]['username'] ?? '';
            $row['nickname']     = $members[$uid]['nickname'] ?? '';
            $row['member_mobile'] = $members[$uid]['mobile'] ?? '';
            $row['status_text']  = Merchant::statusText((int) $row['status']);
            $row['goods_count']  = (int) ($goodsCount[$uid] ?? 0);
            $row['order_count']  = (int) ($orderCount[$uid] ?? 0);
        }
        unset($row);
        return $this->ok(['list' => $list, 'count' => $total]);
    }

    /**
     * 商户详情（资料 + 货款钱包 + 结算汇总）
     */
    public function read(int $id)
    {
        $merchant = Merchant::where('id', $id)->where('delete_time', 0)->findOrEmpty();
        if ($merchant->isEmpty()) {
            return $this->fail('商户不存在');
        }
        $uid    = (int) $merchant->getAttr('uid');
        $member = Member::findOrEmpty($uid);
        $supplyQ = OrderSupply::where('supply_id', $uid);
        return $this->ok([
            'merchant' => $merchant->toArray(),
            'member'   => $member->isEmpty() ? null : [
                'id'       => (int) $member->getAttr('id'),
                'username' => (string) $member->getAttr('username'),
                'nickname' => (string) $member->getAttr('nickname'),
                'mobile'   => (string) $member->getAttr('mobile'),
            ],
            'wallet' => [
                'supply_money'       => $member->isEmpty() ? '0.0000' : (string) $member->getAttr('supply_money'),
                'freez_supply_money' => $member->isEmpty() ? '0.0000' : (string) $member->getAttr('freez_supply_money'),
            ],
            'settlement' => [
                'pending'  => (string) (clone $supplyQ)->where('sure_status', OrderSupply::SURE_WAIT)->sum('supply_price'),
                'settled'  => (string) (clone $supplyQ)->where('sure_status', OrderSupply::SURE_SETTLED)->sum('supply_price'),
                'refunded' => (string) $supplyQ->sum('back_supply_price'),
            ],
        ]);
    }

    /**
     * 平台直接创建商户（会员必须未打标）
     * @param {uid, name, logo?, intro?, contact?, mobile?}
     */
    public function create()
    {
        $merchant = MerchantService::createForMember(
            (int) $this->request->param('uid', 0),
            $this->request->param()
        );
        return $this->ok($merchant, '创建成功');
    }

    /**
     * 保存商户资料
     */
    public function save(int $id)
    {
        $merchant = Merchant::where('id', $id)->where('delete_time', 0)->findOrEmpty();
        if ($merchant->isEmpty()) {
            return $this->fail('商户不存在');
        }
        $data = [];
        foreach (['name' => 100, 'logo' => 255, 'intro' => 1000, 'contact' => 50, 'mobile' => 30] as $field => $len) {
            if ($this->request->has($field)) {
                $data[$field] = mb_substr(trim((string) $this->request->param($field, '')), 0, $len, 'UTF-8');
            }
        }
        if (isset($data['name']) && $data['name'] === '') {
            return $this->fail('请填写店铺名');
        }
        if ($this->request->has('settlement_type')) {
            $data['settlement_type'] = (int) $this->request->param('settlement_type', 1) === 2
                ? Merchant::SETTLE_SALE : Merchant::SETTLE_COST;
        }
        $data['update_time'] = time();
        $merchant->save($data);
        return $this->ok($merchant, '保存成功');
    }

    /**
     * 启停商户（停用保留会员打标，MerchantAuth 会拒绝访问）
     * @param {status: 0/1}
     */
    public function status()
    {
        $id     = (int) $this->request->param('id', 0);
        $status = (int) $this->request->param('status', 1) === 1 ? 1 : 0;
        $merchant = Merchant::where('id', $id)->where('delete_time', 0)->findOrEmpty();
        if ($merchant->isEmpty()) {
            return $this->fail('商户不存在');
        }
        $merchant->save(['status' => $status, 'update_time' => time()]);
        return $this->ok(['status' => $status], $status === 1 ? '已启用' : '已停用');
    }

    /**
     * 可绑定会员选项（未开通商户）
     */
    public function member_options()
    {
        $keyword = trim((string) $this->request->param('keyword', ''));
        $query   = Member::where('delete_time', 0)->where('has_merchant', 0);
        if ($keyword !== '') {
            $query = $query->where(function ($q) use ($keyword) {
                $q->whereLike('username', '%' . $keyword . '%')
                    ->whereOr('nickname', 'like', '%' . $keyword . '%')
                    ->whereOr('mobile', 'like', '%' . $keyword . '%');
                if (ctype_digit($keyword)) {
                    $q->whereOr('id', (int) $keyword);
                }
            });
        }
        $list = $query->order('id desc')->limit(30)
            ->field('id,username,nickname,mobile')->select()->toArray();
        return $this->ok($list);
    }

    /**
     * 商户货款流水
     */
    public function supply_flow()
    {
        $page  = max(1, (int) $this->request->param('page', 1));
        $limit = min(100, max(1, (int) $this->request->param('limit', 20)));
        $query = MemberSupply::order('id desc');
        $uid = (int) $this->request->param('uid', 0);
        if ($uid > 0) {
            $query = $query->where('uid', $uid);
        }
        $tradeType = (string) $this->request->param('trade_type', '');
        if ($tradeType !== '') {
            $query = $query->where('trade_type', (int) $tradeType);
        }
        $total = (clone $query)->count();
        $list  = $query->page($page, $limit)->select()->toArray();
        $text  = self::tradeText();
        foreach ($list as &$row) {
            $row['trade_type_text'] = $text[(int) $row['trade_type']] ?? '其他';
        }
        unset($row);
        return $this->ok(['list' => $list, 'count' => $total]);
    }

    /**
     * 供货结算账本（order_supply）
     */
    public function settlement_index()
    {
        $page  = max(1, (int) $this->request->param('page', 1));
        $limit = min(100, max(1, (int) $this->request->param('limit', 20)));
        $query = OrderSupply::order('id desc');
        $uid = (int) $this->request->param('uid', 0);
        if ($uid > 0) {
            $query = $query->where('supply_id', $uid);
        }
        $sure = (string) $this->request->param('sure_status', '');
        if ($sure !== '') {
            $query = $query->where('sure_status', (int) $sure);
        }
        $keyword = trim((string) $this->request->param('keyword', ''));
        if ($keyword !== '') {
            $orderIds = Order::whereLike('ordersn', '%' . $keyword . '%')->column('id');
            $query = $query->whereIn('order_id', $orderIds ?: [0]);
        }
        $total = (clone $query)->count();
        $rows  = $query->page($page, $limit)->select()->toArray();
        $orderIds = array_column($rows, 'order_id');
        $orders   = $orderIds === [] ? [] : Order::whereIn('id', $orderIds)
            ->column('ordersn,goods_name,status,total_price,cost_value', 'id');
        $uids = array_filter(array_unique(array_column($rows, 'supply_id')));
        $members = $uids ? Member::whereIn('id', $uids)->column('username,nickname', 'id') : [];
        $sureText = [1 => '待结算', 2 => '已结算', 3 => '已退款', 4 => '部分退款'];
        foreach ($rows as &$row) {
            $o = $orders[(int) $row['order_id']] ?? null;
            $row['ordersn']       = $o['ordersn'] ?? '';
            $row['goods_name']    = $o['goods_name'] ?? '';
            $row['order_status']  = $o['status'] ?? 0;
            $row['order_total']   = $o['total_price'] ?? '0.0000';
            $row['supply_name']   = $members[(int) $row['supply_id']]['username'] ?? '';
            $row['sure_status_text'] = $sureText[(int) $row['sure_status']] ?? '—';
            $row['frozen_time_text'] = (int) $row['frozen_time'] > 0 ? date('Y-m-d H:i:s', (int) $row['frozen_time']) : '立即';
        }
        unset($row);
        return $this->ok(['list' => $rows, 'count' => $total]);
    }

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
