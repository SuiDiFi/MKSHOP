-- ===========================================================================
-- MKSHOP M8 增量 C（菜单数据修正 · 浏览器验收暴露的 3 类真实缺陷）— 幂等
--
-- 1) 重复菜单覆盖：同 parentId+path 注册两条路由，后注册者胜
--    id=13 定价模板 → @/views/goods/priceReadyData（不存在，占位页）
--    id=600 定价模板 → @/views/goods/priceReady（真实页面）  ⇒ 隐藏 13
--    id=428 财务对账管理 → @/views/finance/moneyday（不存在，占位页）
--    id=651 财务对账     → @/views/finance/moneyDay（真实页面） ⇒ 隐藏 428
--    id=12/594 商品模板 component 相同，属重复注册 ⇒ 隐藏后出现的 594
-- 2) 双重编码标题：历史导入未带 utf8mb4 连接字符集，标题存成 mojibake
-- 3) 路由 path 冲突：首页下统计页 path=goods/member/order 解析为 /goods、/member、/order，
--    与商品/用户/订单渠道（同路径）撞车，面包屑点渠道会跳到统计页
--    ⇒ 统计页 path 改为 *_analysis（KASUSHOU 原样存在该歧义，此处为可用性问题主动纠正）
-- ===========================================================================
SET NAMES utf8mb4;

-- 1. 隐藏被真实页面取代的重复/占位菜单
UPDATE `mks_admin_router` SET `hidden` = 1, `levelHidden` = 1
WHERE `id` IN (13, 428, 594) AND `delete_time` = 0;

-- 2. 修正双重编码标题（源文本见 mks_extra.sql §4.1/§4.2）
UPDATE `mks_admin_router` SET `title` = '字典列表'     WHERE `id` = 429 AND `auth_type` = 2;
UPDATE `mks_admin_router` SET `title` = '字典保存'     WHERE `id` = 430 AND `auth_type` = 2;
UPDATE `mks_admin_router` SET `title` = '字典删除'     WHERE `id` = 431 AND `auth_type` = 2;
UPDATE `mks_admin_router` SET `title` = '配置分组清单' WHERE `id` = 432 AND `auth_type` = 2;
UPDATE `mks_admin_router` SET `title` = '字典管理'     WHERE `id` = 437 AND `component` = '@/views/setting/dict';
UPDATE `mks_admin_router` SET `title` = '菜单管理'     WHERE `id` = 438 AND `component` = '@/views/setting/userManagement/menuList';

-- 3. 统计页 path 去冲突（仅当仍是原值时才改，避免覆盖人工调整）
UPDATE `mks_admin_router` SET `path` = 'goods_analysis'
WHERE `id` = 3 AND `path` = 'goods'  AND `component` = '@/views/index/goodsAnalysis';
UPDATE `mks_admin_router` SET `path` = 'member_analysis'
WHERE `id` = 4 AND `path` = 'member' AND `component` = '@/views/index/memberAnalysis';
UPDATE `mks_admin_router` SET `path` = 'order_analysis'
WHERE `id` = 5 AND `path` = 'order'  AND `component` = '@/views/index/orderAnalysis';

-- 4. 商品状态页签角标接口权限点（挂商品列表菜单下）
INSERT INTO `mks_admin_router` (`parentId`,`title`,`sort`,`name`,`auth_type`,`api_url`,`methods`,`delete_time`)
SELECT COALESCE((SELECT c.`id` FROM `mks_admin_router` c
                 JOIN `mks_admin_router` p ON p.`id` = c.`parentId`
                 WHERE c.`path` = 'list' AND c.`auth_type` = 1 AND c.`delete_time` = 0
                   AND p.`path` = '/goods' AND p.`delete_time` = 0 LIMIT 1), 0),
       '商品页签数量', 100, 'goods_status_counts', 2, 'goods/status_counts', 'GET', 0
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM (SELECT 1 FROM `mks_admin_router`
                                 WHERE `api_url` = 'goods/status_counts' AND `delete_time` = 0 LIMIT 1) x);

-- 5. 结果自检（期望：0 行重复、0 行乱码、统计页 path 已改名）
-- SELECT parentId,path,COUNT(*) c FROM mks_admin_router WHERE auth_type=1 AND delete_time=0 AND path<>'' GROUP BY parentId,path HAVING c>1;
-- SELECT id,title FROM mks_admin_router WHERE title LIKE '%å%';
-- SELECT id,path,component FROM mks_admin_router WHERE id IN (3,4,5);
