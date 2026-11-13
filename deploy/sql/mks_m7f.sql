-- ===========================================================================
-- MKSHOP M7 增量 F（KASUSHOU 1:1 对齐 · P1-5 用户权限粒度）— 幂等，可重复执行
-- 覆盖：用户密价/销售限价/商品权限 三张表 / 接口权限点 / 可见菜单（会员组下）
-- 说明：模板绑定关系全量替换写入；生效逻辑由后续批次接入（密价/限价/可见性过滤）
-- ===========================================================================
SET NAMES utf8mb4;

-- 1. 模板主表（type：1=会员密价 2=销售限价 3=商品权限）
CREATE TABLE IF NOT EXISTS `mks_user_price_tpl` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `type` tinyint NOT NULL DEFAULT 1 COMMENT '1=会员密价 2=销售限价 3=商品权限',
  `name` varchar(100) NOT NULL DEFAULT '' COMMENT '模板名称',
  `mode` tinyint NOT NULL DEFAULT 1 COMMENT '密价幅度类型：1=按比例加价% 2=固定加价 3=固定售价',
  `value` decimal(12,2) NOT NULL DEFAULT 0 COMMENT '密价幅度/最低售价/权限标记',
  `status` tinyint NOT NULL DEFAULT 1 COMMENT '1=启用 0=停用',
  `create_time` int unsigned NOT NULL DEFAULT 0,
  `update_time` int unsigned NOT NULL DEFAULT 0,
  `delete_time` int unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_type` (`type`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='用户密价/限价/权限模板';

-- 2. 模板-用户绑定（唯一联合防重）
CREATE TABLE IF NOT EXISTS `mks_user_price_tpl_user` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `tpl_id` int unsigned NOT NULL DEFAULT 0,
  `uid` int unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_tpl_uid` (`tpl_id`,`uid`),
  KEY `idx_uid` (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='用户权限模板-用户绑定';

-- 3. 模板-商品绑定（唯一联合防重）
CREATE TABLE IF NOT EXISTS `mks_user_price_tpl_goods` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `tpl_id` int unsigned NOT NULL DEFAULT 0,
  `goods_id` int unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_tpl_goods` (`tpl_id`,`goods_id`),
  KEY `idx_goods` (`goods_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='用户权限模板-商品绑定';

-- 4. 接口权限点
INSERT IGNORE INTO `mks_admin_router` (`parentId`,`title`,`sort`,`name`,`auth_type`,`api_url`,`methods`,`delete_time`) VALUES
  (0, '用户权限列表',   100, 'user_price_tpl_index',  2, 'user_price_tpl/index',  'GET',  0),
  (0, '用户权限详情',   100, 'user_price_tpl_read',   2, 'user_price_tpl/read',   'GET',  0),
  (0, '用户权限新建',   100, 'user_price_tpl_create', 2, 'user_price_tpl/create', 'POST', 0),
  (0, '用户权限更新',   100, 'user_price_tpl_save',   2, 'user_price_tpl/save',   'POST', 0),
  (0, '用户权限删除',   100, 'user_price_tpl_delete', 2, 'user_price_tpl/delete', 'POST', 0),
  (0, '用户权限改值',   100, 'user_price_tpl_modify', 2, 'user_price_tpl/modify', 'POST', 0);

-- 5. 可见菜单（挂会员分组下；parentId 取 name=Member 分组行的 id）
INSERT INTO `mks_admin_router` (`parentId`,`title`,`sort`,`name`,`path`,`icon`,`component`,`auth_type`,`api_url`,`methods`,`delete_time`)
SELECT r.id, '用户权限', 96, 'MksUserPriceTpl', 'userpricetpl', 'user-settings-line', '@/views/member/userPriceTpl', 1, '', '', 0
FROM `mks_admin_router` r
WHERE r.`name` = 'Member' AND r.`auth_type` = 1
  AND NOT EXISTS (SELECT 1 FROM (SELECT 1 FROM `mks_admin_router` WHERE `name` = 'MksUserPriceTpl' LIMIT 1) x)
LIMIT 1;
