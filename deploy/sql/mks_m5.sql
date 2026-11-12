-- ============================================================================
-- MKSHOP · M5 三端前台 + DIY + 客服（增量）
-- 幂等可重复执行：表 IF NOT EXISTS；种子/菜单/权限按唯一键防重。
-- 依据：_analysis 00-07 文档与 R 路由清单（front/kefu、manage/kefu）
-- 注：SET NAMES 必须显式声明，导入需 --default-character-set=utf8mb4
-- ============================================================================
SET NAMES utf8mb4;

-- 1. 前台内容（公告/帮助文章；type 1=公告 2=帮助中心）
CREATE TABLE IF NOT EXISTS `mks_cms_article` (
  `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT '文章ID',
  `type` tinyint(1) NOT NULL DEFAULT 1 COMMENT '类型 1=公告 2=帮助',
  `title` varchar(200) NOT NULL DEFAULT '' COMMENT '标题',
  `summary` varchar(500) NOT NULL DEFAULT '' COMMENT '摘要',
  `content` longtext DEFAULT NULL COMMENT '正文 HTML',
  `is_top` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否置顶',
  `sort` int NOT NULL DEFAULT 0 COMMENT '排序（越大越前）',
  `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '状态 1=显示 0=隐藏',
  `create_time` int NOT NULL DEFAULT 0 COMMENT '创建时间',
  `update_time` int NOT NULL DEFAULT 0 COMMENT '更新时间',
  `delete_time` int NOT NULL DEFAULT 0 COMMENT '软删除',
  PRIMARY KEY (`id`),
  KEY `idx_type_status` (`type`,`status`,`delete_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='前台内容（公告/帮助中心）';

-- 2. 客服会话（会员一侧；同一会员仅一个进行中会话）
CREATE TABLE IF NOT EXISTS `mks_kefu_session` (
  `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT '会话ID',
  `uid` int NOT NULL DEFAULT 0 COMMENT '会员ID（0=游客）',
  `nickname` varchar(100) NOT NULL DEFAULT '' COMMENT '会员昵称快照',
  `avatar` varchar(255) NOT NULL DEFAULT '' COMMENT '头像',
  `source` varchar(20) NOT NULL DEFAULT 'pc' COMMENT '来源端 pc/h5/xcx',
  `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '状态 1=进行中 2=已关闭',
  `admin_id` int NOT NULL DEFAULT 0 COMMENT '接待客服ID',
  `admin_name` varchar(50) NOT NULL DEFAULT '' COMMENT '接待客服名',
  `last_msg` varchar(500) NOT NULL DEFAULT '' COMMENT '最后一条消息摘要',
  `last_time` int NOT NULL DEFAULT 0 COMMENT '最后消息时间',
  `unread_admin` int NOT NULL DEFAULT 0 COMMENT '客服未读数',
  `unread_member` int NOT NULL DEFAULT 0 COMMENT '会员未读数',
  `create_time` int NOT NULL DEFAULT 0 COMMENT '创建时间',
  `update_time` int NOT NULL DEFAULT 0 COMMENT '更新时间',
  `delete_time` int NOT NULL DEFAULT 0 COMMENT '软删除',
  PRIMARY KEY (`id`),
  KEY `idx_uid` (`uid`,`status`),
  KEY `idx_status_time` (`status`,`last_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='客服会话';

-- 3. 客服消息（轮询拉取；is_read 由对应端读取后置 1）
CREATE TABLE IF NOT EXISTS `mks_kefu_message` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT '消息ID',
  `session_id` int NOT NULL DEFAULT 0 COMMENT '会话ID',
  `from_type` tinyint(1) NOT NULL DEFAULT 1 COMMENT '发送方 1=会员 2=客服 3=系统',
  `uid` int NOT NULL DEFAULT 0 COMMENT '会员ID',
  `admin_id` int NOT NULL DEFAULT 0 COMMENT '客服ID',
  `content` text DEFAULT NULL COMMENT '消息内容',
  `msg_type` tinyint(1) NOT NULL DEFAULT 1 COMMENT '消息类型 1=文本',
  `is_read` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否已读',
  `create_time` int NOT NULL DEFAULT 0 COMMENT '发送时间',
  PRIMARY KEY (`id`),
  KEY `idx_session` (`session_id`,`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='客服消息';

-- 4. 公告/帮助种子（按标题防重）
INSERT INTO `mks_cms_article` (`type`,`title`,`summary`,`content`,`is_top`,`sort`,`status`,`create_time`,`update_time`,`delete_time`)
SELECT 1, 'MKSHOP 商城正式上线', '欢迎使用 MKSHOP 自动发卡商城', '<p>MKSHOP 商城现已上线，支持自动发卡、虚拟充值与会员分销。</p><p>如有问题请联系在线客服。</p>', 1, 100, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 0
WHERE NOT EXISTS (SELECT 1 FROM (SELECT 1 FROM `mks_cms_article` WHERE `title` = 'MKSHOP 商城正式上线' AND `delete_time` = 0 LIMIT 1) x);

INSERT INTO `mks_cms_article` (`type`,`title`,`summary`,`content`,`is_top`,`sort`,`status`,`create_time`,`update_time`,`delete_time`)
SELECT 1, '关于订单自动发货的说明', '支付成功后系统自动发货', '<p>支付成功后，系统将立即从卡库提取卡密并展示在订单详情中。</p><p>若遇库存不足，系统会自动补单或退款，请留意订单状态。</p>', 0, 90, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 0
WHERE NOT EXISTS (SELECT 1 FROM (SELECT 1 FROM `mks_cms_article` WHERE `title` = '关于订单自动发货的说明' AND `delete_time` = 0 LIMIT 1) x);

INSERT INTO `mks_cms_article` (`type`,`title`,`summary`,`content`,`is_top`,`sort`,`status`,`create_time`,`update_time`,`delete_time`)
SELECT 2, '如何下单购买商品？', '浏览商品 → 下单 → 支付 → 提卡', '<p>1. 在首页或分类页选择商品进入详情；</p><p>2. 选择数量后点击「立即购买」；</p><p>3. 使用账户余额完成支付；</p><p>4. 支付成功后可在「我的订单」查看卡密。</p>', 1, 100, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 0
WHERE NOT EXISTS (SELECT 1 FROM (SELECT 1 FROM `mks_cms_article` WHERE `title` = '如何下单购买商品？' AND `delete_time` = 0 LIMIT 1) x);

INSERT INTO `mks_cms_article` (`type`,`title`,`summary`,`content`,`is_top`,`sort`,`status`,`create_time`,`update_time`,`delete_time`)
SELECT 2, '支付方式与到账时间', '当前支持余额支付', '<p>MVP 阶段支持会员余额支付，余额可通过后台充值或分销佣金获得。</p><p>在线支付通道（支付宝/微信）将在后续版本开放。</p>', 0, 90, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 0
WHERE NOT EXISTS (SELECT 1 FROM (SELECT 1 FROM `mks_cms_article` WHERE `title` = '支付方式与到账时间' AND `delete_time` = 0 LIMIT 1) x);

INSERT INTO `mks_cms_article` (`type`,`title`,`summary`,`content`,`is_top`,`sort`,`status`,`create_time`,`update_time`,`delete_time`)
SELECT 2, '如何申请提现？', '佣金与余额提现流程', '<p>进入「会员中心 → 提现」，绑定收款方式后提交申请，后台审核通过后打款。</p><p>提现手续费按系统配置阶梯计算。</p>', 0, 80, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 0
WHERE NOT EXISTS (SELECT 1 FROM (SELECT 1 FROM `mks_cms_article` WHERE `title` = '如何申请提现？' AND `delete_time` = 0 LIMIT 1) x);

INSERT INTO `mks_cms_article` (`type`,`title`,`summary`,`content`,`is_top`,`sort`,`status`,`create_time`,`update_time`,`delete_time`)
SELECT 2, '订单一直未发货怎么办？', '联系在线客服处理', '<p>若订单长时间停留在「处理中」，可在客服中心发起咨询，客服会协助补单或退款。</p>', 0, 70, 1, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 0
WHERE NOT EXISTS (SELECT 1 FROM (SELECT 1 FROM `mks_cms_article` WHERE `title` = '订单一直未发货怎么办？' AND `delete_time` = 0 LIMIT 1) x);

-- 5. 客服后台菜单（host 可见菜单；seed 已含则跳过）
INSERT INTO `mks_admin_router` (`parentId`,`title`,`sort`,`name`,`path`,`icon`,`component`,`auth_type`,`api_url`,`methods`,`delete_time`)
SELECT 0, '客服', 9995, 'Kefu', '/service', 'customer-service-2-line', 'Layout', 1, '', '', 0
WHERE NOT EXISTS (SELECT 1 FROM (SELECT 1 FROM `mks_admin_router` WHERE `name` = 'Kefu' AND `auth_type` = 1 LIMIT 1) x);

INSERT INTO `mks_admin_router` (`parentId`,`title`,`sort`,`name`,`path`,`icon`,`component`,`auth_type`,`api_url`,`methods`,`delete_time`)
SELECT r.id, '客服工作台', 9999, 'KefuWorkbench', 'workbench', 'chat-3-line', '@/views/kefu/kefuWorkbench', 1, '', '', 0
FROM `mks_admin_router` r
WHERE r.`name` = 'Kefu' AND r.`auth_type` = 1
  AND NOT EXISTS (SELECT 1 FROM (SELECT 1 FROM `mks_admin_router` WHERE `name` = 'KefuWorkbench' AND `auth_type` = 1 LIMIT 1) x)
LIMIT 1;

-- 6. 客服接口权限点（RBAC 默认拒绝：未登记即拒绝）
INSERT INTO `mks_admin_router` (`parentId`,`title`,`sort`,`name`,`auth_type`,`api_url`,`methods`,`delete_time`)
SELECT s.pid, s.title, 100, s.nm, 2, s.api, s.mt, 0
FROM (
  SELECT 0 pid, '客服会话列表' title, 'm5_kefu_index' nm, 'kefu/index' api, 'GET' mt UNION ALL
  SELECT 0, '客服消息', 'm5_kefu_messages', 'kefu/messages', 'GET' UNION ALL
  SELECT 0, '客服回复', 'm5_kefu_reply', 'kefu/reply', 'POST' UNION ALL
  SELECT 0, '客服关闭会话', 'm5_kefu_close', 'kefu/close', 'POST' UNION ALL
  SELECT 0, '前台客服会话', 'm5_kefu_start', 'kefu/start', 'POST'
) s
WHERE NOT EXISTS (
  SELECT 1 FROM `mks_admin_router` t WHERE t.`api_url` = s.api AND t.`delete_time` = 0
);

-- 7. 字典：客服会话状态
REPLACE INTO `mks_sys_dict` (`dict_type`,`code`,`label`,`color`,`sort`,`status`,`vip_id`) VALUES
  ('kefu_status','1','进行中','success',1,1,0),
  ('kefu_status','2','已关闭','info',2,1,0),
  ('cms_article_type','1','公告','warning',1,1,0),
  ('cms_article_type','2','帮助','success',2,1,0);
