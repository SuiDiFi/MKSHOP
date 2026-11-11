<?php
// +----------------------------------------------------------------------
// | MKSHOP 开放接口验签中间件（/api、/dockapi；兼容 v2api sha1 协议）
// 红线修正：签名密钥只存服务端（.env），不再硬编码前端；时间容差 ±300s
// +----------------------------------------------------------------------
declare (strict_types = 1);

namespace mks\middleware;

use mks\util\DockSign;
use think\Request;
use think\Response;

class ApiSign
{
    public function handle(Request $request, \Closure $next): Response
    {
        $sign      = (string) $request->header('sign', '');
        $timestamp = (string) $request->header('timestamp', '');
        $userid    = (string) $request->header('userid', '');

        if ($sign === '' || $timestamp === '' || $userid === '') {
            return json_fail('缺少签名参数（Sign/Timestamp/UserId）', 200, 400);
        }

        // UserId 侧密钥：M0 阶段取站点级 sign_key；M2/M3 换为按对接账号（mks_docking / member API key）取密钥
        $apikey = DockSign::siteKey();
        if ($apikey === '') {
            return json_fail('对接服务未配置（MKS.SIGN_KEY）', 200, 500);
        }

        if (!DockSign::verify((string) $request->getContent(), $sign, $timestamp, $apikey)) {
            return json_fail('签名验证失败', 200, 401);
        }

        $request->apiUserId = $userid;
        return $next($request);
    }
}
