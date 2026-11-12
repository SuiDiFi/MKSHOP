-- ===========================================================================
-- MKSHOP M3 第二阶段增量（幂等，可重复执行）
-- 1) 后台补单/停止补单、渠道库存同步 权限点
-- 2) sys_timer 定时任务：渠道补单/订单同步、渠道库存补货
-- 导入必须：SET NAMES utf8mb4; 且 mysql --default-character-set=utf8mb4
-- ===========================================================================
SET NAMES utf8mb4;

-- 1. 权限点（超管 role='*' 自动放行；其它角色需在角色权限树勾选）
INSERT IGNORE INTO `mks_admin_router` (`parentId`,`title`,`sort`,`name`,`auth_type`,`api_url`,`methods`,`delete_time`) VALUES
  (24, '停止补单', 100, 'no_bu_order',        2, 'order/no_bu_order',    'POST', 0),
  (0,  '渠道库存同步', 100, 'docking_sync_stock', 2, 'docking/sync_stock', 'POST', 0);

-- 2. 定时任务（type=1：cycle=分钟/秒；由 php think mks:timer 执行）
INSERT INTO `mks_sys_timer`
  (`name`,`mark`,`type`,`title`,`status`,`cycle`,`url`,`last_execution_time`,`update_execution_time`,`create_time`,`delete_time`)
SELECT '渠道补单/订单同步', 'docking_order_bu', 1, '', 1, '1/0', '', 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 0
FROM (SELECT 1 AS i) t
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `mark` FROM `mks_sys_timer` WHERE `mark` = 'docking_order_bu' LIMIT 1) x);

INSERT INTO `mks_sys_timer`
  (`name`,`mark`,`type`,`title`,`status`,`cycle`,`url`,`last_execution_time`,`update_execution_time`,`create_time`,`delete_time`)
SELECT '渠道库存/成本补货', 'docking_sync_stock', 1, '', 1, '10/0', '', 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 0
FROM (SELECT 1 AS i) t
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `mark` FROM `mks_sys_timer` WHERE `mark` = 'docking_sync_stock' LIMIT 1) x);
