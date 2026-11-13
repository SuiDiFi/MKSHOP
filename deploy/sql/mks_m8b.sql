-- ===========================================================================
-- MKSHOP M8 增量 B（后台 UI 对齐 KASUSHOU · 页签数量角标）— 幂等，可重复执行
-- 覆盖：order/status_counts 接口权限点（红线 4：未登记默认拒绝）
-- ===========================================================================
SET NAMES utf8mb4;

-- 1. 接口权限点（挂到「订单列表」菜单节点下；节点 id 按 name='list' + parentId=订单组 定位）
INSERT INTO `mks_admin_router` (`parentId`,`title`,`sort`,`name`,`auth_type`,`api_url`,`methods`,`delete_time`)
SELECT COALESCE((SELECT c.`id` FROM `mks_admin_router` c
                 JOIN `mks_admin_router` p ON p.`id` = c.`parentId`
                 WHERE c.`path` = 'list' AND c.`auth_type` = 1 AND c.`delete_time` = 0
                   AND p.`path` = '/order' AND p.`delete_time` = 0 LIMIT 1), 0),
       '订单页签数量', 100, 'order_status_counts', 2, 'order/status_counts', 'GET', 0
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT 1 FROM `mks_admin_router`
                                 WHERE `api_url` = 'order/status_counts' AND `delete_time` = 0 LIMIT 1) x);
