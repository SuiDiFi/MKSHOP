<?php
// +----------------------------------------------------------------------
// | 后台 · 会员等级管理（M4；对齐权限点 member_level/*）
// 关键字段：升级门槛 amount/消费额/充值额/邀请人数；折扣率 pay_rate；两级佣金比例
// +----------------------------------------------------------------------
declare (strict_types = 1);

namespace app\controller\admin;

use app\common\model\MemberLevel;
use mks\traits\CurdTrait;

class MemberLevelController extends Base
{
    use CurdTrait;

    protected string $crudModel = MemberLevel::class;

    protected array $crudSearchable = [
        'id'        => 'eq',
        'name'      => 'like',
        'is_default' => 'eq',
        'can_up'    => 'eq',
    ];

    protected array $crudModifiable = [
        'name', 'amount', 'pay_rate', 'one_rate', 'two_rate',
        'can_up', 'is_up', 'is_default', 'invite_num', 'limit_price',
    ];

    protected array $crudFields = [
        'name', 'amount', 'pay_rate', 'can_up', 'thumb', 'is_default', 'description',
        'one_rate', 'two_rate', 'invite_num', 'commission_check', 'total_commission_check',
        'is_up', 'consumption_money', 'recharge_money', 'limit_price',
    ];

    protected string $crudOrder = 'amount desc, id asc';

    protected array $crudOrderable = ['id', 'amount', 'pay_rate', 'one_rate', 'two_rate'];

    /**
     * 新建等级（默认等级唯一）
     */
    public function create()
    {
        $data = $this->crudFormData();
        if ($data === []) {
            return $this->fail('没有需要保存的数据');
        }
        $data['create_time'] = time();
        $level = new MemberLevel();
        $level->allowField($this->crudFields)->save($data);
        $this->normalizeDefault((int) $level->getAttr('id'), (int) ($data['is_default'] ?? 0));
        return $this->ok(['id' => (int) $level->getAttr('id')], '创建成功');
    }

    /**
     * 编辑等级
     */
    public function save(int $id)
    {
        $row = MemberLevel::findOrEmpty($id);
        if ($row->isEmpty()) {
            return $this->fail('等级不存在');
        }
        $data = $this->crudFormData();
        if ($data === []) {
            return $this->fail('没有需要保存的数据');
        }
        $data['update_time'] = time();
        $row->allowField($this->crudFields)->save($data);
        if (array_key_exists('is_default', $data)) {
            $this->normalizeDefault($id, (int) $data['is_default']);
        }
        return $this->ok([], '保存成功');
    }

    /**
     * 默认等级唯一化（注册即此级）
     */
    protected function normalizeDefault(int $id, int $isDefault): void
    {
        if ($isDefault !== 1) {
            return;
        }
        MemberLevel::where('id', '<>', $id)->update(['is_default' => 0]);
    }
}
