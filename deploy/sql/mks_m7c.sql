-- ===========================================================================
-- MKSHOP M7 增量 C（KASUSHOU 1:1 对齐 · P1-2 定价模板）— 幂等，可重复执行
-- 覆盖：定价模板接口权限点 / 可见菜单（挂商品(6)下）
-- 表 mks_goods_ready_price 已存在（M0 骨架），无需建表
-- ===========================================================================
SET NAMES utf8mb4;

-- 0. 补列：mks_goods_ready_price 原种子表无时间戳列，BaseModel autoWriteTimestamp 需要它们
SET @ddl = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'mks_goods_ready_price' AND COLUMN_NAME = 'create_time') = 0,
  'ALTER TABLE `mks_goods_ready_price` ADD COLUMN `create_time` int NOT NULL DEFAULT 0 COMMENT ''创建时间'' AFTER `sort`',
  'SELECT 1');
PREPARE mks_stmt FROM @ddl;
EXECUTE mks_stmt;
DEALLOCATE PREPARE mks_stmt;

SET @ddl = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'mks_goods_ready_price' AND COLUMN_NAME = 'update_time') = 0,
  'ALTER TABLE `mks_goods_ready_price` ADD COLUMN `update_time` int NOT NULL DEFAULT 0 COMMENT ''更新时间'' AFTER `create_time`',
  'SELECT 1');
PREPARE mks_stmt FROM @ddl;
EXECUTE mks_stmt;
DEALLOCATE PREPARE mks_stmt;

-- 0.1 恢复默认预置定价（表被清空/缺省时回填种子行）
INSERT INTO `mks_goods_ready_price` (`id`,`vip_id`,`goods_id`,`goods_sku_id`,`uid`,`type`,`start_price`,`end_price`,`plan_id`,`cha_price`,`status`,`price_arr`,`is_default`,`name`,`level_md5`,`sort`,`create_time`,`update_time`)
SELECT 1,0,0,0,0,1,0.00,10000.00,1,10.00,1,'{"1":1,"2":2,"3":3}',0,'默认预置定价','',100,UNIX_TIMESTAMP(),UNIX_TIMESTAMP()
WHERE NOT EXISTS (SELECT 1 FROM (SELECT 1 FROM `mks_goods_ready_price` WHERE `id` = 1 LIMIT 1) x);

-- 1. 接口权限点
INSERT IGNORE INTO `mks_admin_router` (`parentId`,`title`,`sort`,`name`,`auth_type`,`api_url`,`methods`,`delete_time`) VALUES
  (0, '定价模板列表',   100, 'price_ready_index',  2, 'price_ready/index',  'GET',  0),
  (0, '定价模板创建',   100, 'price_ready_create', 2, 'price_ready/create', 'POST', 0),
  (0, '定价模板更新',   100, 'price_ready_save',   2, 'price_ready/save',   'POST', 0),
  (0, '定价模板删除',   100, 'price_ready_delete', 2, 'price_ready/delete', 'POST', 0),
  (0, '定价模板类型',   100, 'price_ready_type',   2, 'price_ready/type_list', 'GET', 0);

-- 2. 可见菜单（挂商品(6)下）
INSERT INTO `mks_admin_router` (`parentId`,`title`,`sort`,`name`,`path`,`icon`,`component`,`auth_type`,`api_url`,`methods`,`delete_time`)
SELECT 6, '定价模板', 96, 'MksPriceReady', 'pricereadydata', 'money-euro-box-line', '@/views/goods/priceReady', 1, '', '', 0
WHERE EXISTS (SELECT 1 FROM (SELECT 1 FROM `mks_admin_router` WHERE `id` = 6 LIMIT 1) p)
  AND NOT EXISTS (SELECT 1 FROM (SELECT 1 FROM `mks_admin_router` WHERE `name` = 'MksPriceReady' LIMIT 1) x);
