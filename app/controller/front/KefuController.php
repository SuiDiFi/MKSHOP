<?php
// +----------------------------------------------------------------------
// | 前台 · 在线客服（M5；轮询拉取，登录会员发起会话）
// +----------------------------------------------------------------------
declare (strict_types = 1);

namespace app\controller\front;

use app\common\model\KefuMessage;
use app\common\model\KefuSession;
use app\common\model\Member;
use mks\BaseController;

class KefuController extends BaseController
{
    /**
     * 发起/续接会话（同一会员复用进行中会话）
     */
    public function start()
    {
        $uid = (int) ($this->request->memberId ?? 0);
        $member = Member::findOrEmpty($uid);
        if ($member->isEmpty()) {
            return $this->fail('请先登录');
        }
        $session = KefuSession::where('uid', $uid)
            ->where('status', KefuSession::ST_OPEN)
            ->where('delete_time', 0)
            ->order('id desc')
            ->findOrEmpty();
        if ($session->isEmpty()) {
            $session = new KefuSession();
            $session->save([
                'uid'         => $uid,
                'nickname'    => (string) ($member->getAttr('nickname') ?: $member->getAttr('username')),
                'avatar'      => (string) $member->getAttr('avatar'),
                'source'      => (string) $this->request->param('source', 'pc'),
                'status'      => KefuSession::ST_OPEN,
                'last_msg'    => '',
                'last_time'   => time(),
                'create_time' => time(),
                'update_time' => time(),
            ]);
            (new KefuMessage())->save([
                'session_id'  => (int) $session->getAttr('id'),
                'from_type'   => KefuMessage::FROM_SYSTEM,
                'content'     => '您好，客服已上线，请描述您的问题～',
                'create_time' => time(),
            ]);
        }
        // 会员侧进入会话：清除会员未读
        $session->save(['unread_member' => 0]);
        return $this->ok([
            'session_id' => (int) $session->getAttr('id'),
            'status'     => (int) $session->getAttr('status'),
        ]);
    }

    /**
     * 拉取消息（last_id 增量；读取后把客服消息标记已读）
     */
    public function messages()
    {
        $uid = (int) ($this->request->memberId ?? 0);
        $sessionId = (int) $this->request->param('session_id', 0);
        $lastId    = (int) $this->request->param('last_id', 0);
        $session = KefuSession::where('id', $sessionId)->where('uid', $uid)->where('delete_time', 0)->findOrEmpty();
        if ($session->isEmpty()) {
            return $this->fail('会话不存在');
        }
        $rows = KefuMessage::where('session_id', $sessionId)
            ->where('id', '>', $lastId)
            ->order('id asc')
            ->limit(100)
            ->select()->toArray();
        KefuMessage::where('session_id', $sessionId)
            ->where('from_type', KefuMessage::FROM_ADMIN)
            ->where('is_read', 0)
            ->update(['is_read' => 1]);
        $session->save(['unread_member' => 0]);
        return $this->ok([
            'list'   => $rows,
            'status' => (int) $session->getAttr('status'),
        ]);
    }

    /**
     * 发送消息
     */
    public function send()
    {
        $uid = (int) ($this->request->memberId ?? 0);
        $sessionId = (int) $this->request->param('session_id', 0);
        $content = trim((string) $this->request->param('content', ''));
        if ($content === '') {
            return $this->fail('请输入消息内容');
        }
        if (mb_strlen($content, 'UTF-8') > 500) {
            return $this->fail('消息不能超过 500 字');
        }
        $session = KefuSession::where('id', $sessionId)->where('uid', $uid)->where('delete_time', 0)->findOrEmpty();
        if ($session->isEmpty()) {
            return $this->fail('会话不存在');
        }
        if ((int) $session->getAttr('status') !== KefuSession::ST_OPEN) {
            return $this->fail('会话已关闭，请重新发起咨询');
        }
        $msg = new KefuMessage();
        $msg->save([
            'session_id'  => $sessionId,
            'from_type'   => KefuMessage::FROM_MEMBER,
            'uid'         => $uid,
            'content'     => $content,
            'create_time' => time(),
        ]);
        $session->save([
            'last_msg'     => mb_substr($content, 0, 100, 'UTF-8'),
            'last_time'    => time(),
            'unread_admin' => (int) $session->getAttr('unread_admin') + 1,
            'update_time'  => time(),
        ]);
        return $this->ok(['id' => (int) $msg->getAttr('id')], '已发送');
    }
}
