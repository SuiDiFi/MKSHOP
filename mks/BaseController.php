<?php
// +----------------------------------------------------------------------
// | MKSHOP 应用基类控制器
// +----------------------------------------------------------------------
declare (strict_types = 1);

namespace mks;

use app\BaseController;
use mks\traits\ResponseTrait;

abstract class BaseController extends BaseController
{
    use ResponseTrait;

    /** @var \app\Request */
    protected $request;
}
