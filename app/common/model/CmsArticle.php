<?php
// +----------------------------------------------------------------------
// | 前台内容模型（M5：公告/帮助中心）
// +----------------------------------------------------------------------
declare (strict_types = 1);

namespace app\common\model;

use think\model\concern\SoftDelete;

class CmsArticle extends BaseModel
{
    use SoftDelete;

    protected $name = 'cms_article';

    protected $deleteTime = 'delete_time';
    protected $defaultSoftDelete = 0;

    public const TYPE_NOTICE = 1; // 公告
    public const TYPE_HELP   = 2; // 帮助中心
}
