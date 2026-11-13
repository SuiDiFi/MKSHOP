-- ===========================================================================
-- MKSHOP M8 增量 A（KASUSHOU 1:1 对齐 · 运营统计看板）— 幂等，可重复执行
--
-- 背景：mks_seed.sql 继承原系统 413 行 admin_router，其中
--   菜单节点 id=2 首页 / id=3 商品统计 / id=4 用户统计 / id=5 交易分析
--   接口节点 id=80~88（index_statics / goods_statics[_list|_trend] / user_statics[_trend] / buy_statics[_trend|_today]）
--   早已登记，但本方一直没有对应路由与页面 → 菜单点进去是占位页、接口 404。
-- 本次交付补齐后端路由 + 前端 4 个页面，因此本增量只做两件事：
--   1) 新登记的物化回算接口权限点 statics_aggregate（红线 4：未登记默认拒绝）
--   2) statics_order 定时任务种子（对齐原系统「自动统计10天内订单数据」）
-- ===========================================================================
SET NAMES utf8mb4;

-- 1. 物化表回算接口权限点（挂到「交易分析」菜单节点 id=5 下，便于按角色授予）
INSERT INTO `mks_admin_router` (`parentId`,`title`,`sort`,`name`,`auth_type`,`api_url`,`methods`,`delete_time`)
SELECT 5, '统计汇总回算', 100, 'statics_aggregate', 2, 'statics_aggregate', 'POST', 0
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT 1 FROM `mks_admin_router` WHERE `api_url` = 'statics_aggregate' AND `delete_time` = 0 LIMIT 1) x);

-- 2. 兜底：若旧库未导入完整 seed（缺 api_url 登记）则补齐；已存在则不重复插入
--    （按 api_url LEFT JOIN 去重，避开 name 唯一索引 routername 冲突）
INSERT INTO `mks_admin_router` (`parentId`,`title`,`sort`,`name`,`auth_type`,`api_url`,`methods`,`delete_time`)
SELECT s.parentId, s.title, 100, s.api_url, 2, s.api_url, s.methods, 0
FROM (
  SELECT 2 AS parentId, '首页统计'         AS title, 'index_statics'       AS api_url, 'GET' AS methods UNION ALL
  SELECT 3, '商品统计',       'goods_statics',        'GET' UNION ALL
  SELECT 3, '商品排行统计',   'goods_statics_list',   'GET' UNION ALL
  SELECT 3, '商品统计图表',   'goods_statics_trend',  'GET' UNION ALL
  SELECT 4, '用户统计',       'user_statics',         'GET' UNION ALL
  SELECT 4, '用户统计图表',   'user_statics_trend',   'GET' UNION ALL
  SELECT 5, '交易统计',       'buy_statics',          'GET' UNION ALL
  SELECT 5, '交易统计图表',   'buy_statics_trend',    'GET' UNION ALL
  SELECT 5, '交易统计今日图表', 'buy_statics_today',    'GET'
) s
LEFT JOIN `mks_admin_router` r
  ON r.`api_url` = s.api_url AND r.`auth_type` = 2 AND r.`delete_time` = 0
WHERE r.`id` IS NULL;

-- 3. 物化表（mks_statics 已在 mks_schema.sql 建好，此处仅确保幂等存在）
CREATE TABLE IF NOT EXISTS `mks_statics` (
  `total_create_time` varchar(20) NOT NULL DEFAULT '0000-00-00' COMMENT '统计日期',
  `order_order_price` decimal(16,4) NOT NULL DEFAULT 0.0000 COMMENT '订单利润',
  `order_total_price` decimal(16,4) NOT NULL DEFAULT 0.0000 COMMENT '订单金额',
  `order_order_id` int NOT NULL DEFAULT 0 COMMENT '订单数量',
  `update_time` int NOT NULL DEFAULT 0 COMMENT '更新时间',
  PRIMARY KEY (`total_create_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='订单统计日表';

-- 4. 定时任务：每小时回算近 10 天成交汇总（type=1 cycle=60分钟/0秒）
INSERT INTO `mks_sys_timer` (`mark`,`name`,`type`,`cycle`,`status`,`delete_time`,`create_time`,`last_execution_time`)
SELECT 'statics_order', '按日成交汇总物化（近10天回算）', 1, '60/0', 1, 0, UNIX_TIMESTAMP(), 0
WHERE NOT EXISTS (SELECT 1 FROM (SELECT 1 FROM `mks_sys_timer` WHERE `mark` = 'statics_order' AND `delete_time` = 0 LIMIT 1) x);
