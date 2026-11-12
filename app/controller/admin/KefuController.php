<?php
// +----------------------------------------------------------------------
// | 后台 · 客服工作台（M5；会话列表/消息/回复/关闭）
// 权限点：kefu/*（已登记 mks_admin_router，默认拒绝）
// +----------------------------------------------------------------------
declare (strict_types = 1);

namespace app\controller\admin;

use app\common\model\KefuMessage;
use app\common\model\KefuSession;

class KefuController extends Base
{
    /**
     * 会话列表（默认进行中优先，支持未读/关键词筛选）
     */
    public function index()
    {
        $page  = max(1, (int) $this->request->param('page', 1));
        $limit = min(100, max(1, (int) $this->request->param('limit', 20)));
        $status = $this->request->param('status', '');
        $keyword = trim((string) $this->request->param('keyword', ''));
        $onlyUnread = (int) $this->request->param('unread', 0) === 1;

        $query = KefuSession::where('delete_time', 0);
        if ($status !== '' && $status !== null) {
            $query = $query->where('status', (int) $status);
        }
        if ($onlyUnread) {
            $query = $query->where('unread_admin', '>', 0);
        }
        if ($keyword !== '') {
            $query = $query->where(function ($q) use ($keyword) {
                $q->whereLike('nickname', '%' . $keyword . '%')
                  ->whereOr('last_msg', 'like', '%' . $keyword . '%')
                  ->whereOr('uid', (int) $keyword);
            });
        }
        $total = (clone $query)->count();
        $list  = $query->order('last_time desc, id desc')->page($page, $limit)
            ->field('id,uid,nickname,avatar,source,status,admin_id,admin_name,last_msg,last_time,unread_admin,unread_member,create_time')
            ->select()->toArray();
        return $this->ok(['list' => $list, 'count' => $total]);
    }

    /**
     * 拉取会话消息（last_id 增量；读取后清除客服未读并标记会员消息已读）
     */
    public function messages()
    {
        $sessionId = (int) $this->request->param('session_id', 0);
        $lastId    = (int) $this->request->param('last_id', 0);
        $session = KefuSession::where('id', $sessionId)->where('delete_time', 0)->findOrEmpty();
        if ($session->isEmpty()) {
            return $this->fail('会话不存在');
        }
        if ((int) $session->getAttr('admin_id') === 0) {
            $info = $this->adminInfo();
            $session->save([
                'admin_id'   => $this->adminId(),
                'admin_name' => (string) ($info['username'] ?? '客服'),
            ]);
        }
        $rows = KefuMessage::where('session_id', $sessionId)
            ->where('id', '>', $lastId)
            ->order('id asc')->limit(200)->select()->toArray();
        KefuMessage::where('session_id', $sessionId)
            ->where('from_type', KefuMessage::FROM_MEMBER)
            ->where('is_read', 0)
            ->update(['is_read' => 1]);
        $session->save(['unread_admin' => 0]);
        return $this->ok([
            'session' => $session->toArray(),
            'list'    => $rows,
        ]);
    }

    /**
     * 回复会员
     */
    public function reply()
    {
        $sessionId = (int) $this->request->param('session_id', 0);
        $content = trim((string) $this->request->param('content', ''));
        if ($content === '') {
            return $this->fail('请输入回复内容');
        }
        if (mb_strlen($content, 'UTF-8') > 500) {
            return $this->fail('回复不能超过 500 字');
        }
        $session = KefuSession::where('id', $sessionId)->where('delete_time', 0)->findOrEmpty();
        if ($session->isEmpty()) {
            return $this->fail('会话不存在');
        }
        if ((int) $session->getAttr('status') !== KefuSession::ST_OPEN) {
            return $this->fail('会话已关闭');
        }
        $info = $this->adminInfo();
        $msg = new KefuMessage();
        $msg->save([
            'session_id'  => $sessionId,
            'from_type'   => KefuMessage::FROM_ADMIN,
            'uid'         => (int) $session->getAttr('uid'),
            'admin_id'    => $this->adminId(),
            'content'     => $content,
            'create_time' => time(),
        ]);
        $session->save([
            'admin_id'     => $this->adminId(),
            'admin_name'   => (string) ($info['username'] ?? '客服'),
            'last_msg'     => mb_substr($content, 0, 100, 'UTF-8'),
            'last_time'    => time(),
            'unread_member' => (int) $session->getAttr('unread_member') + 1,
            'update_time'  => time(),
        ]);
        return $this->ok(['id' => (int) $msg->getAttr('id')], '已回复');
    }

    /**
     * 关闭会话（会员可重新发起新会话）
     */
    public function close()
    {
        $sessionId = (int) $this->request->param('session_id', 0);
        $session = KefuSession::where('id', $sessionId)->where('delete_time', 0)->findOrEmpty();
        if ($session->isEmpty()) {
            return $this->fail('会话不存在');
        }
        $session->save(['status' => KefuSession::ST_CLOSED, 'update_time' => time()]);
        (new KefuMessage())->save([
            'session_id'  => $sessionId,
            'from_type'   => KefuMessage::FROM_SYSTEM,
            'content'     => '会话已结束，如有问题请重新发起咨询。',
            'create_time' => time(),
        ]);
        return $this->ok([], '会话已关闭');
    }
}
