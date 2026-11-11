<?php
// +----------------------------------------------------------------------
// | 后台 · 卡密库管理（对齐权限点 card_cate/*）
// +----------------------------------------------------------------------
declare (strict_types = 1);

namespace app\controller\admin;

use app\common\model\Card;
use app\common\model\CardCate;
use mks\traits\CurdTrait;

class CardCateController extends Base
{
    use CurdTrait;

    protected string $crudModel = CardCate::class;

    protected array $crudSearchable = [
        'name'   => 'like',
        'status' => 'eq',
        'uid'    => 'eq',
    ];

    protected array $crudModifiable = ['status', 'sort', 'name', 'warn', 'warn_open', 'card_num'];

    protected array $crudFields = [
        'name', 'card_type', 'card_no_alias', 'card_password_alias', 'card_content',
        'cost', 'warn', 'warn_open', 'status', 'sort', 'interval', 'frequency', 'pid',
    ];

    protected string $crudOrder = 'sort desc, id asc';

    /**
     * 新建：必填无默认值列兜底（严格 SQL 模式）
     */
    public function create()
    {
        $data = $this->crudFormData();
        if ($data === []) {
            return $this->fail('没有需要保存的数据');
        }
        $data['card_no_alias']       = (string) ($data['card_no_alias'] ?? '卡号');
        $data['card_password_alias'] = (string) ($data['card_password_alias'] ?? '卡密');
        if (mb_strlen((string) ($data['name'] ?? '')) === 0) {
            return $this->fail('请填写卡库名称');
        }
        $result = $this->crudCreate($data);
        return $result instanceof \think\Response\Json ? $result : $this->ok($result, '创建成功');
    }

    /**
     * 全量（下拉选择，对接权限点 card_cate/list）
     */
    public function list()
    {
        $rows = CardCate::where('status', 1)->order('sort desc, id asc')->select();
        return $this->ok($rows);
    }

    /**
     * 删除：库内仍有卡密则拒绝（防误删）
     */
    public function delete(int $id)
    {
        $row = CardCate::findOrEmpty($id);
        if ($row->isEmpty()) {
            return $this->fail('卡库不存在');
        }
        $rest = Card::where('card_cate_id', $id)->where('delete_time', 0)->count();
        if ($rest > 0) {
            return $this->fail('卡库内仍有 ' . $rest . ' 条卡密，请先清空');
        }
        $row->delete();
        return $this->ok([], '删除成功');
    }
}
