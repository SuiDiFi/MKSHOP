<?php
// +----------------------------------------------------------------------
// | MKSHOP CORS 中间件（多端跨域统一出口）
// +----------------------------------------------------------------------
declare (strict_types = 1);

namespace mks\middleware;

use think\Request;
use think\Response;

class Cors
{
    protected array $cookieDomain;

    protected array $header = [
        'Access-Control-Allow-Credentials' => 'true',
        'Access-Control-Max-Age'           => 1800,
        'Access-Control-Allow-Methods'     => 'GET, POST, PATCH, PUT, DELETE, OPTIONS',
        'Access-Control-Allow-Headers'     => 'Authorization, Content-Type, If-Match, If-Modified-Since, If-None-Match, If-Unmodified-Since, X-Requested-With, Sign, Timestamp, UserId',
    ];

    public function __construct()
    {
        $this->cookieDomain = config('cookie.domain', '');
    }

    /**
     * 允许跨域请求
     */
    public function handle(Request $request, \Closure $next): Response
    {
        $origin = (string) $request->header('origin', '');
        $allow  = $this->matchOrigin($origin);

        if ($origin !== '' && $allow !== '') {
            $this->header['Access-Control-Allow-Origin'] = $allow;
        }

        // 预检请求直接响应
        if (strtoupper((string) $request->method()) === 'OPTIONS') {
            return response()->withHeader($this->header)->code(204);
        }

        $response = $next($request);
        return $response->header($this->header);
    }

    protected function matchOrigin(string $origin): string
    {
        $allowAll = config('cors.allow_all', true);
        return $allowAll ? $origin : '';
    }
}
