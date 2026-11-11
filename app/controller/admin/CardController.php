<?php
// +----------------------------------------------------------------------
// | 后台 · 卡密管理（对齐权限点 card/*）
// 卡库类型 card_cate.card_type：1=卡密 2=卡号+卡密 3=固定卡密 4=固定卡号卡密
// 导入格式：每行一条；类型1=每行即卡密；类型2/4=卡号<分隔符>卡密（默认 ----）；类型3=卡号<分隔符>固定卡密
// 红线：卡号/卡密 AES-256-GCM 加密入库 + md5 去重
// +----------------------------------------------------------------------
declare (strict_types = 1);

namespace app\controller\admin;

use app\common\model\Card;
use app\common\model\CardCate;
use app\common\model\CardHistory;
use mks\traits\CurdTrait;
use mks\util\Crypt;
use think\facade\Db;

class CardController extends Base
{
    use CurdTrait;

    protected string $crudModel = Card::class;

    protected array $crudSearchable = [
        'card_cate_id' => 'eq',
        'status'       => 'eq',
        'order_id'     => 'eq',
        'card_type'    => 'eq',
        'docking_id'   => 'eq',
    ];

    protected array $crudModifiable = ['status'];

    protected array $crudFields = [
        'goods_id', 'card_no', 'card_password', 'card_cate_id', 'cost',
        'end_time', 'card_type', 'status', 'card_show_type', 'sale_first',
    ];

    protected string $crudOrder = 'id desc';

    /**
     * 列表：解密后的卡号/卡密默认脱敏（列表页 show_full=1 才全显）
     */
    public function index()
    {
        $showFull = (int) $this->request->param('show_full', 0) === 1;
        $result   = $this->crudIndex();
        $data     = $result->getData();
        $list     = (array) ($data['data']['list'] ?? []);
        foreach ($list as &$row) {
            if (!$showFull) {
                $row['card_no']       = $this->mask((string) $row['card_no']);
                $row['card_password'] = $this->mask((string) $row['card_password']);
            }
        }
        unset($row);
        $data['data']['list'] = $list;
        return json_ok($data['data'], $data['msg']);
    }

    /**
     * 详情（明文）
     */
    public function read(int $id)
    {
        $row = Card::findOrEmpty($id);
        if ($row->isEmpty()) {
            return $this->fail('卡密不存在');
        }
        return $this->ok($row);
    }

    /**
     * 新建单条卡密
     */
    public function create()
    {
        $data = $this->crudFormData();
        $cateId = (int) ($data['card_cate_id'] ?? 0);
        $cate = CardCate::findOrEmpty($cateId);
        if ($cate->isEmpty()) {
            return $this->fail('请选择所属卡库');
        }
        $data['card_cate_id'] = $cateId;
        $data['card_type']    = $data['card_type'] ?? $cate->getAttr('card_type');
        $data['create_time']  = time();
        $rows = $this->dedupRows($cate, [$this->normalizeRow($data)]);
        if ($rows === []) {
            return $this->fail('卡密已存在（重复）');
        }
        $this->insertCards($rows, $cate);
        return $this->ok([], '创建成功');
    }

    /**
     * 导入卡密（批量）：form { card_cate_id, content, split, cost, sale_first, end_time, mark }
     */
    public function save()
    {
        $form     = (array) ($this->request->param('form') ?: $this->request->param());
        $cateId   = (int) ($form['card_cate_id'] ?? 0);
        $content  = (string) ($form['content'] ?? '');
        $split    = (string) ($form['split'] ?? '----');
        $cost     = (float) ($form['cost'] ?? 0);
        $mark     = (string) ($form['mark'] ?? '');
        if ($cateId <= 0) {
            return $this->fail('请选择所属卡库');
        }
        $cate = CardCate::findOrEmpty($cateId);
        if ($cate->isEmpty()) {
            return $this->fail('卡库不存在');
        }
        if (trim($content) === '') {
            return $this->fail('请粘贴卡密内容');
        }

        $cateType = (int) $cate->getAttr('card_type');
        $fixedPwd = $cateType === 3 ? (string) $cate->getAttr('card_password_alias') : '';

        $parsed = [];
        $lines  = preg_split('/\r\n|\r|\n/', trim($content)) ?: [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $row = [
                'card_cate_id'   => $cateId,
                'card_type'      => $cateType,
                'cost'           => $cost,
                'sale_first'     => (int) ($form['sale_first'] ?? 0),
                'end_time'       => (int) ($form['end_time'] ?? 0),
                'create_time'    => time(),
                'card_show_type' => 1,
            ];
            $hasSep = $split !== '' && str_contains($line, $split);
            if ($cateType === 1) {
                $row['card_no']       = '';
                $row['card_password'] = $line;
            } elseif ($hasSep) {
                [$no, $pwd] = array_pad(explode($split, $line, 2), 2, '');
                $row['card_no']       = trim($no);
                $row['card_password'] = trim($pwd);
            } elseif ($cateType === 3) {
                $row['card_no']       = $line;
                $row['card_password'] = $fixedPwd;
            } else {
                // 类型2/4 无分隔符：整行当卡号，卡密留空（固定卡号卡密场景在别名中说明）
                $row['card_no']       = $line;
                $row['card_password'] = $cateType === 4 ? $fixedPwd : '';
            }
            $parsed[] = $row;
        }
        if ($parsed === []) {
            return $this->fail('未解析到有效卡密');
        }
        $rows = $this->dedupRows($cate, $parsed);
        if ($rows === []) {
            return $this->fail('导入的卡密全部重复，未入库');
        }
        $inserted = $this->insertCards($rows, $cate);

        // 导卡历史
        CardHistory::create([
            'card_cate_id'   => $cateId,
            'card_cate_name' => (string) $cate->getAttr('name'),
            'count'          => $inserted,
            'uid'            => (string) $this->adminId(),
            'cost'           => round($cost * $inserted, 5),
            'mark'           => $mark,
            'sale_first'     => (int) ($form['sale_first'] ?? 0),
            'create_time'    => time(),
            'end_time'       => 0,
        ]);
        $dup = count($parsed) - $inserted;
        return $this->ok(['imported' => $inserted, 'duplicate' => $dup], "导入成功 {$inserted} 条" . ($dup > 0 ? "，去重跳过 {$dup} 条" : ''));
    }

    /**
     * 删除（软删）+ 卡库数量回算
     */
    public function delete(int $id)
    {
        $row = Card::findOrEmpty($id);
        if ($row->isEmpty()) {
            return $this->fail('卡密不存在');
        }
        $cateId = (int) $row->getAttr('card_cate_id');
        $row->delete();
        $this->refreshCateNum($cateId);
        return $this->ok([], '删除成功');
    }

    /**
     * 批量删除：form { card_cate_id, status? }（status 不传=删该库全部待售）
     */
    public function delete_all()
    {
        $cateId = (int) $this->request->param('card_cate_id', 0);
        $status = $this->request->param('status');
        if ($cateId <= 0) {
            return $this->fail('请选择卡库');
        }
        $query = Card::where('card_cate_id', $cateId);
        if ($status !== null && $status !== '') {
            $query = $query->where('status', (int) $status);
        } else {
            $query = $query->where('status', 1); // 默认只清待售
        }
        $ids = $query->column('id');
        if ($ids === []) {
            return $this->fail('没有可删除的卡密');
        }
        $count = count($ids);
        // 静态查询 delete() 在 defaultSoftDelete=0 时会被框架写成 delete_time=0（无效），
        // 批量软删必须显式 UPDATE（红线：软删统一 delete_time）
        Db::name('card')->whereIn('id', $ids)->where('delete_time', 0)->update([
            'delete_time' => time(),
            'update_time' => time(),
        ]);
        $this->refreshCateNum($cateId);
        return $this->ok(['deleted' => $count], "已删除 {$count} 条");
    }

    /**
     * 去重（同库同内容唯一：md5 对比）
     */
    protected function dedupRows(CardCate $cate, array $rows): array
    {
        $noMd5 = [];
        $pwdMd5 = [];
        foreach ($rows as $r) {
            $no = (string) ($r['card_no'] ?? '');
            $pwd = (string) ($r['card_password'] ?? '');
            if ($no !== '') {
                $noMd5[] = md5($no);
            }
            if ($pwd !== '') {
                $pwdMd5[] = md5($pwd);
            }
        }
        $existsNo  = $noMd5 === [] ? [] : Card::where('card_cate_id', (int) $cate->getAttr('id'))->whereIn('card_no_md5', array_unique($noMd5))->column('card_no_md5');
        $existsPwd = $pwdMd5 === [] ? [] : Card::where('card_cate_id', (int) $cate->getAttr('id'))->whereIn('card_password_md5', array_unique($pwdMd5))->column('card_password_md5');
        $existsNo  = array_flip($existsNo);
        $existsPwd = array_flip($existsPwd);

        $seen = [];
        $out  = [];
        foreach ($rows as $r) {
            $no  = (string) ($r['card_no'] ?? '');
            $pwd = (string) ($r['card_password'] ?? '');
            $key = md5($no . "\x00" . $pwd);
            if (isset($seen[$key])) {
                continue;
            }
            $dup = false;
            if ($no !== '' && isset($existsNo[md5($no)])) {
                $dup = true;
            }
            if (!$dup && $no === '' && $pwd !== '' && isset($existsPwd[md5($pwd)])) {
                $dup = true;
            }
            if ($dup) {
                continue;
            }
            $seen[$key] = true;
            $r['card_no_md5']       = $no !== '' ? md5($no) : '';
            $r['card_password_md5'] = $pwd !== '' ? md5($pwd) : '';
            // Db 直插绕过模型修改器 → 此处显式加密（Crypt 对已加密文本防二次加密）
            $r['card_no']       = $no !== '' ? Crypt::encrypt($no) : '';
            $r['card_password'] = $pwd !== '' ? Crypt::encrypt($pwd) : '';
            $out[] = $r;
        }
        return $out;
    }

    /**
     * 批量入库（分块）+ 卡库数量刷新
     */
    protected function insertCards(array $rows, CardCate $cate): int
    {
        Db::startTrans();
        try {
            foreach (array_chunk($rows, 500) as $chunk) {
                Db::name('card')->insertAll($chunk);
            }
            Db::commit();
        } catch (\Throwable $e) {
            Db::rollback();
            return 0;
        }
        $this->refreshCateNum((int) $cate->getAttr('id'));
        return count($rows);
    }

    /**
     * 卡库冗余数量回算（待售数）
     */
    protected function refreshCateNum(int $cateId): void
    {
        if ($cateId <= 0) {
            return;
        }
        CardCate::where('id', $cateId)->update([
            'card_num'   => Card::where('card_cate_id', $cateId)->where('status', 1)->count(),
            'update_time' => time(),
        ]);
    }

    /**
     * 脱敏（保留首尾 2/2 位）
     */
    protected function mask(string $v): string
    {
        $len = mb_strlen($v);
        if ($len <= 6) {
            return str_repeat('*', $len);
        }
        return mb_substr($v, 0, 2) . str_repeat('*', $len - 4) . mb_substr($v, -2);
    }

    /**
     * 单条规范化（create 用）
     */
    protected function normalizeRow(array $data): array
    {
        return [
            'card_cate_id'   => (int) ($data['card_cate_id'] ?? 0),
            'card_type'      => (int) ($data['card_type'] ?? 1),
            'card_no'        => (string) ($data['card_no'] ?? ''),
            'card_password'  => (string) ($data['card_password'] ?? ''),
            'cost'           => (float) ($data['cost'] ?? 0),
            'end_time'       => (int) ($data['end_time'] ?? 0),
            'status'         => (int) ($data['status'] ?? 1),
            'sale_first'     => (int) ($data['sale_first'] ?? 0),
            'card_show_type' => (int) ($data['card_show_type'] ?? 1),
            'create_time'    => time(),
        ];
    }
}
