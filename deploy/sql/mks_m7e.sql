-- ===========================================================================
-- MKSHOP M7 增量 E（KASUSHOU 1:1 对齐 · P1-4 财务对账）— 幂等，可重复执行
-- 覆盖：对账接口权限点 / 可见菜单（财务(44)组下）/ 日结定时任务种子
-- mks_money_day 表已存在；mks_docking_day 由 MoneyDayService 首次运行自动建表
-- ===========================================================================
SET NAMES utf8mb4;

-- 1. 接口权限点
INSERT IGNORE INTO `mks_admin_router` (`parentId`,`title`,`sort`,`name`,`auth_type`,`api_url`,`methods`,`delete_time`) VALUES
  (0, '客户对账列表',   100, 'money_day_index',   2, 'money_day/index',   'GET',  0),
  (0, '客户对账更新',   100, 'money_day_update',  2, 'money_day/update',  'POST', 0),
  (0, '渠道对账列表',   100, 'money_day_channel', 2, 'money_day/channel_index',  'GET',  0),
  (0, '渠道对账更新',   100, 'money_day_channel_update', 2, 'money_day/channel_update', 'POST', 0),
  (0, '客户对账导出',   100, 'money_day_export',  2, 'money_day/export',  'GET',  0);

-- 2. 可见菜单（挂财务(44)组下）
INSERT INTO `mks_admin_router` (`parentId`,`title`,`sort`,`name`,`path`,`icon`,`component`,`auth_type`,`api_url`,`methods`,`delete_time`)
SELECT r.parentId, '财务对账', 96, 'MksMoneyDay', 'moneyday', 'file-list-line', '@/views/finance/moneyDay', 1, '', '', 0
FROM `mks_admin_router` r
WHERE r.`path` = 'finance' AND r.`auth_type` = 1
  AND NOT EXISTS (SELECT 1 FROM (SELECT 1 FROM `mks_admin_router` WHERE `name` = 'MksMoneyDay' LIMIT 1) x)
LIMIT 1;

INSERT INTO `mks_admin_router` (`parentId`,`title`,`sort`,`name`,`path`,`icon`,`component`,`auth_type`,`api_url`,`methods`,`delete_time`)
SELECT r.parentId, '渠道对账', 95, 'MksDockingDay', 'dockingday', 'coin', '@/views/finance/dockingDay', 1, '', '', 0
FROM `mks_admin_router` r
WHERE r.`path` = 'finance' AND r.`auth_type` = 1
  AND NOT EXISTS (SELECT 1 FROM (SELECT 1 FROM `mks_admin_router` WHERE `name` = 'MksDockingDay' LIMIT 1) x)
LIMIT 1;

-- 3. 日结定时任务（每日一次；type=1 cycle=1440分钟/0秒）
INSERT INTO `mks_sys_timer` (`mark`,`name`,`type`,`cycle`,`status`,`delete_time`,`create_time`,`last_execution_time`)
SELECT 'money_day', '客户+渠道日结对账', 1, '1440/0', 1, 0, UNIX_TIMESTAMP(), 0
WHERE NOT EXISTS (SELECT 1 FROM (SELECT 1 FROM `mks_sys_timer` WHERE `mark` = 'money_day' AND `delete_time` = 0 LIMIT 1) x);
