-- =================================================================------------
-- mks_m8d.sql 菜单表遗留行去冲突（UI 对齐第四轮验收发现：3 个已实现页面被遮成占位符）
-- 背景：mks_admin_router 里同一 path 存在多行（历史导入的 KASUSHOU 原始菜单 + 我们补的页面），
--       旧行的 component 指向从未实现的视图；Vue Router 先注册者胜，于是真实页面打不开。
-- 幂等：所有 UPDATE 都带原值条件，重复执行不影响已改过的行。
-- =================================================================------------

-- 1. 隐藏遗留行改指真实视图（与新增行同组件，前端择优注册后两条 path 一致）
UPDATE `mks_admin_router` SET `component` = '@/views/finance/moneyDay'
 WHERE `id` = 428 AND `component` = '@/views/finance/moneyday' AND `delete_time` = 0;

UPDATE `mks_admin_router` SET `component` = '@/views/goods/priceReady'
 WHERE `id` = 13 AND `component` = '@/views/goods/priceReadyData' AND `delete_time` = 0;

-- 2. 售后处理归位：id=25 在「订单」组内（/order/complain）却指向未实现的 orderComplain；
--    id=611 是我们补的真实页面，但挂在首页容器下变成游离顶级菜单（/complain）。
--    改为 25 指向已实现视图，611 软删，菜单只保留订单组那一条。
UPDATE `mks_admin_router` SET `component` = '@/views/order/complainList'
 WHERE `id` = 25 AND `component` = '@/views/order/orderComplain' AND `delete_time` = 0;

UPDATE `mks_admin_router` SET `delete_time` = UNIX_TIMESTAMP()
 WHERE `id` = 611 AND `parentId` = 1 AND `path` = 'complain' AND `delete_time` = 0;

-- 3. 结果自检（期望：0 行）
-- SELECT a.`id`, a.`path`, a.`component` FROM `mks_admin_router` a
--   JOIN `mks_admin_router` b ON b.`parentId` = a.`parentId` AND b.`path` = a.`path`
--   AND b.`id` <> a.`id` AND b.`delete_time` = 0
--  WHERE a.`delete_time` = 0 AND a.`path` IN ('moneyday','pricereadydata','complain','goodstpl');
