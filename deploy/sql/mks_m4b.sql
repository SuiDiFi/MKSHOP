-- ============================================================================
-- MKSHOP · M4 会员分销补齐（会员管理/等级/提现）
-- 幂等可重复执行：表 IF NOT EXISTS；菜单/权限/字典按唯一键防重。
-- 依据：_analysis 00-07 文档与 R 路由清单（member/*、member_level/*、member_collect/*）
-- 注：SET NAMES 必须显式声明，导入需 --default-character-set=utf8mb4
-- ============================================================================
SET NAMES utf8mb4;

-- 1. 提现申请单（schema 已含定义；此处兜底老库）
CREATE TABLE IF NOT EXISTS `mks_member_collect` (
  `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT '提现申请ID',
  `host` varchar(255) NOT NULL DEFAULT '' COMMENT '申请来源域名',
  `uid_type` tinyint(1) NOT NULL DEFAULT 1 COMMENT '提现主体类型 1=会员 2=供货商/商户（推断）',
  `collect_type` tinyint(1) NOT NULL DEFAULT 1 COMMENT '提现类型（对应 member_collect_way.collect_type 与 config.collect_config.collect_bank_list）',
  `apply_uid` int NOT NULL DEFAULT 0 COMMENT '申请人ID',
  `apply_uname` varchar(50) NOT NULL DEFAULT '' COMMENT '申请人账号',
  `way_id` int NOT NULL DEFAULT 0 COMMENT '提现方式ID → member_collect_way.id',
  `way_name` varchar(100) NOT NULL DEFAULT '' COMMENT '提现方式名称快照',
  `account` varchar(100) NOT NULL DEFAULT '' COMMENT '收款账号',
  `true_name` varchar(100) NOT NULL DEFAULT '' COMMENT '收款人姓名',
  `money` decimal(16,4) NOT NULL DEFAULT 0.00 COMMENT '提现金额',
  `fee` decimal(16,4) NOT NULL DEFAULT 0.00 COMMENT '手续费',
  `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '提现状态：1=待审核 2=已通过 3=已拒绝',
  `create_time` int NOT NULL DEFAULT 0 COMMENT '申请时间',
  `update_time` int NOT NULL DEFAULT 0 COMMENT '处理时间',
  `admin_id` int NOT NULL DEFAULT 0 COMMENT '处理管理员ID',
  `admin_uname` varchar(100) NOT NULL DEFAULT '' COMMENT '处理管理员名',
  `check_time` int NOT NULL DEFAULT 0 COMMENT '审核时间',
  `mark` varchar(200) NOT NULL DEFAULT '' COMMENT '备注',
  `type` tinyint(1) NOT NULL DEFAULT 1 COMMENT '提现来源（余额/佣金）',
  PRIMARY KEY (`id`),
  KEY `idx_apply_uid` (`apply_uid`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='提现申请单';

-- 2. 会员提现方式
CREATE TABLE IF NOT EXISTS `mks_member_collect_way` (
  `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT '提现方式ID',
  `collect_img` varchar(255) NOT NULL DEFAULT '' COMMENT '方式图标/收款码',
  `uid` int NOT NULL DEFAULT 0 COMMENT '绑定会员ID（0=平台全局方式）',
  `name` varchar(255) NOT NULL DEFAULT '' COMMENT '方式名称',
  `uid_type` tinyint(1) NOT NULL DEFAULT 1 COMMENT '主体类型',
  `company` varchar(100) NOT NULL DEFAULT '' COMMENT '开户行/公司',
  `account` varchar(100) NOT NULL DEFAULT '' COMMENT '收款账号',
  `true_name` varchar(100) NOT NULL DEFAULT '' COMMENT '收款人',
  `bank` int NOT NULL DEFAULT 0 COMMENT '银行编码 → bank.id（该表不在 SQL 内）',
  `is_default` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否默认方式',
  `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '审核状态：1=待审核 2=已通过 3=已拒绝',
  `create_time` int NOT NULL DEFAULT 0 COMMENT '创建时间',
  `update_time` int NOT NULL DEFAULT 0 COMMENT '更新时间',
  `admin_id` int NOT NULL DEFAULT 0 COMMENT '审核管理员ID',
  `admin_uname` varchar(100) NOT NULL DEFAULT '' COMMENT '审核管理员名',
  `check_time` int NOT NULL DEFAULT 0 COMMENT '审核时间',
  `mark` varchar(1000) NOT NULL DEFAULT '' COMMENT '备注',
  `accountinfo` varchar(255) NOT NULL DEFAULT '' COMMENT '账户附加信息 JSON',
  PRIMARY KEY (`id`),
  KEY `idx_uid` (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='提现方式（银行卡/支付宝/微信/USDT 等）绑定与默认项';

-- 3. 可见菜单兜底（用户/用户列表/用户等级/提现记录管理；seed 已含则跳过）
INSERT INTO `mks_admin_router` (`parentId`,`title`,`sort`,`name`,`path`,`icon`,`component`,`auth_type`,`api_url`,`methods`,`delete_time`)
SELECT 0, '用户', 9997, 'Member', '/member', 'user-3-line', 'Layout', 1, '', '', 0
WHERE NOT EXISTS (SELECT 1 FROM (SELECT 1 FROM `mks_admin_router` WHERE `name` = 'Member' AND `auth_type` = 1 LIMIT 1) x);

INSERT INTO `mks_admin_router` (`parentId`,`title`,`sort`,`name`,`path`,`icon`,`component`,`auth_type`,`api_url`,`methods`,`delete_time`)
SELECT r.id, '用户列表', 9999, 'MemberList', 'list', 'user-3-fill', '@/views/member/memberList', 1, '', '', 0
FROM `mks_admin_router` r
WHERE r.`name` = 'Member' AND r.`auth_type` = 1
  AND NOT EXISTS (SELECT 1 FROM (SELECT 1 FROM `mks_admin_router` WHERE `name` = 'MemberList' AND `auth_type` = 1 LIMIT 1) x)
LIMIT 1;

INSERT INTO `mks_admin_router` (`parentId`,`title`,`sort`,`name`,`path`,`icon`,`component`,`auth_type`,`api_url`,`methods`,`delete_time`)
SELECT r.id, '用户等级', 100, 'MemberLevel', 'level', 'award-fill', '@/views/member/memberLevel', 1, '', '', 0
FROM `mks_admin_router` r
WHERE r.`name` = 'Member' AND r.`auth_type` = 1
  AND NOT EXISTS (SELECT 1 FROM (SELECT 1 FROM `mks_admin_router` WHERE `name` = 'MemberLevel' AND `auth_type` = 1 LIMIT 1) x)
LIMIT 1;

INSERT INTO `mks_admin_router` (`parentId`,`title`,`sort`,`name`,`path`,`icon`,`component`,`auth_type`,`api_url`,`methods`,`delete_time`)
SELECT r.id, '提现记录管理', 100, 'WithdrawRecord', 'withdraw', 'stack-overflow-line', '@/views/finance/withdrawRecord', 1, '', '', 0
FROM `mks_admin_router` r
WHERE r.`name` = 'Finance' AND r.`auth_type` = 1
  AND NOT EXISTS (SELECT 1 FROM (SELECT 1 FROM `mks_admin_router` WHERE `name` = 'WithdrawRecord' AND `auth_type` = 1 LIMIT 1) x)
LIMIT 1;

-- 4. 接口权限点兜底（RBAC 默认拒绝：未登记即拒绝）
INSERT INTO `mks_admin_router` (`parentId`,`title`,`sort`,`name`,`auth_type`,`api_url`,`methods`,`delete_time`)
SELECT s.pid, s.title, 100, s.nm, 2, s.api, s.mt, 0
FROM (
  SELECT 20 pid, '用户列表' title, 'm4_member_index' nm, 'member/index' api, 'GET' mt UNION ALL
  SELECT 20, '用户快捷修改', 'm4_member_modify', 'member/modify', 'POST' UNION ALL
  SELECT 20, '用户创建', 'm4_member_create', 'member/create', 'POST' UNION ALL
  SELECT 20, '用户保存', 'm4_member_save', 'member/save', 'POST' UNION ALL
  SELECT 20, '用户删除', 'm4_member_delete', 'member/delete', 'POST' UNION ALL
  SELECT 20, '用户选择', 'm4_member_list', 'member/list', 'POST' UNION ALL
  SELECT 20, '用户列表Tab', 'm4_member_status_type', 'member/status_type', 'POST' UNION ALL
  SELECT 20, '用户回收站删除', 'm4_member_destroy', 'member/destroy', 'POST' UNION ALL
  SELECT 20, '用户回收站还原', 'm4_member_restore', 'member/restore', 'POST' UNION ALL
  SELECT 20, '用户信息', 'm4_member_read', 'member/read', 'POST' UNION ALL
  SELECT 20, '用户详情', 'm4_member_one_info', 'member/one_info', 'POST' UNION ALL
  SELECT 20, '重置密码', 'm4_member_reset_password', 'member/reset_password', 'POST' UNION ALL
  SELECT 20, '用户解绑', 'm4_member_unbind', 'member/unbind', 'POST' UNION ALL
  SELECT 21, '用户等级列表', 'm4_member_level_index', 'member_level/index', 'GET' UNION ALL
  SELECT 21, '用户等级选择列表', 'm4_member_level_list', 'member_level/list', 'POST' UNION ALL
  SELECT 21, '用户等级创建', 'm4_member_level_create', 'member_level/create', 'POST' UNION ALL
  SELECT 21, '用户等级保存', 'm4_member_level_save', 'member_level/save', 'POST' UNION ALL
  SELECT 21, '用户等级删除', 'm4_member_level_delete', 'member_level/delete', 'POST' UNION ALL
  SELECT 21, '级别快速修改', 'm4_member_level_modify', 'member_level/modify', 'POST' UNION ALL
  SELECT 31, '提现列表', 'm4_member_collect_index', 'member_collect/index', 'GET' UNION ALL
  SELECT 31, '提现处理', 'm4_member_collect_dual', 'member_collect/dual', 'POST'
) s
WHERE NOT EXISTS (
  SELECT 1 FROM `mks_admin_router` t WHERE t.`api_url` = s.api AND t.`delete_time` = 0
);

-- 5. 字典：提现状态/来源、会员状态
REPLACE INTO `mks_sys_dict` (`dict_type`,`code`,`label`,`color`,`sort`,`status`,`vip_id`) VALUES
  ('collect_status','1','待审核','warning',1,1,0),
  ('collect_status','2','已通过','success',2,1,0),
  ('collect_status','3','已拒绝','danger',3,1,0),
  ('collect_type','1','余额','',1,1,0),
  ('collect_type','2','佣金','warning',2,1,0),
  ('member_status','1','正常','success',1,1,0),
  ('member_status','0','禁用','danger',2,1,0);
