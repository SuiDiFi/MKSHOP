-- =================================================================------------
-- 20261114_modify_routes.sql  登记两组「行内快捷改值」接口的权限点
-- 背景：goodsTpl.vue / priceReady.vue 的状态开关调 POST goods_tpl/modify、price_ready/modify，
--       但 route/admin.php 没注册（线上直接 404「接口不存在」，与权限无关），
--       mks_admin_router 里也没有这两条 auth_type=2 记录（换成普通角色就是 403）。
--       方法体本身早就由 mks/traits/CurdTrait.php:191 提供，缺的只是注册与登记。
-- 幂等：name 是 UNIQUE 列，用 INSERT IGNORE；已在同批 route/admin.php 里补上路由注册。
-- 生效范围：仅登记接口，角色授权仍需在「角色管理」勾选（登记 ≠ 授权）。
-- 执行：php think mks:upgrade（按文件名顺序执行并写 mks_schema_version 台账）
-- =================================================================------------

INSERT IGNORE INTO `mks_admin_router` (`parentId`,`title`,`sort`,`name`,`auth_type`,`api_url`,`methods`,`delete_time`) VALUES
  (0, '模板快捷修改', 100, 'goods_tpl_modify',   2, 'goods_tpl/modify',   'POST', 0),
  (0, '定价快捷修改', 100, 'price_ready_modify', 2, 'price_ready/modify', 'POST', 0);

-- -----------------------------------------------------------------------------
-- 同批补齐：用 tools/check_api_routes.mjs 把「已注册路由 ↔ 权限点登记」逐条对齐后，
-- 发现下面 6 个接口在鉴权组里（AdminToken+AdminAuth+AdminLog），库里 auth_type=2 行数为 0
-- （已对生产库只读核对：6 条全部 0 行，权限点总量 403、白名单 4 行）。
-- 后果：普通角色点这些功能必 403；超管因 isSuper 短路完全看不出。
-- 口径：登记 ≠ 授权——登记后没勾选该点的角色依旧 403，默认拒绝不变，所以补登记是纯修复。
-- 不补的那条：login/login 在「公开组」（route/admin.php:14-16 无中间件），本就不需要权限点。
-- -----------------------------------------------------------------------------
INSERT IGNORE INTO `mks_admin_router` (`parentId`,`title`,`sort`,`name`,`auth_type`,`api_url`,`methods`,`delete_time`) VALUES
  (0, '管理员详情',     100, 'admin_read',         2, 'admin/read',         'GET',  0),
  (0, '管理员快捷修改', 100, 'admin_modify',       2, 'admin/modify',       'POST', 0),
  (0, '角色新增',       100, 'role_create',        2, 'role/create',        'POST', 0),
  (0, '定时任务新增',   100, 'sys_timer_create',   2, 'sys_timer/create',   'POST', 0),
  (0, '对接快捷修改',   100, 'docking_modify',     2, 'docking/modify',     'POST', 0),
  (0, '对接日志列表',   100, 'docking_log_index',  2, 'docking/log_index',  'GET',  0);

-- 自检（期望：8 行）
-- SELECT `id`,`title`,`api_url`,`methods` FROM `mks_admin_router`
--  WHERE `auth_type` = 2 AND `delete_time` = 0
--    AND `api_url` IN ('goods_tpl/modify','price_ready/modify','admin/read','admin/modify',
--                      'role/create','sys_timer/create','docking/modify','docking/log_index');
