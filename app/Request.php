<?php
// +----------------------------------------------------------------------
// | MKSHOP 请求对象（全局输入过滤）
// +----------------------------------------------------------------------
declare (strict_types = 1);

namespace app;

use think\Request as BaseRequest;

class Request extends BaseRequest
{
    // API 为主的应用不做自动转义（JSON 载荷会被破坏），仅去除首尾空白
    protected $filter = ['trim'];
}
