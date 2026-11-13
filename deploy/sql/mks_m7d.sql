-- ===========================================================================
-- MKSHOP M7 增量 D（KASUSHOU 1:1 对齐 · P1-3 售后管理）— 幂等，可重复执行
-- 覆盖：售后接口权限点 / 可见菜单（挂订单(41)组下；表 mks_complain 已存在）
-- ===========================================================================
SET NAMES utf8mb4;

-- 1. 接口权限点
INSERT IGNORE INTO `mks_admin_router` (`parentId`,`title`,`sort`,`name`,`auth_type`,`api_url`,`methods`,`delete_time`) VALUES
  (0, '售后列表',     100, 'complain_index',  2, 'complain/index',  'GET',  0),
  (0, '售后详情',     100, 'complain_read',   2, 'complain/read',   'GET',  0),
  (0, '售后处理',     100, 'complain_dual',   2, 'complain/dual',   'POST', 0),
  (0, '售后导出',     100, 'complain_export', 2, 'complain/export', 'GET',  0),
  (0, '售后类型',     100, 'complain_type',   2, 'complain/type_list', 'GET', 0);

-- 2. 可见菜单（挂订单路由分组下，与分销佣金同范式）
INSERT INTO `mks_admin_router` (`parentId`,`title`,`sort`,`name`,`path`,`icon`,`component`,`auth_type`,`api_url`,`methods`,`delete_time`)
SELECT r.parentId, '售后管理', 95, 'MksComplainList', 'complain', 'file-list-2-line', '@/views/order/complainList', 1, '', '', 0
FROM `mks_admin_router` r
WHERE r.`path` = 'order' AND r.`auth_type` = 1
  AND NOT EXISTS (SELECT 1 FROM (SELECT 1 FROM `mks_admin_router` WHERE `name` = 'MksComplainList' LIMIT 1) x)
LIMIT 1;
