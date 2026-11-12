<?php
// +----------------------------------------------------------------------
// | 前台 · 公告 / 帮助中心（M5；cms_article 按 type 区分）
// +----------------------------------------------------------------------
declare (strict_types = 1);

namespace app\controller\front;

use app\common\model\CmsArticle;
use mks\BaseController;

class ArticleController extends BaseController
{
    /**
     * 公告列表（type=1）
     */
    public function noticeIndex()
    {
        return $this->listByType(CmsArticle::TYPE_NOTICE);
    }

    /**
     * 帮助中心列表（type=2）
     */
    public function helpList()
    {
        return $this->listByType(CmsArticle::TYPE_HELP);
    }

    /**
     * 文章详情（公告/帮助共用；仅返回已显示且未删除）
     */
    public function info(int $id)
    {
        $row = CmsArticle::where('id', $id)->where('status', 1)->where('delete_time', 0)->findOrEmpty();
        if ($row->isEmpty()) {
            return $this->fail('内容不存在或已下架');
        }
        return $this->ok($row->toArray());
    }

    private function listByType(int $type)
    {
        $page  = max(1, (int) $this->request->param('page', 1));
        $limit = min(50, max(1, (int) $this->request->param('limit', 10)));
        $query = CmsArticle::where('type', $type)->where('status', 1)->where('delete_time', 0);
        $keyword = trim((string) $this->request->param('keyword', ''));
        if ($keyword !== '') {
            $query = $query->whereLike('title', '%' . $keyword . '%');
        }
        $total = (clone $query)->count();
        $list  = $query->order('is_top desc, sort desc, id desc')->page($page, $limit)
            ->field('id,title,summary,is_top,create_time')
            ->select()->toArray();
        return $this->ok(['list' => $list, 'count' => $total]);
    }
}
