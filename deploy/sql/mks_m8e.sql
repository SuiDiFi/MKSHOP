-- =================================================================------------
-- mks_m8e.sql 登记「商品新增」接口权限点（商品独立编辑页验收发现的 RBAC 缺口）
-- 背景：AdminAuth 是默认拒绝——接口没在 mks_admin_router(auth_type=2) 登记就一律 403。
--       商品权限点里有 goods/save(更新)、goods/read、goods/delete…，唯独没有 goods/create，
--       而新增商品走的就是 POST goods/create。超管短路 isSuper 看不出问题，
--       换成普通/供货商角色点「保存」必然 403，商品永远加不出来。
-- 幂等：NOT EXISTS 按 api_url 守卫；name 是 UNIQUE 列，用 MksGoodsCreate 不与种子导出的数字名冲突。
-- 生效范围：只是把接口登记上，角色授权(admin_role.role 的 router id 串)仍需在「角色管理」勾选，
--           与其它新增权限点的口径一致。
-- =================================================================------------

INSERT INTO `mks_admin_router`
  (`parentId`,`title`,`sort`,`name`,`path`,`icon`,`component`,`redirect`,
   `hidden`,`levelHidden`,`tabHidden`,`badge`,`noColumn`,`noClosable`,`target`,`activeMenu`,
   `dot`,`dynamicNewTab`,`breadcrumbHidden`,`noKeepAlive`,`isCustomSvg`,
   `mode`,`auth_type`,`api_url`,`methods`,`delete_time`)
SELECT 9,'商品新增',100,'MksGoodsCreate','','','GoodsList','',
   0,0,0,'',0,0,'','',
   0,0,0,0,0,
   'allOf',2,'goods/create','POST',0
  FROM DUAL
 WHERE NOT EXISTS (
   SELECT 1 FROM (SELECT `id` FROM `mks_admin_router`
                   WHERE `api_url` = 'goods/create' AND `delete_time` = 0 LIMIT 1) x
 );

-- 自检（期望：1 行 goods/create POST）
-- SELECT `id`,`parentId`,`title`,`api_url`,`methods` FROM `mks_admin_router`
--  WHERE `auth_type` = 2 AND `api_url` = 'goods/create' AND `delete_time` = 0;
