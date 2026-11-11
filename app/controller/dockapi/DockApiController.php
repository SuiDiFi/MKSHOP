<?php
// +----------------------------------------------------------------------
// | 对外供货 API 控制器（dockapi 供货商端；下游商户调用我们）
// | 鉴权在 Service 层完成（Header Sign/Timestamp/UserId）
// +----------------------------------------------------------------------
declare (strict_types = 1);

namespace app\controller\dockapi;

use app\common\service\DockApiService;
use mks\BaseController;

class DockApiController extends BaseController
{
    /**
     * 余额查询
     */
    public function userMoney()
    {
        $member = DockApiService::auth($this->request);
        return $this->ok(DockApiService::userMoney($member));
    }

    /**
     * 商品列表
     */
    public function goodsList()
    {
        DockApiService::auth($this->request);
        $data = DockApiService::goodsList(
            max(1, (int) $this->request->param('page', 1)),
            min(100, max(1, (int) $this->request->param('limit', 20))),
            (int) $this->request->param('cate_id', 0),
            (string) $this->request->param('keyword', '')
        );
        return $this->ok($data);
    }

    /**
     * 商品详情
     */
    public function goodsDetail()
    {
        DockApiService::auth($this->request);
        $id = (int) $this->request->param('id', 0);
        return $this->ok(DockApiService::goodsDetail($id));
    }

    /**
     * 下单（余额支付闭环；卡密自动回传）
     */
    public function buy()
    {
        $member = DockApiService::auth($this->request);
        $params = $this->request->post();
        $params['_ip']   = (string) $this->request->ip();
        $params['_host'] = (string) $this->request->host();
        $data = DockApiService::buy($member, $params);
        return $this->ok($data, '下单成功');
    }

    /**
     * 查单
     */
    public function queryOrder()
    {
        $member = DockApiService::auth($this->request);
        $data = DockApiService::queryOrder(
            $member,
            (string) $this->request->param('ordersn', ''),
            (string) $this->request->param('external_orderno', '')
        );
        return $this->ok($data);
    }
}
