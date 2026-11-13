-- ===========================================================================
-- MKSHOP M6 增量 E（小程序上架配置预留）— 幂等，可重复执行
-- 覆盖：miniprogram 配置分组 / 登录方式字典 / 后台「小程序配置」菜单
-- 说明：仅新增配置项与菜单，不新增后台接口；配置读写复用 sys/config_group_form
--      与 sys/save_config_group（权限点已在种子中）。敏感项（AppSecret、
--      APIv3 密钥、证书路径）只存后台，不下发给小程序端。
-- ===========================================================================
SET NAMES utf8mb4;

-- ---------------------------------------------------------------------------
-- 1. 配置种子（group = miniprogram）
--    唯一键 (name, vip_id) 保证 INSERT IGNORE 幂等
-- ---------------------------------------------------------------------------
INSERT IGNORE INTO `mks_config`
(`vip_id`,`name`,`info`,`value`,`type`,`input_type`,`group`,`parameter`,`upload_type`,`required`,`width`,`desc`,`high`,`sort`,`props`,`status`,`delete_time`,`son_names`) VALUES
(0,'mp_appid','小程序 AppID','','text','input','miniprogram','',1,'',0,'微信公众平台 → 开发管理 → 开发设置；构建时注入小程序，不在页面代码硬编码',0,10,NULL,1,0,NULL),
(0,'mp_appsecret','小程序 AppSecret','','text','input','miniprogram','',1,'',0,'仅服务端使用（微信授权登录换取 openid）；不会下发给小程序端，注意保密',0,20,NULL,1,0,NULL),
(0,'mp_request_domain','服务器请求域名','','text','input','miniprogram','',1,'',0,'小程序 request/uploadFile/downloadFile 合法域名，须 HTTPS，例如 https://mksup.top',0,30,NULL,1,0,NULL),
(0,'mp_business_domain','业务域名白名单','','textarea','textarea','miniprogram','',1,'',0,'微信公众平台「业务域名」（web-view 承载客服/协议页用），多个用逗号或换行分隔；需上传校验文件',0,40,NULL,1,0,NULL),
(0,'mp_pay_mchid','微信支付商户号','','text','input','miniprogram','',1,'',0,'微信支付商户平台 → 账户中心 → 商户信息；未开启微信支付可留空',0,50,NULL,1,0,NULL),
(0,'mp_pay_apiv3_key','微信支付 APIv3 密钥','','text','input','miniprogram','',1,'',0,'微信支付商户平台 → 账户中心 → API 安全；仅服务端使用，勿下发客户端',0,60,NULL,1,0,NULL),
(0,'mp_pay_cert_path','微信支付证书路径','','text','input','miniprogram','',1,'',0,'服务器上商户 API 证书的绝对路径，例如 /opt/mkshop/cert/apiclient_cert.pem（目录需挂载进 PHP 容器）',0,70,NULL,1,0,NULL),
(0,'mp_pay_cert_serial','微信支付证书序列号','','text','input','miniprogram','',1,'',0,'商户 API 证书序列号（微信支付平台可查）；与证书路径配套使用',0,80,NULL,1,0,NULL),
(0,'mp_login_mode','登录方式','account','radio','radio','miniprogram','[{"label":"账号密码","value":"account"},{"label":"微信授权","value":"wechat"},{"label":"两者都支持","value":"both"}]',1,'',0,'默认账号密码；微信授权需同时开启「启用微信授权登录」并配置 AppID/AppSecret',0,90,NULL,1,0,NULL),
(0,'mp_wechat_login_enable','启用微信授权登录','0','switch','switch','miniprogram','',1,'',0,'默认关闭；关闭时小程序仅展示账号密码登录，接口返回「未启用」',0,100,NULL,1,0,NULL),
(0,'mp_wechat_pay_enable','启用微信支付','0','switch','switch','miniprogram','',1,'',0,'默认关闭；关闭时继续使用余额支付，微信支付接口返回「未启用」',0,110,NULL,1,0,NULL),
(0,'mp_subscribe_templates','订阅消息模板 ID','','textarea','textarea','miniprogram','',1,'',0,'一行一个，格式 场景=模板ID，例如 order_paid=xxxxxxxx；未配置则不发送订阅消息',0,120,NULL,1,0,NULL),
(0,'mp_kefu_url','客服链接','','text','input','miniprogram','',1,'',0,'小程序客服页或企业微信客服链接；留空则使用站内客服',0,130,NULL,1,0,NULL),
(0,'mp_privacy_url','隐私协议链接','','text','input','miniprogram','',1,'',0,'须为 https 且域名已加入「业务域名」；微信提审要求填写隐私保护指引',0,140,NULL,1,0,NULL),
(0,'mp_agreement_url','用户协议链接','','text','input','miniprogram','',1,'',0,'用户注册/交易协议页面，建议 https 且与隐私协议同域',0,150,NULL,1,0,NULL),
(0,'mp_release_enable','小程序上架开关','1','switch','switch','miniprogram','',1,'',0,'默认开启（不影响现有账号密码+余额支付）；关闭时小程序端提示「暂未上架」，便于提审期间冻结入口',0,160,NULL,1,0,NULL),
(0,'mp_audit_status','审核状态备注','','textarea','textarea','miniprogram','',1,'',0,'记录提审版本号、提交时间、审核结果、驳回原因与整改记录（仅后台可见）',0,170,NULL,1,0,NULL);

-- ---------------------------------------------------------------------------
-- 2. 字典种子（登录方式下拉，供后台/其他模块复用）
-- ---------------------------------------------------------------------------
REPLACE INTO `mks_sys_dict` (`dict_type`,`code`,`label`,`sort`) VALUES
  ('mp_login_mode','account','账号密码',1),
  ('mp_login_mode','wechat','微信授权',2),
  ('mp_login_mode','both','两者都支持',3);

-- ---------------------------------------------------------------------------
-- 3. 平台后台菜单（挂到「设置 → 基础设置」分组，与通用/安全设置同级）
-- ---------------------------------------------------------------------------
INSERT INTO `mks_admin_router` (`parentId`,`title`,`sort`,`name`,`path`,`icon`,`component`,`auth_type`,`api_url`,`methods`,`delete_time`)
SELECT 56, '小程序配置', 87, 'MksMiniprogramConfig', 'miniprogram', 'cellphone', '@/views/setting/miniprogram', 1, '', '', 0
WHERE EXISTS (SELECT 1 FROM (SELECT 1 FROM `mks_admin_router` WHERE `id` = 56 AND `auth_type` = 1 LIMIT 1) p)
  AND NOT EXISTS (SELECT 1 FROM (SELECT 1 FROM `mks_admin_router` WHERE `name` = 'MksMiniprogramConfig' LIMIT 1) x);

-- 兜底：基础设置分组不存在时挂到顶级
INSERT INTO `mks_admin_router` (`parentId`,`title`,`sort`,`name`,`path`,`icon`,`component`,`auth_type`,`api_url`,`methods`,`delete_time`)
SELECT 0, '小程序配置', 87, 'MksMiniprogramConfig', 'miniprogram', 'cellphone', '@/views/setting/miniprogram', 1, '', '', 0
WHERE NOT EXISTS (SELECT 1 FROM (SELECT 1 FROM `mks_admin_router` WHERE `name` = 'MksMiniprogramConfig' LIMIT 1) x);
