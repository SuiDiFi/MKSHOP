<?php
// +----------------------------------------------------------------------
// | 后台 · 提现审核（M4；对齐权限点 member_collect/*）
// 流程：1 待审核 → 2 已通过（线下已打款，冻结额出账）/ 3 已拒绝（冻结额退回）
// +----------------------------------------------------------------------
declare (strict_types = 1);

namespace app\controller\admin;

use app\common\model\MemberCollect;
use app\common\service\WithdrawService;

class MemberCollectController extends Base
{
    /**
     * 提现申请列表
     */
    public function index()
    {
        $page  = max(1, (int) $this->request->param('page', 1));
        $limit = min(100, max(1, (int) $this->request->param('limit', 15)));
        $query = MemberCollect::order('id desc');
        $status = $this->request->param('status', '');
        if ($status !== '' && $status !== null) {
            $query = $query->where('status', (int) $status);
        }
        $type = $this->request->param('type', '');
        if ($type !== '' && $type !== null) {
            $query = $query->where('type', (int) $type);
        }
        $uid = (int) $this->request->param('apply_uid', 0);
        if ($uid > 0) {
            $query = $query->where('apply_uid', $uid);
        }
        $keyword = trim((string) $this->request->param('keyword', ''));
        if ($keyword !== '') {
            $query = $query->where(function ($q) use ($keyword) {
                $q->whereOr('apply_uname', 'like', '%' . $keyword . '%')
                    ->whereOr('true_name', 'like', '%' . $keyword . '%')
                    ->whereOr('account', 'like', '%' . $keyword . '%');
            });
        }
        $total = (clone $query)->count();
        $list  = $query->page($page, $limit)->select()->toArray();
        foreach ($list as &$row) {
            $row['status_text'] = MemberCollect::statusText((int) $row['status']);
            $row['type_text']   = MemberCollect::typeText((int) $row['type']);
            $row['real_money']  = bcsub((string) $row['money'], (string) $row['fee'], 4);
        }
        unset($row);
        return $this->ok(['list' => $list, 'count' => $total]);
    }

    /**
     * 审核处理（2=通过打款 / 3=拒绝退回）
     * @param {status, mark?}
     */
    public function dual(int $id)
    {
        $status = (int) $this->request->param('status', 0);
        $mark   = trim((string) $this->request->param('mark', ''));
        $admin  = $this->adminInfo();
        WithdrawService::review(
            $id,
            $status,
            $this->adminId(),
            (string) ($admin['username'] ?? 'admin'),
            $mark
        );
        return $this->ok([], $status === MemberCollect::ST_APPROVED ? '已通过并确认打款' : '已拒绝，金额已退回');
    }
}
