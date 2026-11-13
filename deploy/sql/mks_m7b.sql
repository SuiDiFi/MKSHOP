-- ===========================================================================
-- MKSHOP M7 增量 B（KASUSHOU 1:1 对齐 · P1-1 商品模板）— 幂等，可重复执行
-- 覆盖：下单输入项模板表 / goods.tpl_id 关联列 / 权限点与可见菜单
-- ===========================================================================
SET NAMES utf8mb4;

-- 1. 下单输入项模板表
CREATE TABLE IF NOT EXISTS `mks_goods_tpl` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL DEFAULT '' COMMENT '模板名称',
  `inputs` text NULL COMMENT '输入项 JSON：[{label,placeholder,required}]',
  `sort` int NOT NULL DEFAULT 100 COMMENT '权重',
  `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1=启用 0=停用',
  `create_time` int NOT NULL DEFAULT 0,
  `update_time` int NOT NULL DEFAULT 0,
  `delete_time` int NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='商品下单输入项模板（M7 对齐 KASUSHOU goods_tpl）';

-- 2. goods.tpl_id 关联列（幂等加列）
SET @ddl = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'mks_goods' AND COLUMN_NAME = 'tpl_id') = 0,
  'ALTER TABLE `mks_goods` ADD COLUMN `tpl_id` int NOT NULL DEFAULT 0 COMMENT ''下单输入项模板ID（0=不使用模板）'' AFTER `num_input_type`',
  'SELECT 1');
PREPARE mks_stmt FROM @ddl;
EXECUTE mks_stmt;
DEALLOCATE PREPARE mks_stmt;

-- 3. 接口权限点
INSERT IGNORE INTO `mks_admin_router` (`parentId`,`title`,`sort`,`name`,`auth_type`,`api_url`,`methods`,`delete_time`) VALUES
  (0, '模板列表',   100, 'goods_tpl_index',  2, 'goods_tpl/index',  'GET',  0),
  (0, '模板全量',   100, 'goods_tpl_list',   2, 'goods_tpl/list',   'POST', 0),
  (0, '模板创建',   100, 'goods_tpl_create', 2, 'goods_tpl/create', 'POST', 0),
  (0, '模板更新',   100, 'goods_tpl_save',   2, 'goods_tpl/save',   'POST', 0),
  (0, '模板删除',   100, 'goods_tpl_delete', 2, 'goods_tpl/delete', 'POST', 0);

-- 4. 可见菜单（挂商品(6)下）
INSERT INTO `mks_admin_router` (`parentId`,`title`,`sort`,`name`,`path`,`icon`,`component`,`auth_type`,`api_url`,`methods`,`delete_time`)
SELECT 6, '商品模板', 97, 'MksGoodsTpl', 'goodstpl', 'sound-module-line', '@/views/goods/goodsTpl', 1, '', '', 0
WHERE EXISTS (SELECT 1 FROM (SELECT 1 FROM `mks_admin_router` WHERE `id` = 6 LIMIT 1) p)
  AND NOT EXISTS (SELECT 1 FROM (SELECT 1 FROM `mks_admin_router` WHERE `name` = 'MksGoodsTpl' LIMIT 1) x);
