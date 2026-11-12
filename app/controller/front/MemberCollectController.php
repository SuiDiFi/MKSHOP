<?php
// +----------------------------------------------------------------------
// | 前台 · 提现（M4：提现配置/申请/记录 + 提现方式绑定）
// | 路由前缀：front/member_collect/*、front/member_collect_way/*
// +----------------------------------------------------------------------
declare (strict_types = 1);

namespace app\controller\front;

use app\common\model\Member;
use app\common\model\MemberCollect;
use app\common\model\MemberCollectWay;
use app\common\service\WithdrawService;
use mks\BaseController;
use mks\util\SysConfig;

class MemberCollectController extends BaseController
{
    /**
     * 我的提现记录（分页）
     */
    public function index()
    {
        $uid   = (int) ($this->request->memberId ?? 0);
        $page  = max(1, (int) $this->request->param('page', 1));
        $limit = min(50, max(1, (int) $this->request->param('limit', 10)));
        $query = MemberCollect::where('apply_uid', $uid);
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
     * 提现配置：可提金额/费率档/我的提现方式
     */
    public function config()
    {
        $uid    = (int) ($this->request->memberId ?? 0);
        $member = Member::findOrEmpty($uid);
        if ($member->isEmpty()) {
            return $this->fail('请先登录');
        }
        $tiers = SysConfig::get('member_collect_config', []);
        if (is_string($tiers)) {
            $tiers = json_decode($tiers, true) ?: [];
        }
        return $this->ok([
            'commission_money'      => (string) $member->getAttr('commission_money'),
            'balance'               => (string) $member->getAttr('balance'),
            'freez_commission_money' => (string) $member->getAttr('freez_commission_money'),
            'freez_money'           => (string) $member->getAttr('freez_money'),
            'fee_tiers'             => $tiers,
            'min_money'             => WithdrawService::minMoney(),
            'tip'                   => (string) SysConfig::get('member_collect_tip', ''),
            'ways'                  => $this->waysOf($uid),
        ]);
    }

    /**
     * 申请提现
     * @param {type:1余额/2佣金, money, way_id?, account?, true_name?, mark?}
     */
    public function save()
    {
        $uid   = (int) ($this->request->memberId ?? 0);
        $type  = (int) $this->request->param('type', MemberCollect::T_COMMISSION);
        $money = trim((string) $this->request->param('money', ''));
        $wayId = (int) $this->request->param('way_id', 0);
        $mark  = trim((string) $this->request->param('mark', ''));
        if (!is_numeric($money)) {
            return $this->fail('请输入正确的提现金额');
        }
        $account = [
            'account'   => trim((string) $this->request->param('account', '')),
            'true_name' => trim((string) $this->request->param('true_name', '')),
            'way_name'  => trim((string) $this->request->param('way_name', '')),
        ];
        $collect = WithdrawService::apply($uid, $type, $money, $wayId, $account, $mark);
        return $this->ok([
            'id'     => (int) $collect->getAttr('id'),
            'status' => MemberCollect::statusText((int) $collect->getAttr('status')),
        ], '提交成功，等待审核');
    }

    /**
     * 我的提现方式
     */
    public function ways()
    {
        return $this->ok($this->waysOf((int) ($this->request->memberId ?? 0)));
    }

    /**
     * 保存提现方式（新增/修改本人方式）
     * @param {id?, name, account, true_name?, company?, is_default?}
     */
    public function way_save()
    {
        $uid  = (int) ($this->request->memberId ?? 0);
        $id   = (int) $this->request->param('id', 0);
        $name = trim((string) $this->request->param('name', ''));
        $account = trim((string) $this->request->param('account', ''));
        if ($name === '' || $account === '') {
            return $this->fail('请填写方式名称与收款账号');
        }
        $data = [
            'name'       => mb_substr($name, 0, 255, 'UTF-8'),
            'account'    => mb_substr($account, 0, 100, 'UTF-8'),
            'true_name'  => mb_substr(trim((string) $this->request->param('true_name', '')), 0, 100, 'UTF-8'),
            'company'    => mb_substr(trim((string) $this->request->param('company', '')), 0, 100, 'UTF-8'),
            'is_default' => (int) $this->request->param('is_default', 0) === 1 ? 1 : 0,
            'status'     => MemberCollectWay::ST_APPROVED, // 会员自绑方式自动通过（对应 config.is_check_merchant_collect_way 关闭场景，默认放行）
        ];
        if ($id > 0) {
            $way = MemberCollectWay::findOrEmpty($id);
            if ($way->isEmpty() || (int) $way->getAttr('uid') !== $uid) {
                return $this->fail('提现方式不存在');
            }
            $way->save($data);
            $wayId = $id;
        } else {
            $way = MemberCollectWay::create(array_merge($data, [
                'uid'         => $uid,
                'uid_type'    => 1,
                'create_time' => time(),
                'update_time' => time(),
            ]));
            $wayId = (int) $way->getAttr('id');
        }
        if ($data['is_default'] === 1) {
            MemberCollectWay::where('uid', $uid)->where('id', '<>', $wayId)->update(['is_default' => 0]);
        }
        return $this->ok(['id' => $wayId], '保存成功');
    }

    /**
     * 删除提现方式（仅本人）
     */
    public function way_delete()
    {
        $uid = (int) ($this->request->memberId ?? 0);
        $id  = (int) $this->request->param('id', 0);
        $way = MemberCollectWay::findOrEmpty($id);
        if ($way->isEmpty() || (int) $way->getAttr('uid') !== $uid) {
            return $this->fail('提现方式不存在');
        }
        $way->delete();
        return $this->ok([], '删除成功');
    }

    /**
     * 可用提现方式：本人绑定 + 平台全局方式（uid=0 且已通过）
     */
    protected function waysOf(int $uid): array
    {
        $list = MemberCollectWay::where(function ($q) use ($uid) {
            $q->whereOr('uid', $uid)->whereOr(function ($q2) {
                $q2->where('uid', 0)->where('status', MemberCollectWay::ST_APPROVED);
            });
        })->where('status', MemberCollectWay::ST_APPROVED)
            ->order('is_default desc, id desc')
            ->field('id,uid,name,company,account,true_name,is_default,status')
            ->select()->toArray();
        return $list;
    }
}
