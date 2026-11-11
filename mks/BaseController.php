<?php
// +----------------------------------------------------------------------
// | MKSHOP 应用基类控制器
// +----------------------------------------------------------------------
declare (strict_types = 1);

namespace mks;

use app\BaseController as AppBaseController;
use mks\traits\ResponseTrait;

abstract class BaseController extends AppBaseController
{
    use ResponseTrait;
}
