<?php
// +----------------------------------------------------------------------
// | MKSHOP 全局异常处理（统一响应：code/msg/data/time）
// +----------------------------------------------------------------------
declare (strict_types = 1);

namespace app;

use think\App;
use think\exception\Handle;
use think\exception\HttpException;
use think\exception\HttpResponseException;
use think\exception\ValidateException;
use think\Response;
use think\Response\Json;
use Throwable;

class ExceptionHandle extends Handle
{
    /** @var App */
    protected $app;

    /**
     * Render an exception into an HTTP response.
     */
    public function render($request, Throwable $e): Response
    {
        // 调试模式交还框架（Trace 页面）
        if ($this->app->isDebug()) {
            return parent::render($request, $e);
        }

        // 参数校验异常：取第一条错误
        if ($e instanceof ValidateException) {
            return json_fail($e->getError(), 422);
        }

        // 业务异常（throw_fail）：统一 JSON，HTTP 200 + 业务码（对齐 fail() 语义）
        if ($e instanceof \mks\exception\BizException) {
            return json_fail($e->getMessage(), 200, $e->getCode() ?: 1);
        }

        // HTTP 异常
        if ($e instanceof HttpException) {
            $code = $e->getStatusCode();
            $msg  = $code === 404 ? '接口不存在' : $e->getMessage();
            if ($request->isJson() || $request->isAjax() || !$request->isGet()) {
                return json_fail($msg, $code);
            }
            return response($msg, $code);
        }

        // 其他业务/系统异常：记录日志，返回统一错误
        $this->reportException($e);
        return json_fail(config('app.show_error_msg') ? $e->getMessage() : '系统繁忙，请稍后再试', 500);
    }

    protected function reportException(Throwable $e): void
    {
        try {
            \think\facade\Log::error(sprintf(
                '[%s] %s @ %s:%d%s',
                get_class($e),
                $e->getMessage(),
                $e->getFile(),
                $e->getLine(),
                PHP_EOL . $e->getTraceAsString()
            ));
        } catch (Throwable $ignore) {
            // 日志通道不可用时静默
        }
    }
}
