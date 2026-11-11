<?php
// +----------------------------------------------------------------------
// | 前台 · 分销中心（M4：概览/我的团队/佣金明细）
// +----------------------------------------------------------------------
declare (strict_types = 1);

namespace app\controller\front;

use app\common\model\Member;
use app\common\model\MemberCommission;
use app\common\model\MemberLevel;
use mks\BaseController;

class CommissionController extends BaseController
{
    /**
     * 概览（登录态）：佣金三账户 + 邀请码 + 下级数 + 本人等级两级比例
     */
    public function index()
    {
        $member = Member::findOrEmpty((int) ($this->request->memberId ?? 0));
        if ($member->isEmpty()) {
            return $this->fail('请先登录');
        }
        $level = MemberLevel::findOrEmpty((int) $member->getAttr('level_id'));
        return $this->ok([
            'commission_money' => (string) $member->getAttr('commission_money'),
            'total_commission_money' => (string) $member->getAttr('total_commission_money'),
            'invite_code'      => (int) $member->getAttr('id'),  // 邀请码=会员ID
            'child_num'        => (int) $member->getAttr('child_num'),
            'pid'              => (int) $member->getAttr('pid'),
            'level_name'       => (string) ($level->getAttr('name') ?? ''),
            'one_rate'         => (int) ($level->getAttr('one_rate') ?? 0),
            'two_rate'         => (int) ($level->getAttr('two_rate') ?? 0),
        ]);
    }

    /**
     * 我的团队（登录态；一级下级分页 + 各自业绩）
     */
    public function teams()
    {
        $uid   = (int) ($this->request->memberId ?? 0);
        $page  = max(1, (int) $this->request->param('page', 1));
        $limit = min(50, max(1, (int) $this->request->param('limit', 10)));
        $query = Member::where('pid', $uid)->where('delete_time', 0);
        $total = (clone $query)->count();
        $list  = $query->order('id desc')->page($page, $limit)
            ->field('id,username,nickname,avatar,create_time,cost,child_num')
            ->select()->toArray();
        return $this->ok(['list' => $list, 'count' => $total]);
    }

    /**
     * 佣金明细（登录态；member_commission 流水分页）
     */
    public function logs()
    {
        $uid   = (int) ($this->request->memberId ?? 0);
        $page  = max(1, (int) $this->request->param('page', 1));
        $limit = min(50, max(1, (int) $this->request->param('limit', 10)));
        $query = MemberCommission::where('uid', $uid);
        $total = (clone $query)->count();
        $list  = $query->order('id desc')->page($page, $limit)
            ->field('id,money,trade_type,change_type,before,after,order_id,mark,create_time')
            ->select()->toArray();
        return $this->ok(['list' => $list, 'count' => $total]);
    }
}
