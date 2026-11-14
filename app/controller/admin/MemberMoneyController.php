<?php
// +----------------------------------------------------------------------
// | 后台 · 会员余额流水（对齐权限点 member_money/*，供详情抽屉「资金明细」页签）
// +----------------------------------------------------------------------
declare (strict_types = 1);

namespace app\controller\admin;

use app\common\model\MemberMoney;
use mks\BaseController;

class MemberMoneyController extends BaseController
{
    /**
     * 资金明细列表
     * GET {uid?, order_id?, extend_id?, page, limit}
     */
    public function index()
    {
        $page  = max(1, (int) $this->request->param('page', 1));
        $limit = min(100, max(1, (int) $this->request->param('limit', 15)));
        $query = MemberMoney::order('id desc');

        $uid = (int) $this->request->param('uid', 0);
        if ($uid > 0) {
            $query = $query->where('uid', $uid);
        }
        $orderId = (int) $this->request->param('order_id', 0);
        if ($orderId > 0) {
            $query = $query->where('order_id', $orderId);
        }
        $extendId = trim((string) $this->request->param('extend_id', ''));
        if ($extendId !== '') {
            $query = $query->where('extend_id', $extendId);
        }
        $tradeType = $this->request->param('trade_type', '');
        if ($tradeType !== '') {
            $query = $query->where('trade_type', (int) $tradeType);
        }

        $total = (clone $query)->count();
        $list  = $query->page($page, $limit)
            ->field('id,uid,money,trade_type,change_type,before,after,create_time,extend_id,mark,order_id,admin_id')
            ->select()->toArray();
        foreach ($list as &$r) {
            $r['trade_type_text']  = MemberMoney::TRADE_TYPE_TEXT[(int) $r['trade_type']] ?? (string) $r['trade_type'];
            $r['change_type_text'] = ((int) $r['change_type'] === MemberMoney::C_INCOME) ? '收入' : '支出';
        }
        return $this->ok(['list' => $list, 'count' => $total]);
    }
}
