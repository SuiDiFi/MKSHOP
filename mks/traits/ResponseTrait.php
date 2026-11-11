<?php
// +----------------------------------------------------------------------
// | MKSHOP 统一响应 Trait（code/msg/data/time；HTTP 状态与业务码分离）
// +----------------------------------------------------------------------
declare (strict_types = 1);

namespace mks\traits;

use think\Response\Json;

trait ResponseTrait
{
    /**
     * 成功响应
     */
    protected function ok(mixed $data = [], string $msg = 'ok'): Json
    {
        return json_ok($data, $msg);
    }

    /**
     * 失败响应（业务错误，HTTP 200 + code!=0）
     */
    protected function fail(string $msg = '操作失败', int $code = 1, mixed $data = []): Json
    {
        return json_fail($msg, 200, $code, $data);
    }

    /**
     * 抛出业务异常（等价 fail 但走异常流，可在 Logic 层使用）
     */
    protected function abort(string $msg, int $code = 1): never
    {
        throw_fail($msg, $code);
    }
}
