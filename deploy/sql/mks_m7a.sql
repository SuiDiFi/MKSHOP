-- ===========================================================================
-- MKSHOP M7 增量 A（KASUSHOU 1:1 对齐 · P0 第一批）— 幂等，可重复执行
-- 覆盖：订单导出 / 商品导出 接口权限点登记（RBAC 白名单外接口必须登记）
-- ===========================================================================
SET NAMES utf8mb4;

-- 1. 接口权限点（auth_type=2：接口级校验，不进菜单）
INSERT IGNORE INTO `mks_admin_router` (`parentId`,`title`,`sort`,`name`,`auth_type`,`api_url`,`methods`,`delete_time`) VALUES
  (0, '订单导出', 100, 'order_export', 2, 'order/export', 'GET', 0),
  (0, '商品导出', 100, 'goods_export', 2, 'goods/export', 'GET', 0);
