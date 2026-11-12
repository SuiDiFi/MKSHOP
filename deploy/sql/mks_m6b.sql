-- ===========================================================================
-- MKSHOP M6 增量 B（商户打标与供货结算闭环）— 幂等，可重复执行
-- 覆盖：商户表 / 入驻申请表 / 结算唯一键 / 配置 / 字典 / 权限点 / 菜单
-- 约定：MySQL 8.0；不使用存储过程（SqlImport 按分号切分），用 PREPARE 做幂等 DDL
-- 注意：升级执行后需清理缓存（含表字段缓存），否则新表新列可能不生效
-- ===========================================================================
SET NAMES utf8mb4;

-- ---------------------------------------------------------------------------
-- 1. mks_merchant — 商户/供货商店铺资料（member.merchant_id 引用）
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `mks_merchant` (
  `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT '商户ID',
  `uid` int NOT NULL DEFAULT 0 COMMENT '会员ID（唯一）→ member.id',
  `name` varchar(100) NOT NULL DEFAULT '' COMMENT '店铺名',
  `logo` varchar(255) NOT NULL DEFAULT '' COMMENT '店铺Logo',
  `intro` varchar(1000) NOT NULL DEFAULT '' COMMENT '店铺简介',
  `contact` varchar(50) NOT NULL DEFAULT '' COMMENT '联系人',
  `mobile` varchar(30) NOT NULL DEFAULT '' COMMENT '联系电话',
  `settlement_type` tinyint(1) NOT NULL DEFAULT 1 COMMENT '结算方式 1=按成本结算 2=按销售价结算（MVP 仅 1）',
  `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '状态 1=正常 0=停用',
  `create_time` int NOT NULL DEFAULT 0 COMMENT '创建时间',
  `update_time` int NOT NULL DEFAULT 0 COMMENT '更新时间',
  `delete_time` int NOT NULL DEFAULT 0 COMMENT '软删除时间（0=未删除）',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_uid` (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='商户/供货商店铺资料';

-- ---------------------------------------------------------------------------
-- 2. mks_merchant_apply — 商户入驻申请
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `mks_merchant_apply` (
  `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT '申请ID',
  `uid` int NOT NULL DEFAULT 0 COMMENT '申请人会员ID → member.id',
  `merchant_name` varchar(100) NOT NULL DEFAULT '' COMMENT '店铺名',
  `contact` varchar(50) NOT NULL DEFAULT '' COMMENT '联系人',
  `mobile` varchar(30) NOT NULL DEFAULT '' COMMENT '联系电话',
  `intro` varchar(1000) NOT NULL DEFAULT '' COMMENT '店铺简介',
  `license_img` varchar(500) NOT NULL DEFAULT '' COMMENT '营业执照/资质图片',
  `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '状态 1=待审核 2=通过 3=拒绝',
  `audit_admin_id` int NOT NULL DEFAULT 0 COMMENT '审核管理员ID',
  `audit_time` int NOT NULL DEFAULT 0 COMMENT '审核时间',
  `audit_mark` varchar(500) NOT NULL DEFAULT '' COMMENT '审核备注',
  `create_time` int NOT NULL DEFAULT 0 COMMENT '申请时间',
  `update_time` int NOT NULL DEFAULT 0 COMMENT '更新时间',
  PRIMARY KEY (`id`),
  KEY `idx_uid_status` (`uid`,`status`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='商户入驻申请';

-- ---------------------------------------------------------------------------
-- 3. 结算唯一键（防多实例重复入账；先清理历史重复保留最小 id）
--    注意：唯一键与「单层分站」绑定，将来多级分站需改为 UNIQUE(order_id, vip_id)
-- ---------------------------------------------------------------------------
DELETE t1 FROM `mks_order_vip` t1
JOIN `mks_order_vip` t2 ON t1.`order_id` = t2.`order_id` AND t1.`id` > t2.`id`;

SET @ddl = IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'mks_order_vip' AND INDEX_NAME = 'uk_order') = 0,
  'ALTER TABLE `mks_order_vip` ADD UNIQUE KEY `uk_order` (`order_id`)',
  'SELECT 1');
PREPARE mks_stmt FROM @ddl;
EXECUTE mks_stmt;
DEALLOCATE PREPARE mks_stmt;

DELETE t1 FROM `mks_order_supply` t1
JOIN `mks_order_supply` t2 ON t1.`order_id` = t2.`order_id` AND t1.`id` > t2.`id`;

SET @ddl = IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'mks_order_supply' AND INDEX_NAME = 'uk_order') = 0,
  'ALTER TABLE `mks_order_supply` ADD UNIQUE KEY `uk_order` (`order_id`)',
  'SELECT 1');
PREPARE mks_stmt FROM @ddl;
EXECUTE mks_stmt;
DEALLOCATE PREPARE mks_stmt;

-- ---------------------------------------------------------------------------
-- 4. 配置种子（入驻开关）
-- ---------------------------------------------------------------------------
INSERT INTO `mks_config` (`vip_id`,`name`,`info`,`value`,`type`,`input_type`,`group`,`parameter`,`upload_type`,`required`,`width`,`desc`,`high`,`sort`,`props`,`status`,`delete_time`,`son_names`)
SELECT 0,'merchant_apply_enable','开启商户入驻申请','1','switch','switch','merchant','',1,'',0,'关闭后前台不再显示入驻申请入口',0,100,NULL,1,0,NULL
WHERE NOT EXISTS (SELECT 1 FROM `mks_config` WHERE `name` = 'merchant_apply_enable' AND `vip_id` = 0);

-- ---------------------------------------------------------------------------
-- 5. 字典种子
-- ---------------------------------------------------------------------------
REPLACE INTO `mks_sys_dict` (`dict_type`,`code`,`label`,`sort`) VALUES
  ('merchant_status','1','正常',1),('merchant_status','0','停用',2),
  ('merchant_apply_status','1','待审核',1),('merchant_apply_status','2','已通过',2),('merchant_apply_status','3','已拒绝',3),
  ('supply_sure_status','1','待结算',1),('supply_sure_status','2','已结算',2),('supply_sure_status','3','已退款',3),('supply_sure_status','4','部分退款',4);

-- ---------------------------------------------------------------------------
-- 6. 后台接口权限点（默认拒绝，必须登记）
-- ---------------------------------------------------------------------------
INSERT IGNORE INTO `mks_admin_router` (`parentId`,`title`,`sort`,`name`,`auth_type`,`api_url`,`methods`,`delete_time`) VALUES
  (0,'商户列表',100,'merchant_index',2,'merchant/index','GET',0),
  (0,'商户详情',100,'merchant_read',2,'merchant/read','GET',0),
  (0,'商户创建',100,'merchant_create',2,'merchant/create','POST',0),
  (0,'商户保存',100,'merchant_save',2,'merchant/save','POST',0),
  (0,'商户启停',100,'merchant_status',2,'merchant/status','POST',0),
  (0,'商户会员选项',100,'merchant_member_options',2,'merchant/member_options','GET',0),
  (0,'商户货款流水',100,'merchant_supply_flow',2,'merchant/supply_flow','GET',0),
  (0,'商户结算查询',100,'merchant_settlement_index',2,'merchant/settlement_index','GET',0),
  (0,'入驻申请列表',100,'merchant_apply_index',2,'merchant_apply/index','GET',0),
  (0,'入驻申请详情',100,'merchant_apply_read',2,'merchant_apply/read','GET',0),
  (0,'入驻申请审核',100,'merchant_apply_audit',2,'merchant_apply/audit','POST',0);

-- ---------------------------------------------------------------------------
-- 7. 平台后台菜单（挂到会员管理同级；与 mks_extra.sql 写法一致）
-- ---------------------------------------------------------------------------
INSERT INTO `mks_admin_router` (`parentId`,`title`,`sort`,`name`,`path`,`icon`,`component`,`auth_type`,`api_url`,`methods`,`delete_time`)
SELECT r.parentId, '商户管理', 93, 'MksMerchantList', 'merchant', 'shop', '@/views/merchant/merchantList', 1, '', '', 0
FROM `mks_admin_router` r
WHERE r.`name` = 'MemberList' AND r.`auth_type` = 1
  AND NOT EXISTS (SELECT 1 FROM (SELECT 1 FROM `mks_admin_router` WHERE `name` = 'MksMerchantList' LIMIT 1) x)
LIMIT 1;

-- 兜底：MemberList 不存在时挂到 order 菜单同级
INSERT INTO `mks_admin_router` (`parentId`,`title`,`sort`,`name`,`path`,`icon`,`component`,`auth_type`,`api_url`,`methods`,`delete_time`)
SELECT r.parentId, '商户管理', 93, 'MksMerchantList', 'merchant', 'shop', '@/views/merchant/merchantList', 1, '', '', 0
FROM `mks_admin_router` r
WHERE r.`path` = 'order' AND r.`auth_type` = 1
  AND NOT EXISTS (SELECT 1 FROM (SELECT 1 FROM `mks_admin_router` WHERE `name` = 'MksMerchantList' LIMIT 1) x)
LIMIT 1;
