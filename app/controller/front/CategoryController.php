<?php
// +----------------------------------------------------------------------
// | 前台 · 分类页（M5：分类树 + 各分类在售数量）
// +----------------------------------------------------------------------
declare (strict_types = 1);

namespace app\controller\front;

use app\common\model\GoodsCategory;
use mks\BaseController;

class CategoryController extends BaseController
{
    public function index()
    {
        $rows = GoodsCategory::where('status', 1)
            ->order('sort desc, id asc')
            ->field('id,pid,name,goods_num,img')
            ->select()->toArray();
        return $this->ok(['list' => $this->tree($rows, 0)]);
    }

    /**
     * 组装两级分类树（前台仅两级，多余层级拍平到父级）
     */
    private function tree(array $rows, int $pid): array
    {
        $out = [];
        foreach ($rows as $r) {
            if ((int) $r['pid'] !== $pid) {
                continue;
            }
            $children = $this->tree($rows, (int) $r['id']);
            $out[] = [
                'id'       => (int) $r['id'],
                'name'     => (string) $r['name'],
                'img'      => (string) ($r['img'] ?? ''),
                'goods_num' => (int) $r['goods_num'],
                'children' => $children,
            ];
        }
        return $out;
    }
}
