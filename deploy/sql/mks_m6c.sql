-- ===========================================================================
-- MKSHOP M6 增量 C（插件框架）— 幂等，可重复执行
-- 覆盖：mks_addon 注册表 / 配置 / 字典 / 权限点 / 后台菜单
-- 约定：MySQL 8.0；SqlImport 按分号切分，不使用存储过程
-- 安全：MVP 仅允许平台管理员从服务器本地 addons/ 目录安装（D15）
-- ===========================================================================
SET NAMES utf8mb4;

-- ---------------------------------------------------------------------------
-- 1. mks_addon — 插件注册表（目录名即插件标识）
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `mks_addon` (
  `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT '插件ID',
  `name` varchar(50) NOT NULL DEFAULT '' COMMENT '插件标识（addons/ 下目录名，唯一）',
  `title` varchar(100) NOT NULL DEFAULT '' COMMENT '插件名称',
  `version` varchar(20) NOT NULL DEFAULT '' COMMENT '版本号',
  `author` varchar(100) NOT NULL DEFAULT '' COMMENT '作者',
  `description` varchar(500) NOT NULL DEFAULT '' COMMENT '插件简介',
  `hooks` text COMMENT '监听的钩子（JSON 数组）',
  `menus` text COMMENT '注入的菜单快照（JSON，卸载时按 id 删除）',
  `config` text COMMENT '插件配置值（JSON）',
  `status` tinyint(1) NOT NULL DEFAULT 0 COMMENT '状态 1=启用 0=停用',
  `install_time` int NOT NULL DEFAULT 0 COMMENT '安装时间',
  `update_time` int NOT NULL DEFAULT 0 COMMENT '更新时间',
  `delete_time` int NOT NULL DEFAULT 0 COMMENT '软删除时间（0=未删除）',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='插件注册表';

-- ---------------------------------------------------------------------------
-- 2. 配置种子（插件总开关）
-- ---------------------------------------------------------------------------
INSERT INTO `mks_config` (`vip_id`,`name`,`info`,`value`,`type`,`input_type`,`group`,`parameter`,`upload_type`,`required`,`width`,`desc`,`high`,`sort`,`props`,`status`,`delete_time`,`son_names`)
SELECT 0,'addon_enable','启用插件机制','1','switch','switch','addon','',1,'',0,'关闭后所有插件钩子不再触发（不影响已注入菜单）',0,100,NULL,1,0,NULL
WHERE NOT EXISTS (SELECT 1 FROM `mks_config` WHERE `name` = 'addon_enable' AND `vip_id` = 0);

-- ---------------------------------------------------------------------------
-- 3. 字典种子
-- ---------------------------------------------------------------------------
REPLACE INTO `mks_sys_dict` (`dict_type`,`code`,`label`,`sort`) VALUES
  ('addon_status','1','已启用',1),('addon_status','0','已停用',2);

-- ---------------------------------------------------------------------------
-- 4. 后台接口权限点（默认拒绝，必须登记）
-- ---------------------------------------------------------------------------
INSERT IGNORE INTO `mks_admin_router` (`parentId`,`title`,`sort`,`name`,`auth_type`,`api_url`,`methods`,`delete_time`) VALUES
  (0,'插件列表',100,'addon_index',2,'addon/index','GET',0),
  (0,'插件详情',100,'addon_read',2,'addon/read','GET',0),
  (0,'插件本地扫描',100,'addon_scan',2,'addon/scan','GET',0),
  (0,'插件安装',100,'addon_install',2,'addon/install','POST',0),
  (0,'插件启用',100,'addon_enable',2,'addon/enable','POST',0),
  (0,'插件停用',100,'addon_disable',2,'addon/disable','POST',0),
  (0,'插件卸载',100,'addon_uninstall',2,'addon/uninstall','POST',0),
  (0,'插件配置保存',100,'addon_config_save',2,'addon/config_save','POST',0),
  (0,'插件钩子清单',100,'addon_hooks',2,'addon/hooks','GET',0);

-- ---------------------------------------------------------------------------
-- 5. 平台后台菜单（挂到系统设置分组；与 mks_extra.sql 写法一致）
-- ---------------------------------------------------------------------------
INSERT INTO `mks_admin_router` (`parentId`,`title`,`sort`,`name`,`path`,`icon`,`component`,`auth_type`,`api_url`,`methods`,`delete_time`)
SELECT r.parentId, '插件管理', 88, 'MksAddonList', 'addon', 'extension', '@/views/setting/addonList', 1, '', '', 0
FROM `mks_admin_router` r
WHERE r.`name` = 'MksDictList' AND r.`auth_type` = 1
  AND NOT EXISTS (SELECT 1 FROM (SELECT 1 FROM `mks_admin_router` WHERE `name` = 'MksAddonList' LIMIT 1) x)
LIMIT 1;

-- 兜底：字典菜单不存在时挂到顶级
INSERT INTO `mks_admin_router` (`parentId`,`title`,`sort`,`name`,`path`,`icon`,`component`,`auth_type`,`api_url`,`methods`,`delete_time`)
SELECT 0, '插件管理', 88, 'MksAddonList', 'addon', 'extension', '@/views/setting/addonList', 1, '', '', 0
WHERE NOT EXISTS (SELECT 1 FROM (SELECT 1 FROM `mks_admin_router` WHERE `name` = 'MksAddonList' LIMIT 1) x);
