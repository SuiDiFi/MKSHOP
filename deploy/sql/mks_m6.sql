-- ===========================================================================
-- MKSHOP M6 增量（分站经营闭环）— 幂等，可重复执行
-- 覆盖：分站表扩展 / 分站商品级定价 / 域名回填 / 结算索引 / 配置 / 字典 / 权限点
-- 约定：MySQL 8.0；不使用存储过程（SqlImport 按分号切分），用 PREPARE 做幂等 DDL
-- 注意：升级执行后需清理缓存（含表字段缓存），否则新列可能不生效
-- ===========================================================================
SET NAMES utf8mb4;

-- ---------------------------------------------------------------------------
-- 1. mks_vip_site 扩展列
-- ---------------------------------------------------------------------------
SET @ddl = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'mks_vip_site' AND COLUMN_NAME = 'pid') = 0,
  'ALTER TABLE `mks_vip_site` ADD COLUMN `pid` int NOT NULL DEFAULT 0 COMMENT ''上级分站ID（M6 单层预留，恒为0）'' AFTER `uid`',
  'SELECT 1');
PREPARE mks_stmt FROM @ddl;
EXECUTE mks_stmt;
DEALLOCATE PREPARE mks_stmt;

SET @ddl = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'mks_vip_site' AND COLUMN_NAME = 'path_prefix') = 0,
  'ALTER TABLE `mks_vip_site` ADD COLUMN `path_prefix` varchar(32) NOT NULL DEFAULT '''' COMMENT ''二级目录前缀（如 shop → /shop/）'' AFTER `domain`',
  'SELECT 1');
PREPARE mks_stmt FROM @ddl;
EXECUTE mks_stmt;
DEALLOCATE PREPARE mks_stmt;

SET @ddl = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'mks_vip_site' AND COLUMN_NAME = 'allow_main_goods') = 0,
  'ALTER TABLE `mks_vip_site` ADD COLUMN `allow_main_goods` tinyint(1) NOT NULL DEFAULT 1 COMMENT ''是否展示主站公共商品 1=是 0=否'' AFTER `rate`',
  'SELECT 1');
PREPARE mks_stmt FROM @ddl;
EXECUTE mks_stmt;
DEALLOCATE PREPARE mks_stmt;

SET @ddl = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'mks_vip_site' AND COLUMN_NAME = 'settle_minutes') = 0,
  'ALTER TABLE `mks_vip_site` ADD COLUMN `settle_minutes` int NOT NULL DEFAULT 0 COMMENT ''抽成冻结分钟（0=使用全局 order_vip_sure_time）'' AFTER `share_rate`',
  'SELECT 1');
PREPARE mks_stmt FROM @ddl;
EXECUTE mks_stmt;
DEALLOCATE PREPARE mks_stmt;

SET @ddl = IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'mks_vip_site' AND INDEX_NAME = 'idx_path_prefix') = 0,
  'ALTER TABLE `mks_vip_site` ADD KEY `idx_path_prefix` (`path_prefix`)',
  'SELECT 1');
PREPARE mks_stmt FROM @ddl;
EXECUTE mks_stmt;
DEALLOCATE PREPARE mks_stmt;

-- ---------------------------------------------------------------------------
-- 2. mks_vip_goods_price — 分站商品级定价（三级定价最高优先级）
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `mks_vip_goods_price` (
  `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT '主键',
  `vip_id` int NOT NULL DEFAULT 0 COMMENT '分站ID → vip_site.id',
  `goods_id` int NOT NULL DEFAULT 0 COMMENT '商品ID → goods.id',
  `mode` tinyint(1) NOT NULL DEFAULT 1 COMMENT '定价方式 1=比例加价(%) 2=固定加价(元) 3=固定售价(元)',
  `value` decimal(16,4) NOT NULL DEFAULT 0.0000 COMMENT '定价值',
  `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1=启用 0=停用',
  `create_time` int NOT NULL DEFAULT 0 COMMENT '创建时间',
  `update_time` int NOT NULL DEFAULT 0 COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_vip_goods` (`vip_id`,`goods_id`),
  KEY `goods_id` (`goods_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='分站商品级定价（覆盖分站全局比例）';

-- ---------------------------------------------------------------------------
-- 3. 结算表索引（冻结到期扫描）
-- ---------------------------------------------------------------------------
SET @ddl = IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'mks_order_vip' AND INDEX_NAME = 'idx_sure_frozen') = 0,
  'ALTER TABLE `mks_order_vip` ADD KEY `idx_sure_frozen` (`sure_status`,`frozen_time`)',
  'SELECT 1');
PREPARE mks_stmt FROM @ddl;
EXECUTE mks_stmt;
DEALLOCATE PREPARE mks_stmt;

SET @ddl = IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'mks_order_vip' AND INDEX_NAME = 'idx_order') = 0,
  'ALTER TABLE `mks_order_vip` ADD KEY `idx_order` (`order_id`)',
  'SELECT 1');
PREPARE mks_stmt FROM @ddl;
EXECUTE mks_stmt;
DEALLOCATE PREPARE mks_stmt;

SET @ddl = IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'mks_order_supply' AND INDEX_NAME = 'idx_sure_frozen') = 0,
  'ALTER TABLE `mks_order_supply` ADD KEY `idx_sure_frozen` (`sure_status`,`frozen_time`)',
  'SELECT 1');
PREPARE mks_stmt FROM @ddl;
EXECUTE mks_stmt;
DEALLOCATE PREPARE mks_stmt;

SET @ddl = IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'mks_order_supply' AND INDEX_NAME = 'idx_order') = 0,
  'ALTER TABLE `mks_order_supply` ADD KEY `idx_order` (`order_id`)',
  'SELECT 1');
PREPARE mks_stmt FROM @ddl;
EXECUTE mks_stmt;
DEALLOCATE PREPARE mks_stmt;

-- ---------------------------------------------------------------------------
-- 4. 域名权威表回填：vip_site.domain → sys_url（D2）
-- ---------------------------------------------------------------------------
INSERT INTO `mks_sys_url` (`vip_id`,`type`,`url`,`is_main`,`template`,`template_pc`,`create_time`,`update_time`)
SELECT v.`id`, 2, v.`domain`, 0, 'default', 'default', UNIX_TIMESTAMP(), UNIX_TIMESTAMP()
FROM `mks_vip_site` v
WHERE v.`domain` <> '' AND v.`delete_time` = 0
  AND NOT EXISTS (SELECT 1 FROM `mks_sys_url` u WHERE u.`url` = v.`domain`);

INSERT INTO `mks_sys_url` (`vip_id`,`type`,`url`,`is_main`,`template`,`template_pc`,`create_time`,`update_time`)
SELECT 0, 1, '', 1, 'default', 'default', UNIX_TIMESTAMP(), UNIX_TIMESTAMP()
WHERE NOT EXISTS (SELECT 1 FROM `mks_sys_url` WHERE `is_main` = 1);

-- ---------------------------------------------------------------------------
-- 5. 配置种子（结算冻结时长 / 分站默认值）
-- ---------------------------------------------------------------------------
INSERT INTO `mks_config` (`vip_id`,`name`,`info`,`value`,`type`,`input_type`,`group`,`parameter`,`upload_type`,`required`,`width`,`desc`,`high`,`sort`,`props`,`status`,`delete_time`,`son_names`)
SELECT 0,'order_vip_sure_time','分站抽成冻结时长(分钟)','1440','number','number','vip','',1,'',0,'订单成功后分站抽成先入冻结佣金，到期自动解冻',0,100,NULL,1,0,NULL
WHERE NOT EXISTS (SELECT 1 FROM `mks_config` WHERE `name` = 'order_vip_sure_time' AND `vip_id` = 0);

INSERT INTO `mks_config` (`vip_id`,`name`,`info`,`value`,`type`,`input_type`,`group`,`parameter`,`upload_type`,`required`,`width`,`desc`,`high`,`sort`,`props`,`status`,`delete_time`,`son_names`)
SELECT 0,'order_supply_sure_time','供货货款冻结时长(分钟)','2880','number','number','merchant','',1,'',0,'订单成功后供货货款先入冻结，到期自动解冻',0,100,NULL,1,0,NULL
WHERE NOT EXISTS (SELECT 1 FROM `mks_config` WHERE `name` = 'order_supply_sure_time' AND `vip_id` = 0);

INSERT INTO `mks_config` (`vip_id`,`name`,`info`,`value`,`type`,`input_type`,`group`,`parameter`,`upload_type`,`required`,`width`,`desc`,`high`,`sort`,`props`,`status`,`delete_time`,`son_names`)
SELECT 0,'vip_default_rate','分站默认加价率(%)','0','number','number','vip','',1,'',0,'新建分站的默认加价率',0,100,NULL,1,0,NULL
WHERE NOT EXISTS (SELECT 1 FROM `mks_config` WHERE `name` = 'vip_default_rate' AND `vip_id` = 0);

INSERT INTO `mks_config` (`vip_id`,`name`,`info`,`value`,`type`,`input_type`,`group`,`parameter`,`upload_type`,`required`,`width`,`desc`,`high`,`sort`,`props`,`status`,`delete_time`,`son_names`)
SELECT 0,'vip_default_share_rate','平台默认抽成比例(%)','0','number','number','vip','',1,'',0,'按分站毛利计算；0=毛利全归分站',0,100,NULL,1,0,NULL
WHERE NOT EXISTS (SELECT 1 FROM `mks_config` WHERE `name` = 'vip_default_share_rate' AND `vip_id` = 0);

INSERT INTO `mks_config` (`vip_id`,`name`,`info`,`value`,`type`,`input_type`,`group`,`parameter`,`upload_type`,`required`,`width`,`desc`,`high`,`sort`,`props`,`status`,`delete_time`,`son_names`)
SELECT 0,'vip_allow_main_goods','分站默认展示主站商品','1','switch','switch','vip','',1,'',0,'新分站默认是否展示主站公共商品',0,100,NULL,1,0,NULL
WHERE NOT EXISTS (SELECT 1 FROM `mks_config` WHERE `name` = 'vip_allow_main_goods' AND `vip_id` = 0);

-- ---------------------------------------------------------------------------
-- 6. 字典种子
-- ---------------------------------------------------------------------------
REPLACE INTO `mks_sys_dict` (`dict_type`,`code`,`label`,`sort`) VALUES
  ('vip_price_mode','1','比例加价',1),('vip_price_mode','2','固定加价',2),('vip_price_mode','3','固定售价',3),
  ('vip_sure_status','1','待结算',1),('vip_sure_status','2','已结算',2),('vip_sure_status','3','已退款',3),('vip_sure_status','4','部分退款',4);

-- ---------------------------------------------------------------------------
-- 7. 后台接口权限点（默认拒绝，必须登记）
-- ---------------------------------------------------------------------------
INSERT IGNORE INTO `mks_admin_router` (`parentId`,`title`,`sort`,`name`,`auth_type`,`api_url`,`methods`,`delete_time`) VALUES
  (0,'分站详情',100,'vip_read',2,'vip/read','GET',0),
  (0,'分站商品定价列表',100,'vip_goods_price_index',2,'vip/goods_price_index','GET',0),
  (0,'分站商品定价保存',100,'vip_goods_price_save',2,'vip/goods_price_save','POST',0),
  (0,'分站商品定价清除',100,'vip_goods_price_clear',2,'vip/goods_price_clear','POST',0),
  (0,'分站订单列表',100,'vip_order_index',2,'vip/order_index','GET',0),
  (0,'分站结算列表',100,'vip_settlement_index',2,'vip/settlement_index','GET',0),
  (0,'分站结算解冻',100,'vip_settlement_settle',2,'vip/settlement_settle','POST',0),
  (0,'分站配置读取',100,'vip_config_read',2,'vip/config_read','GET',0),
  (0,'分站配置保存',100,'vip_config_save',2,'vip/config_save','POST',0),
  (0,'分站域名列表',100,'vip_domain_index',2,'vip/domain_index','GET',0),
  (0,'分站域名保存',100,'vip_domain_save',2,'vip/domain_save','POST',0),
  (0,'分站域名删除',100,'vip_domain_delete',2,'vip/domain_delete','POST',0);
