<?php
// +----------------------------------------------------------------------
// | 后台 · 商户入驻审核（M6-B；对齐权限点 merchant_apply/*）
// | 通过：建 mks_merchant + member.has_merchant=1 + merchant_id
// +----------------------------------------------------------------------
declare (strict_types = 1);

namespace app\controller\admin;

use app\common\model\Member;
use app\common\model\MerchantApply;
use app\common\service\MerchantService;

class MerchantApplyController extends Base
{
    /**
     * 入驻申请列表
     */
    public function index()
    {
        $page  = max(1, (int) $this->request->param('page', 1));
        $limit = min(100, max(1, (int) $this->request->param('limit', 15)));
        $query = MerchantApply::order('id desc');
        $status = $this->request->param('status', '');
        if ($status !== '' && $status !== null) {
            $query = $query->where('status', (int) $status);
        }
        $keyword = trim((string) $this->request->param('keyword', ''));
        if ($keyword !== '') {
            $query = $query->where(function ($q) use ($keyword) {
                $q->whereLike('merchant_name', '%' . $keyword . '%')
                    ->whereOr('contact', 'like', '%' . $keyword . '%')
                    ->whereOr('mobile', 'like', '%' . $keyword . '%');
            });
        }
        $total = (clone $query)->count();
        $list  = $query->page($page, $limit)->select()->toArray();
        $uids  = array_filter(array_column($list, 'uid'));
        $members = $uids ? Member::whereIn('id', $uids)->column('username,nickname,has_merchant', 'id') : [];
        foreach ($list as &$row) {
            $uid = (int) $row['uid'];
            $row['username']    = $members[$uid]['username'] ?? '';
            $row['nickname']    = $members[$uid]['nickname'] ?? '';
            $row['status_text'] = MerchantApply::statusText((int) $row['status']);
        }
        unset($row);
        return $this->ok(['list' => $list, 'count' => $total]);
    }

    /**
     * 申请详情
     */
    public function read(int $id)
    {
        $apply = MerchantApply::findOrEmpty($id);
        if ($apply->isEmpty()) {
            return $this->fail('申请不存在');
        }
        $member = Member::findOrEmpty((int) $apply->getAttr('uid'));
        return $this->ok([
            'apply'  => $apply->toArray(),
            'member' => $member->isEmpty() ? null : [
                'id'           => (int) $member->getAttr('id'),
                'username'     => (string) $member->getAttr('username'),
                'nickname'     => (string) $member->getAttr('nickname'),
                'mobile'       => (string) $member->getAttr('mobile'),
                'has_merchant' => (int) $member->getAttr('has_merchant'),
                'merchant_id'  => (int) $member->getAttr('merchant_id'),
            ],
        ]);
    }

    /**
     * 审核（2=通过 / 3=拒绝）
     * @param {status, mark?}
     */
    public function audit(int $id)
    {
        $status = (int) $this->request->param('status', 0);
        $mark   = trim((string) $this->request->param('mark', ''));
        $admin  = $this->adminInfo();
        MerchantService::audit(
            $id,
            $status,
            $this->adminId(),
            (string) ($admin['username'] ?? 'admin'),
            $mark
        );
        return $this->ok([], $status === MerchantApply::ST_APPROVED ? '已通过，商户已开通' : '已拒绝');
    }
}
