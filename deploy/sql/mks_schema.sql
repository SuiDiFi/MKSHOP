-- ============================================================================
-- MKSHOP · 建库脚本（由 tools/transform_sql.mjs 自动生成，勿手改）
-- 表前缀 mks_ · 字符集 utf8mb4 · 金额 decimal(16,4)/成本(16,5) · 时间 int
-- 源：逆向分析交付包 appendix/F（DDL 106 表）与 appendix/G（种子 834 行）
-- 生成时间：2026-11-11T12:22:42.282Z
-- ============================================================================

-- ============================================================================
-- F-重写建表SQL.sql
-- MKSHOP（MKSHOP）商城系统 · 重写建库 DDL（MySQL 8.0+）
-- ----------------------------------------------------------------------------
-- 来源   : E:\MKSHOP\app\controller\install\xm999.sql（Navicat 导出，97 表）
--          + _analysis/01b-枚举与配置字典.md §6（9 张插件补建表）
-- 清洗   : 1) 去掉 DROP TABLE / 全部 INSERT（数据见 G-字典与种子数据.sql）
--          2) Navicat 噪音（USING BTREE / 行内 CHARACTER SET / 列对齐空格）全部移除
--          3) 字符集统一 utf8mb4 / utf8mb4_general_ci（原库 16 张表是 utf8）
--          4) MySQL 8 整数显示宽度归一（int(11)→int，保留 tinyint(1) 布尔语义）
--          5) 全表补 COMMENT（中文业务含义回填，来源 01/01b/源码）
--          6) 去重索引：与主键重复的 id 索引、列完全相同的重复索引
--          7) 金额精度统一：所有金额类 decimal 字段 → decimal(16,4)；成本 → decimal(16,5)
--             费率类（*rate*）保持原精度；时间统一 int 时间戳
--
-- 执行顺序（必须按序）：
--   ① F-重写建表SQL.sql          ← 本文件，按 P0→P5→P6 分区顺序执行
--   ② G-字典与种子数据.sql        ← 必需字典（config 179 / admin_router 413 / 等级 / 定时任务 …）
--   ③ mks_city_area.sql      ← 城市/街道（44708 行，4.6 MB）
--   ④ mks_provinces.sql      ← 省市区（3676 行）
--      注：官方安装器 InstallEdJob 的顺序是 city_area → provinces（两表互不依赖，顺序无关，
--          但为与原系统一致，此处按官方顺序排列）
--   （原 xm999.sql 与 default.sql 不参与，等价内容已在 F/G 内）
--
-- 分区说明：
--   P0 权限+配置+会员+商品+卡密+订单+支付   → 少了起不来
--   P1 资金/结算/售后/会员扩展              → 有交易就必须有
--   P2 对接供货                             → 做渠道/供货商才有用
--   P3 运营内容与通知                       → 前台内容位
--   P4 装潢DIY与多站点                      → 可视化装修/域名绑定
--   P5 运维周边                             → 可后置
--   P6 插件补建表（自研替代）                → 官方为付费插件，若无授权可自研
--
-- ⚠ 外键：本套系统原本「零外键约束」，本文件保持一致（不建 FK），
--   关系完整性由应用层保证；若新架构需要 FK，请按 01 文档「关联（推断）」列自行添加。
-- ⚠ 软删除：26 张表有 delete_time（0=未删）。带 delete_time 前缀的索引仅服务于
--   「查询恒带 delete_time=0」的场景，若新代码不保证该前提，这些索引会退化。
-- ============================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;
SET SESSION sql_mode = 'STRICT_TRANS_TABLES,NO_ENGINE_SUBSTITUTION';

-- 建库（按需改库名）：
-- CREATE DATABASE IF NOT EXISTS `xm999` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
-- USE `xm999`;


-- ============================================================================
-- P0 核心骨架（必须先建：权限 + 配置 + 会员 + 商品 + 卡密 + 订单 + 支付）  （36 张表）
-- ============================================================================
-- ---------------------------------------------------------------------------
-- [P0] mks_admin — 后台管理员：账号密码、角色、余额、API 白名单(cao_*)
-- ---------------------------------------------------------------------------
CREATE TABLE `mks_admin` (
  `id` tinyint unsigned NOT NULL AUTO_INCREMENT COMMENT '管理员ID',
  `roles` varchar(255) NOT NULL DEFAULT '' COMMENT '角色ID（可多值，逗号分隔）→ admin_role.id',
  `username` varchar(20) NOT NULL DEFAULT '' COMMENT '登录账号（唯一）',
  `password` varchar(200) NOT NULL DEFAULT '' COMMENT '密码哈希',
  `trade_password` varchar(255) NOT NULL DEFAULT '' COMMENT '资金操作二次密码',
  `email` varchar(50) NOT NULL DEFAULT '' COMMENT '邮箱',
  `balance` decimal(16,4) NOT NULL DEFAULT 0.00 COMMENT '管理员可支配余额（平台资金账户）',
  `realname` varchar(10) NOT NULL DEFAULT '' COMMENT '真实姓名',
  `mobile` varchar(30) NOT NULL DEFAULT '' COMMENT '手机号',
  `ip` varchar(128) NOT NULL DEFAULT '' COMMENT '最近IP',
  `status` tinyint NOT NULL DEFAULT 1 COMMENT '状态 1=正常',
  `avatar` varchar(255) NOT NULL DEFAULT '' COMMENT '头像',
  `create_time` int NOT NULL DEFAULT 0 COMMENT '创建时间',
  `update_time` int NOT NULL DEFAULT 0 COMMENT '更新时间',
  `delete_time` int NOT NULL DEFAULT 0 COMMENT '软删除',
  `last_time` int NOT NULL DEFAULT 0 COMMENT '最后登录时间',
  `login_count` int NOT NULL DEFAULT 0 COMMENT '登录次数',
  `last_ip` varchar(50) NOT NULL DEFAULT '' COMMENT '最后登录IP',
  `last_add` varchar(255) NOT NULL DEFAULT '' COMMENT '登录地',
  `cao_key` varchar(255) NOT NULL DEFAULT '' COMMENT 'API 操作密钥',
  `cao_ip` varchar(255) NOT NULL DEFAULT '' COMMENT 'API 操作 IP 白名单',
  `cao_pass` varchar(255) NOT NULL DEFAULT '' COMMENT 'API 操作密码',
  `user_where` varchar(50) NOT NULL DEFAULT 'pc' COMMENT '后台端类型（默认 pc）',
  `cao_save` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'API 密钥是否保存',
  `c` varchar(1000) NOT NULL DEFAULT '' COMMENT '扩展 JSON',
  `see_uids` text DEFAULT NULL COMMENT '可见会员ID范围（数据权限）',
  `auth_str` varchar(255) NOT NULL DEFAULT '' COMMENT '权限串（数据权限/授权标识）',
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='后台管理员' AUTO_INCREMENT=2;

-- ---------------------------------------------------------------------------
-- [P0] mks_admin_role — 后台角色：role 权限串(菜单+按钮)、层级
-- ---------------------------------------------------------------------------
CREATE TABLE `mks_admin_role` (
  `id` int NOT NULL AUTO_INCREMENT COMMENT '角色ID',
  `name` varchar(255) NOT NULL DEFAULT '' COMMENT '角色名称',
  `value` varchar(255) NOT NULL DEFAULT '' COMMENT '角色标识（Admin）',
  `create_time` int NOT NULL COMMENT '创建时间',
  `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '状态',
  `role` text DEFAULT NULL COMMENT '权限串：逗号分隔的 admin_router.id 列表；"*"=超级管理员',
  `btnRolesCheckedList` varchar(255) NOT NULL DEFAULT '' COMMENT '按钮权限标识串（read/write/delete:模块）',
  `delete_time` int NOT NULL DEFAULT 0 COMMENT '软删除',
  `level` tinyint(1) NOT NULL DEFAULT 1 COMMENT '角色层级（1=最高）',
  `mark` varchar(255) NOT NULL DEFAULT '' COMMENT '备注',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='后台角色' AUTO_INCREMENT=2;

-- ---------------------------------------------------------------------------
-- [P0] mks_admin_router — 后台菜单树 + 接口权限表（一行=菜单节点或一个 API 权限点）
-- ---------------------------------------------------------------------------
CREATE TABLE `mks_admin_router` (
  `id` int NOT NULL AUTO_INCREMENT COMMENT '节点ID',
  `parentId` int NOT NULL DEFAULT 0 COMMENT '父节点ID（0=顶级）',
  `title` varchar(255) NOT NULL DEFAULT '' COMMENT '菜单/权限中文名',
  `sort` int NOT NULL DEFAULT 100 COMMENT '排序（同层越大越前）',
  `name` varchar(255) NOT NULL DEFAULT '' COMMENT '前端路由 name（唯一索引 routername；接口节点用数字字符串占位）',
  `path` varchar(255) NOT NULL DEFAULT '' COMMENT '前端路由 path（菜单节点有效，接口节点为空）',
  `icon` varchar(255) NOT NULL DEFAULT '' COMMENT '图标类名（line-icon）',
  `component` varchar(255) NOT NULL DEFAULT '' COMMENT '前端组件路径（@/views/...），Layout=布局容器',
  `redirect` varchar(255) NOT NULL DEFAULT '' COMMENT '重定向地址',
  `hidden` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否在菜单中隐藏（1=隐藏，如编辑页）',
  `levelHidden` tinyint(1) NOT NULL DEFAULT 0 COMMENT '层级隐藏',
  `tabHidden` tinyint(1) NOT NULL DEFAULT 0 COMMENT '隐藏标签页',
  `badge` varchar(255) NOT NULL DEFAULT '' COMMENT '角标文本',
  `noColumn` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否不参与列设置',
  `noClosable` tinyint(1) NOT NULL DEFAULT 0 COMMENT '标签页不可关闭',
  `target` varchar(255) NOT NULL DEFAULT '' COMMENT '打开方式（_blank 等）',
  `activeMenu` varchar(255) NOT NULL DEFAULT '' COMMENT '高亮父菜单（隐藏页用）',
  `dot` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否显示小红点',
  `dynamicNewTab` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否动态新标签',
  `breadcrumbHidden` tinyint(1) NOT NULL DEFAULT 0 COMMENT '面包屑隐藏',
  `noKeepAlive` tinyint(1) NOT NULL DEFAULT 0 COMMENT '不缓存页面',
  `isCustomSvg` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否自定义 svg 图标',
  `mode` varchar(20) NOT NULL DEFAULT 'allOf' COMMENT '权限模式（全部 413 行为 allOf，即需全部满足；推断另有 anyOf）',
  `auth_type` tinyint(1) NOT NULL DEFAULT 1 COMMENT '节点类型：1=菜单节点 2=接口权限点',
  `api_url` varchar(100) NOT NULL DEFAULT '' COMMENT '接口地址（auth_type=2 时填写，如 goods/index）',
  `methods` varchar(10) NOT NULL DEFAULT '' COMMENT 'HTTP 方法（GET/POST）',
  `delete_time` int NOT NULL DEFAULT 0 COMMENT '软删除',
  UNIQUE KEY `routername` (`name`),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='后台菜单树 + 接口权限表（一行=菜单节点或一个 API 权限点）' AUTO_INCREMENT=429;

-- ---------------------------------------------------------------------------
-- [P0] mks_admin_login_status — 管理员在线会话
-- ---------------------------------------------------------------------------
CREATE TABLE `mks_admin_login_status` (
  `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT '主键ID（自增）',
  `uid` int NOT NULL DEFAULT 0 COMMENT '会员ID → member.id',
  `ip` varchar(128) NOT NULL DEFAULT '' COMMENT 'IP 地址',
  `ip_add` varchar(255) NOT NULL DEFAULT '' COMMENT 'IP 归属地',
  `ua` varchar(1000) NOT NULL DEFAULT '' COMMENT 'User-Agent',
  `md5` varchar(32) NOT NULL DEFAULT '' COMMENT '内容 MD5（幂等/去重）',
  `create_time` int NOT NULL DEFAULT 0 COMMENT '创建时间（int 时间戳）',
  `expire_time` int NOT NULL DEFAULT 0 COMMENT '过期/失效时间',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='管理员在线会话';

-- ---------------------------------------------------------------------------
-- [P0] mks_admin_log — 管理员操作日志
-- ---------------------------------------------------------------------------
CREATE TABLE `mks_admin_log` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT '主键ID（自增）',
  `uid` int DEFAULT NULL COMMENT '会员ID → member.id',
  `title` varchar(255) NOT NULL COMMENT '标题',
  `ip` varchar(30) DEFAULT NULL COMMENT 'IP 地址',
  `ip_add` varchar(255) DEFAULT '' COMMENT 'IP 归属地',
  `ip_all` varchar(255) NOT NULL DEFAULT '' COMMENT 'IP 归属地（完整）',
  `type` tinyint(1) DEFAULT 0 COMMENT '类型标识（区分同表多种用途，码值见模块说明）',
  `change_type` int DEFAULT 0 COMMENT '变动类型/变更类型',
  `mark` text NOT NULL COMMENT '标识/备注（短文本，代码中作为事件键或备注）',
  `create_time` int DEFAULT NULL COMMENT '创建时间（int 时间戳）',
  `update_time` int DEFAULT NULL COMMENT '更新时间（int 时间戳）',
  PRIMARY KEY (`id`),
  KEY `admin_log_admin_id` (`uid`),
  KEY `admin_log_admin_uid` (`uid`,`type`),
  KEY `admin_log_admin_uid_type` (`uid`,`type`,`change_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='管理员操作日志';

-- ---------------------------------------------------------------------------
-- [P0] mks_config — 系统配置项（键值+控件元数据，按 group 分组，支持分站 vip_id 覆盖）
-- ---------------------------------------------------------------------------
CREATE TABLE `mks_config` (
  `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT '配置ID',
  `vip_id` int NOT NULL DEFAULT 0 COMMENT '分站ID（0=主站配置；分站可覆盖同名配置）',
  `name` varchar(100) NOT NULL DEFAULT '' COMMENT '配置键（代码中 config(“xxx”) 读取）',
  `info` varchar(255) NOT NULL DEFAULT '' COMMENT '配置中文名（后台表单标签）',
  `value` longtext NOT NULL COMMENT '配置值（longtext，JSON 或纯文本）',
  `type` varchar(255) NOT NULL DEFAULT 'text' COMMENT '控件类型：text/textarea/select/radio/checkbox/switch/number/upload/editor/diyTable',
  `input_type` varchar(20) NOT NULL DEFAULT 'input' COMMENT '输入类型（默认 input）',
  `group` varchar(50) NOT NULL DEFAULT '' COMMENT '配置分组（后台 Tab）',
  `parameter` varchar(1000) NOT NULL DEFAULT '' COMMENT '候选项 JSON（select/radio/checkbox 用，label+value）',
  `upload_type` tinyint(1) unsigned NOT NULL DEFAULT 1 COMMENT '上传类型（1=图片 2=文件…）',
  `required` varchar(255) NOT NULL DEFAULT '' COMMENT '是否必填',
  `width` int unsigned NOT NULL DEFAULT 0 COMMENT '表单宽度',
  `desc` varchar(255) NOT NULL DEFAULT '' COMMENT '说明/提示',
  `high` int unsigned NOT NULL DEFAULT 0 COMMENT '高度',
  `sort` int unsigned NOT NULL DEFAULT 0 COMMENT '排序',
  `props` text DEFAULT NULL COMMENT '扩展属性 JSON',
  `status` tinyint(1) unsigned NOT NULL DEFAULT 0 COMMENT '是否启用',
  `delete_time` int NOT NULL DEFAULT 0 COMMENT '软删除',
  `son_names` text DEFAULT NULL COMMENT '子项名称（diyTable 多列控件用）',
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`,`vip_id`),
  KEY `vip_id` (`vip_id`),
  KEY `group` (`group`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='系统配置项（键值+控件元数据，按 group 分组，支持分站 vip_id 覆盖）' AUTO_INCREMENT=182;

-- ---------------------------------------------------------------------------
-- [P0] mks_member — 会员主表：账号、余额/佣金/供货款多钱包、等级、上下级、API密钥、实名
-- ---------------------------------------------------------------------------
CREATE TABLE `mks_member` (
  `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT '会员ID（自增从 10000 起，对应 config.reg.default_member_id，“用户编号”即此值）',
  `merchant_id` int NOT NULL DEFAULT 0 COMMENT '所属商户ID（供货商体系，0=非商户）',
  `group_id` varchar(50) NOT NULL DEFAULT '1' COMMENT '会员分组ID → member_group.id',
  `nickname` varchar(50) NOT NULL DEFAULT '' COMMENT '昵称',
  `username` varchar(80) NOT NULL COMMENT '登录账号（唯一索引）',
  `sex` enum('0','1','2') NOT NULL DEFAULT '0' COMMENT '性别 enum(''0'',''1'',''2'')：0=未知 1=男 2=女',
  `profile` varchar(255) NOT NULL DEFAULT '' COMMENT '个人简介',
  `password` varchar(32) NOT NULL DEFAULT '' COMMENT '登录密码（32位，MD5/salt 组合，见 salt）',
  `password_open` tinyint(1) NOT NULL DEFAULT 1 COMMENT '是否启用密码登录',
  `trade_password` varchar(255) NOT NULL DEFAULT '' COMMENT '交易密码（提现/改价二次验证）',
  `trade_password_open` tinyint(1) NOT NULL DEFAULT 1 COMMENT '交易密码开关',
  `trade_password_list` varchar(255) NOT NULL DEFAULT '1,2,3,4' COMMENT '需要交易密码的场景集合（如 1,2,3,4，对应 config.user.user_pawssord_list_set）',
  `email` varchar(60) NOT NULL DEFAULT '' COMMENT '邮箱',
  `salt` varchar(20) NOT NULL DEFAULT '' COMMENT '密码盐',
  `user_where` varchar(255) NOT NULL DEFAULT 'pc' COMMENT '注册来源端（pc/h5/app/xcx）',
  `balance` decimal(16,4) NOT NULL DEFAULT 0.00 COMMENT '账户余额（可用）',
  `wallet_address` varchar(255) DEFAULT NULL COMMENT 'USDT/钱包地址',
  `wallet_money` decimal(16,4) NOT NULL DEFAULT 0 COMMENT '钱包余额（数字货币账户）',
  `wallet_freez_money` decimal(16,4) NOT NULL DEFAULT 0 COMMENT '钱包冻结金额',
  `warn` decimal(16,4) NOT NULL DEFAULT 0 COMMENT '余额预警阈值',
  `warn_time` int NOT NULL DEFAULT 0 COMMENT '预警时间',
  `birthday` varchar(50) NOT NULL DEFAULT '0000-00-00' COMMENT '生日',
  `reg_ip` varchar(50) NOT NULL DEFAULT '' COMMENT '注册IP',
  `reg_host` varchar(255) NOT NULL DEFAULT '' COMMENT '注册来源域名',
  `login_count` int NOT NULL DEFAULT 0 COMMENT '登录次数',
  `last_time` int unsigned NOT NULL DEFAULT 0 COMMENT '最后登录时间',
  `last_ip` varchar(50) NOT NULL DEFAULT '0' COMMENT '最后登录IP',
  `last_add` varchar(255) NOT NULL DEFAULT '' COMMENT '最后登录IP归属地',
  `qq` varchar(20) NOT NULL DEFAULT '' COMMENT 'QQ',
  `address` varchar(255) NOT NULL DEFAULT '' COMMENT '地址（文本）',
  `mobile` varchar(30) NOT NULL DEFAULT '' COMMENT '手机号',
  `mobile_code` varchar(20) NOT NULL DEFAULT '86' COMMENT '手机国际区号（默认 86）',
  `avatar` varchar(255) NOT NULL COMMENT '头像',
  `level_id` int NOT NULL DEFAULT 1 COMMENT '会员等级ID → member_level.id',
  `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '会员状态：1=正常 0=禁用',
  `token` varchar(500) NOT NULL DEFAULT '' COMMENT '登录 token',
  `token1` varchar(500) NOT NULL DEFAULT '' COMMENT '第二 token（多端登录）',
  `vip_id` int NOT NULL DEFAULT 0 COMMENT '所属分站ID',
  `pid` int NOT NULL DEFAULT 0 COMMENT '上级会员ID（推荐人，分销树）',
  `child_num` int NOT NULL DEFAULT 0 COMMENT '下级人数（缓存）',
  `score` int NOT NULL DEFAULT 0 COMMENT '积分',
  `create_time` int unsigned NOT NULL DEFAULT 0 COMMENT '注册时间',
  `update_time` int NOT NULL DEFAULT 0 COMMENT '更新时间',
  `delete_time` int NOT NULL DEFAULT 0 COMMENT '软删除时间',
  `province` varchar(200) NOT NULL DEFAULT '' COMMENT '省份',
  `city` varchar(200) NOT NULL DEFAULT '' COMMENT '城市',
  `roles` varchar(255) NOT NULL DEFAULT '1' COMMENT '前台角色ID → member_role.id',
  `commission_money` decimal(16,4) NOT NULL DEFAULT 0.00 COMMENT '佣金余额（可提现）',
  `total_recharge_money` decimal(16,4) NOT NULL DEFAULT 0.00 COMMENT '累计充值金额（升级/等级判定用）',
  `total_commission_money` decimal(16,4) NOT NULL DEFAULT 0.00 COMMENT '累计佣金总额',
  `freez_money` decimal(16,4) NOT NULL DEFAULT 0.00 COMMENT '冻结余额（下单占用/审核中）',
  `cost` decimal(16,5) NOT NULL DEFAULT 0.00 COMMENT '累计消费额',
  `freez_commission_money` decimal(16,4) NOT NULL DEFAULT 0.00 COMMENT '冻结佣金',
  `supply_money` decimal(16,4) NOT NULL DEFAULT 0.00 COMMENT '供货商货款余额',
  `owner_goods` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否拥有商品（供货商权限）',
  `owner_check` tinyint(1) NOT NULL DEFAULT 0 COMMENT '供货商审核状态',
  `income_order` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否开启 API 下单权限',
  `income_app` varchar(255) NOT NULL DEFAULT '' COMMENT 'API 应用标识',
  `income_key` varchar(255) NOT NULL DEFAULT '' COMMENT 'API 密钥（config.reg.open_user_api_show 控制默认开启）',
  `income_ip` varchar(2000) NOT NULL DEFAULT '' COMMENT 'API IP 白名单',
  `income_white` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否启用 IP 白名单',
  `income_use_ip` varchar(2000) NOT NULL DEFAULT '' COMMENT 'API 实际使用 IP 记录',
  `has_vip` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否已开通分站',
  `has_merchant` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否已开通商户/供货商',
  `freez_supply_money` decimal(16,4) NOT NULL DEFAULT 0.00 COMMENT '冻结货款',
  `card_no` varchar(255) NOT NULL DEFAULT '' COMMENT '身份证号/卡号（实名）',
  `real_name` varchar(255) NOT NULL DEFAULT '' COMMENT '真实姓名（实名）',
  `is_one_recharge` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否首充（首充活动标记）',
  `goods_notify_url` varchar(255) NOT NULL DEFAULT '' COMMENT '商品变更通知 URL',
  `income_save` tinyint NOT NULL DEFAULT 0 COMMENT 'API 权限保存标记',
  `mark` varchar(255) NOT NULL DEFAULT '' COMMENT '后台备注',
  `c` varchar(1000) NOT NULL DEFAULT '' COMMENT '扩展 JSON（历史字段）',
  `close` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否关闭（禁止登录）',
  `ensure_money` decimal(16,4) NOT NULL DEFAULT 0.0000 COMMENT '保证金',
  `is_pay_certify` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否已实名/支付认证',
  `seal_time` int NOT NULL DEFAULT 0 COMMENT '封号时间',
  PRIMARY KEY (`id`),
  KEY `merchant_id` (`merchant_id`,`delete_time`),
  KEY `group_id` (`group_id`,`delete_time`),
  KEY `level_id` (`level_id`),
  UNIQUE KEY `username` (`username`),
  KEY `mobile` (`mobile`),
  KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='会员主表' AUTO_INCREMENT=10000;

-- ---------------------------------------------------------------------------
-- [P0] mks_member_level — 会员等级：升级门槛、折扣率、两级佣金比例、升级条件
-- ---------------------------------------------------------------------------
CREATE TABLE `mks_member_level` (
  `id` smallint unsigned NOT NULL AUTO_INCREMENT COMMENT '等级ID（会员默认 level_id=1）',
  `name` varchar(30) DEFAULT '' COMMENT '等级名称',
  `amount` decimal(16,4) DEFAULT 0 COMMENT '升级所需累计消费/充值金额门槛',
  `pay_rate` decimal(4,2) NOT NULL DEFAULT 0.00 COMMENT '支付折扣率或手续费率',
  `can_up` tinyint(1) NOT NULL DEFAULT 1 COMMENT '是否允许升级',
  `thumb` varchar(255) DEFAULT NULL COMMENT '等级图标',
  `is_default` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否默认等级（注册即此级）',
  `description` varchar(200) DEFAULT NULL COMMENT '等级说明',
  `create_time` int DEFAULT 0 COMMENT '创建时间',
  `update_time` int DEFAULT 0 COMMENT '更新时间',
  `delete_time` int DEFAULT 0 COMMENT '软删除',
  `one_rate` tinyint NOT NULL DEFAULT 0 COMMENT '一级佣金比例（%）',
  `two_rate` tinyint NOT NULL DEFAULT 0 COMMENT '二级佣金比例（%）',
  `invite_num` int DEFAULT 0 COMMENT '升级所需邀请人数',
  `commission_check` decimal(16,4) DEFAULT 0 COMMENT '升级所需累计佣金',
  `total_commission_check` decimal(16,4) DEFAULT 0 COMMENT '升级所需团队累计佣金',
  `is_up` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否自动升级',
  `consumption_money` decimal(16,4) DEFAULT 0 COMMENT '升级所需消费额',
  `recharge_money` decimal(16,4) DEFAULT 0 COMMENT '升级所需充值额',
  `limit_price` decimal(16,4) NOT NULL DEFAULT 0 COMMENT '等级最低下单金额限制',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='会员等级' AUTO_INCREMENT=4;

-- ---------------------------------------------------------------------------
-- [P0] mks_member_group — 会员分组（后台分组筛选与权限规则 rules）
-- ---------------------------------------------------------------------------
CREATE TABLE `mks_member_group` (
  `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT '主键ID（自增）',
  `name` varchar(50) DEFAULT '' COMMENT '名称',
  `rules` mediumtext DEFAULT NULL COMMENT '规则集合',
  `create_time` int DEFAULT NULL COMMENT '创建时间（int 时间戳）',
  `update_time` int DEFAULT NULL COMMENT '更新时间（int 时间戳）',
  `status` tinyint(1) DEFAULT 1 COMMENT '状态机标识（1=正常/启用，0=关闭；各表具体码值含义见 §8.2）',
  `delete_time` int DEFAULT 0 COMMENT '软删除时间（0=未删除）',
  PRIMARY KEY (`id`),
  KEY `name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='会员分组（后台分组筛选与权限规则 rules）' AUTO_INCREMENT=2;

-- ---------------------------------------------------------------------------
-- [P0] mks_member_role — 前台角色表（默认用户角色，role=*）
-- ---------------------------------------------------------------------------
CREATE TABLE `mks_member_role` (
  `id` int NOT NULL AUTO_INCREMENT COMMENT '主键ID（自增）',
  `name` varchar(255) NOT NULL DEFAULT '' COMMENT '名称',
  `value` varchar(255) NOT NULL DEFAULT '' COMMENT '值（配置值/属性值）',
  `create_time` datetime NOT NULL COMMENT '创建时间（int 时间戳）',
  `role` text NOT NULL COMMENT '权限串（* = 全部）',
  `is_default` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否默认项',
  `btnRolesCheckedList` varchar(255) NOT NULL DEFAULT '' COMMENT '按钮级权限标识串（动作:模块）',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='前台角色表（默认用户角色，role=*）' AUTO_INCREMENT=2;

-- ---------------------------------------------------------------------------
-- [P0] mks_member_login_status — 会员在线会话（登录态 md5 + 过期时间）
-- ---------------------------------------------------------------------------
CREATE TABLE `mks_member_login_status` (
  `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT '主键ID（自增）',
  `uid` int NOT NULL DEFAULT 0 COMMENT '会员ID → member.id',
  `ip` varchar(128) NOT NULL DEFAULT '' COMMENT 'IP 地址',
  `ip_add` varchar(255) NOT NULL DEFAULT '' COMMENT 'IP 归属地',
  `ua` varchar(1000) NOT NULL DEFAULT '' COMMENT 'User-Agent',
  `md5` varchar(32) NOT NULL DEFAULT '' COMMENT '内容 MD5（幂等/去重）',
  `create_time` int NOT NULL COMMENT '创建时间（int 时间戳）',
  `expire_time` int NOT NULL DEFAULT 0 COMMENT '过期/失效时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `MD5` (`md5`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='会员在线会话（登录态 md5 + 过期时间）';

-- ---------------------------------------------------------------------------
-- [P0] mks_goods — 商品主表：卡密/虚拟/卡券三类商品的全部售卖属性、定价方式、对接与自动补单配置
-- ---------------------------------------------------------------------------
CREATE TABLE `mks_goods` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT '商品ID（bigint，与订单/卡密/库存/收藏关联）',
  `goods_no` varchar(20) NOT NULL DEFAULT '' COMMENT '商品编号（前台展示、对接外部渠道时用）',
  `goods_name` varchar(1000) NOT NULL DEFAULT '' COMMENT '商品名称（前台标题，varchar1000 支持长标题）',
  `goods_short_name` varchar(255) NOT NULL DEFAULT '' COMMENT '商品简称（列表紧凑展示）',
  `goods_name_blod_flag` tinyint(1) NOT NULL DEFAULT 0 COMMENT '标题加粗 1=加粗',
  `goods_name_color` varchar(10) NOT NULL DEFAULT '' COMMENT '标题颜色（如 #ff0000）',
  `ishot` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否热销标记（前台 hot 角标）',
  `goods_type` tinyint(1) NOT NULL DEFAULT 1 COMMENT '商品类型：1=卡密商品 2=虚拟商品 3=实物商品',
  `start_count` int NOT NULL DEFAULT 1 COMMENT '单次最少购买数量',
  `end_count` int NOT NULL DEFAULT 1 COMMENT '单次最多购买数量',
  `num_count` varchar(255) NOT NULL DEFAULT '1' COMMENT '可选购买数量档位（逗号分隔，如 1,2,5,10）',
  `num_type` tinyint(1) NOT NULL DEFAULT 1 COMMENT '下单数量控件类型（下拉/步进，推断）',
  `num_input_type` tinyint(1) NOT NULL DEFAULT 1 COMMENT '数量输入方式（手输/选择，推断）',
  `multiple` int NOT NULL DEFAULT 0 COMMENT '购买数量倍数限制（必须为该值的整数倍，0=不限）',
  `face_value` decimal(14,4) NOT NULL DEFAULT 0.000 COMMENT '面值/原价（前台划线价）',
  `real_price` decimal(16,4) NOT NULL DEFAULT 0.000 COMMENT '实际售价（未开启会员价时的基准售价）',
  `cost_value` decimal(16,5) NOT NULL DEFAULT 0.00 COMMENT '成本价（对接渠道进价，用于利润计算，5 位小数）',
  `validdate_flag` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否有有效期限制 1=有',
  `validdate_value` int NOT NULL DEFAULT 0 COMMENT '有效期天数（下单后多少天失效）',
  `goods_info` text NOT NULL COMMENT '商品详情（富文本 HTML）',
  `goods_notice` text NOT NULL COMMENT '购买须知（富文本）',
  `order_notice` text NOT NULL COMMENT '下单提示/公告（富文本）',
  `recharge_url` varchar(255) NOT NULL DEFAULT '' COMMENT '充值/兑换跳转地址（虚拟商品需要用户去充值时的链接）',
  `url_pwd` varchar(255) NOT NULL DEFAULT '' COMMENT '跳转地址密码',
  `demo_url` varchar(255) NOT NULL DEFAULT '' COMMENT '演示地址',
  `recharge_time_type` tinyint(1) NOT NULL DEFAULT 0 COMMENT '充值耗时单位类型（分/时/天）',
  `recharge_time_value` int NOT NULL DEFAULT 0 COMMENT '充值耗时数值（前台展示的预计到账时间）',
  `order_tpl_type` tinyint(1) NOT NULL DEFAULT 0 COMMENT '下单模板来源：0=默认, 1=自定义模板（推断）',
  `order_tpl_id` int NOT NULL DEFAULT 0 COMMENT '绑定的下单模板 ID → order_tpl.id',
  `create_time` int NOT NULL DEFAULT 0 COMMENT '创建时间（int 时间戳）',
  `update_time` int NOT NULL DEFAULT 0 COMMENT '更新时间',
  `delete_time` int NOT NULL DEFAULT 0 COMMENT '软删除时间（0=未删）',
  `is_del` tinyint(1) NOT NULL DEFAULT 0 COMMENT '删除标记（与 delete_time 并存的历史遗留冗余字段）',
  `uid` int NOT NULL DEFAULT 0 COMMENT '创建者/供货商会员ID（0=平台自营）',
  `vip_id` int NOT NULL DEFAULT 0 COMMENT '分站ID（0=主站；分站商品隔离）',
  `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '商品状态：1=销售中 2=暂停中 3=已下架 6=已售罄 7=已预警 8=回收站（审核见 check_status）',
  `stop_reason` varchar(255) NOT NULL DEFAULT '' COMMENT '下架原因',
  `goods_imgs` varchar(2000) NOT NULL DEFAULT '' COMMENT '商品轮播图（多图，逗号/JSON 分隔的 URL 串）',
  `goods_img` varchar(500) NOT NULL DEFAULT '' COMMENT '商品主图',
  `provide_type` tinyint(1) NOT NULL DEFAULT 1 COMMENT '供货方式：1=自营 5=供货 6=渠道',
  `category_ids` text NOT NULL COMMENT '所属分类ID集合（text，逗号分隔，多分类冗余）',
  `tags_id` int NOT NULL DEFAULT 0 COMMENT '品牌/属性标签ID → goods_tags.id',
  `check_status` tinyint(1) NOT NULL DEFAULT 3 COMMENT '审核状态：4=待审核 9=已审核 5=已拒绝（原库默认 3 为历史遗留，建议默认 4）',
  `check_content` varchar(100) NOT NULL DEFAULT '' COMMENT '审核意见/驳回原因',
  `sales_num` int unsigned NOT NULL DEFAULT 0 COMMENT '真实销量',
  `virtual_num` int unsigned NOT NULL DEFAULT 0 COMMENT '虚拟销量（前台展示用）',
  `browse` int NOT NULL DEFAULT 0 COMMENT '浏览量',
  `stock_num` int unsigned NOT NULL DEFAULT 0 COMMENT '总库存数（冗余汇总，用于列表快速展示与售罄判断）',
  `have_goods_price` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否启用会员价表 1=启用',
  `goods_price_id` int NOT NULL DEFAULT 0 COMMENT '定价模板ID → goods_ready_price.id（按模板批量算价）',
  `goods_price_type` tinyint NOT NULL DEFAULT 0 COMMENT '定价方式：0=手动, 1=定价模板（推断）',
  `sku_type` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否多规格 0=单规格 1=多规格',
  `sku_id` varchar(255) NOT NULL DEFAULT '0' COMMENT '规格组合ID集合',
  `sort` int NOT NULL DEFAULT 0 COMMENT '排序值（越大越前）',
  `stock_auto_get` tinyint(1) NOT NULL DEFAULT 1 COMMENT '库存是否自动获取（对接渠道自动同步库存）',
  `take_up_time` int NOT NULL DEFAULT 0 COMMENT '上架/开售时间',
  `goods_tags` varchar(1000) NOT NULL DEFAULT '' COMMENT '前台角标文本（如“热卖”“新品”）',
  `rate_value` decimal(5,2) NOT NULL DEFAULT 0.00 COMMENT '供货商费率%（平台从该商品订单抽取的比例）',
  `stock_set_price` tinyint(1) NOT NULL DEFAULT 1 COMMENT '库存是否影响价格（不同成本档位对应不同售价）',
  `sync_time` int NOT NULL DEFAULT 0 COMMENT '最近一次与对接渠道同步的时间',
  `sync_config` text DEFAULT NULL COMMENT '同步配置 JSON（同步哪些字段、是否自动补单等）',
  `card_option` varchar(255) NOT NULL DEFAULT '' COMMENT '卡密发货选项（是否显示卡号/卡密等）',
  `only_balance` tinyint(1) NOT NULL DEFAULT 0 COMMENT '仅允许余额支付 1=是',
  `docking_auto_bu` tinyint(1) NOT NULL DEFAULT 0 COMMENT '对接渠道是否允许自动补单',
  `auto_bu_back` tinyint(1) NOT NULL DEFAULT 0 COMMENT '自动补单失败是否回退订单',
  `docking_order_bu` tinyint(1) NOT NULL DEFAULT 0 COMMENT '渠道下单失败是否自动补单',
  `auto_order_time` int NOT NULL DEFAULT 0 COMMENT '自动补单间隔时间',
  `auto_bu_time` int NOT NULL DEFAULT 0 COMMENT '最近自动补单时间',
  `auto_bu_count` int NOT NULL DEFAULT 0 COMMENT '自动补单次数',
  `order_bu_back` tinyint(1) NOT NULL DEFAULT 0 COMMENT '普通补单失败回退开关',
  `order_bu_count` int NOT NULL DEFAULT 1 COMMENT '补单次数上限',
  `temp_id` int NOT NULL DEFAULT 0 COMMENT '关联模板ID（商品模板/顺序推断）',
  `is_postage` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否包邮 1=包邮（实物/卡券商品）',
  `spec_type` tinyint(1) NOT NULL DEFAULT 0 COMMENT '规格类型（单/多规格，与 sku_type 并存）',
  `goods_video` varchar(1000) NOT NULL DEFAULT '' COMMENT '商品视频地址',
  `postage` decimal(13,2) NOT NULL DEFAULT 0.00 COMMENT '运费金额',
  `freight` tinyint(1) NOT NULL DEFAULT 1 COMMENT '运费模板ID → goods_freight.id（该表不在 SQL 内，见“运行期建表”）',
  `status_time` int NOT NULL DEFAULT 0 COMMENT '状态变更时间',
  `need_balance` decimal(16,4) NOT NULL DEFAULT 0 COMMENT '下单所需最低余额门槛',
  `need_balance_tip` varchar(255) NOT NULL DEFAULT '' COMMENT '余额门槛提示文案',
  `need_client` varchar(255) NOT NULL DEFAULT '' COMMENT '限制客户端（如仅 APP 可买）',
  `show_client` varchar(255) NOT NULL DEFAULT '' COMMENT '展示客户端限制',
  `can_back` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否允许退款 1=允许',
  `rebate_type` tinyint(1) NOT NULL DEFAULT 0 COMMENT '返现类型（推断：按比例/固定额）',
  `is_rebate` tinyint(1) NOT NULL DEFAULT 1 COMMENT '是否参与返现 1=参与',
  `is_point` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否赠送积分',
  `point` tinyint(1) NOT NULL DEFAULT 2 COMMENT '积分值/积分类型',
  `is_sync_channel` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否同步到分站渠道',
  `is_batch` tinyint(1) NOT NULL DEFAULT 1 COMMENT '是否支持批量下单 1=支持',
  `can_level` varchar(255) NOT NULL DEFAULT '' COMMENT '可见等级集合（会员等级ID，逗号分隔）',
  `can_price` decimal(16,4) NOT NULL DEFAULT 0 COMMENT '指定等级可见价',
  `can_price_level` varchar(255) NOT NULL DEFAULT '' COMMENT '可见价对应的等级集合',
  `can_buy` varchar(255) NOT NULL DEFAULT '' COMMENT '可购买会员等级集合',
  `can_no_buy` varchar(255) NOT NULL DEFAULT '' COMMENT '禁止购买会员等级集合',
  `set_config` text DEFAULT NULL COMMENT '扩展配置 JSON（预留）',
  `warn_num` int NOT NULL DEFAULT 0 COMMENT '库存预警数量',
  `is_recharge` int NOT NULL DEFAULT 0 COMMENT '是否充值类商品（下单后需跳充值）',
  `is_premium` int DEFAULT 0 COMMENT '是否精品/优选标记',
  `is_difference` int NOT NULL DEFAULT 0 COMMENT '是否差价商品（补差价专用）',
  `is_iso_level` int NOT NULL DEFAULT 0 COMMENT '是否按等级隔离价格',
  `goods_bei` int NOT NULL DEFAULT 0 COMMENT '商品倍数（价格/数量换算系数）',
  `can_api_see` tinyint NOT NULL DEFAULT 0 COMMENT '是否允许 API 接口查询到该商品',
  `is_real_name` tinyint(1) NOT NULL DEFAULT 0 COMMENT '购买是否需要实名',
  `order_bu_time` int NOT NULL DEFAULT 0 COMMENT '上次补单时间',
  `json_config` text DEFAULT NULL COMMENT '商品扩展 JSON 配置（新版字段，与 set_config/sync_config 并存）',
  PRIMARY KEY (`id`),
  KEY `good_default` (`delete_time`,`status`,`check_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='商品主表';

-- ---------------------------------------------------------------------------
-- [P0] mks_goods_category — 商品分类（多级树）：前台目录、二级分类、广告图与跳转配置
-- ---------------------------------------------------------------------------
CREATE TABLE `mks_goods_category` (
  `id` int NOT NULL AUTO_INCREMENT COMMENT '主键ID（自增）',
  `vip_id` int NOT NULL DEFAULT 0 COMMENT '分站ID（0=主站）',
  `name` varchar(255) NOT NULL DEFAULT '' COMMENT '名称',
  `info` text DEFAULT NULL COMMENT '说明/信息',
  `color` varchar(10) NOT NULL DEFAULT '' COMMENT '颜色值',
  `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '状态机标识（1=正常/启用，0=关闭；各表具体码值含义见 §8.2）',
  `pid` int NOT NULL DEFAULT 0 COMMENT '父级ID（树形结构父节点，0=顶级）',
  `has_child` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否有子级（冗余，用于快速渲染树）',
  `create_time` int NOT NULL DEFAULT 0 COMMENT '创建时间（int 时间戳）',
  `update_time` int NOT NULL DEFAULT 0 COMMENT '更新时间（int 时间戳）',
  `sort` int NOT NULL DEFAULT 0 COMMENT '排序值（越大越前，或正序，按模块实现）',
  `img` varchar(500) NOT NULL DEFAULT '' COMMENT '图片地址',
  `istop` int NOT NULL DEFAULT 0 COMMENT '是否置顶',
  `img2` varchar(500) NOT NULL DEFAULT '' COMMENT '第二张图片（选中态/移动端图）',
  `short_name` varchar(255) NOT NULL DEFAULT '' COMMENT '简称/短标题',
  `docking_id` int NOT NULL DEFAULT 0 COMMENT '对接供货渠道ID → docking.id',
  `docking_cate_id` varchar(255) NOT NULL DEFAULT '0' COMMENT '对接渠道分类ID（渠道侧分类）',
  `cate_type` tinyint(1) NOT NULL DEFAULT 2 COMMENT '分类类型（默认 2，码值见模块说明）',
  `level` tinyint(1) NOT NULL DEFAULT 0 COMMENT '层级/级别',
  `path` varchar(255) NOT NULL DEFAULT '' COMMENT '路径（路由 path / 层级路径）',
  `ad_img` varchar(500) NOT NULL DEFAULT '' COMMENT '分类页广告图',
  `ad_url` varchar(500) NOT NULL DEFAULT '' COMMENT '分类页广告跳转地址',
  `badge` varchar(255) NOT NULL DEFAULT '' COMMENT '角标文本',
  `badge_color` varchar(20) NOT NULL DEFAULT '#ff0000' COMMENT '角标颜色',
  `tip` varchar(255) NOT NULL DEFAULT '' COMMENT '提示文案',
  `open_dir` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否展开子级/目录型分类',
  `goods_num` int NOT NULL DEFAULT 0 COMMENT '商品数量',
  `is_jump_url` tinyint(1) DEFAULT 0 COMMENT '是否跳转外部链接',
  `jump_url` varchar(500) DEFAULT '' COMMENT '跳转地址',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='商品分类（多级树）';

-- ---------------------------------------------------------------------------
-- [P0] mks_goods_price — 商品会员价表：按 商品×会员等级 存售价、成本、返现金额
-- ---------------------------------------------------------------------------
CREATE TABLE `mks_goods_price` (
  `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT '价格ID',
  `cash_back_amount` decimal(16,4) NOT NULL DEFAULT 0.00 COMMENT '该等级下单返现金额',
  `cost_value` decimal(16,5) NOT NULL DEFAULT 0.000 COMMENT '该等级成本',
  `goods_id` int NOT NULL COMMENT '商品ID',
  `uid` int NOT NULL DEFAULT 0 COMMENT '创建者/供货商ID',
  `level_id` int NOT NULL DEFAULT 0 COMMENT '会员等级ID',
  `price` decimal(16,4) NOT NULL DEFAULT 0.00 COMMENT '该等级售价',
  `price_type` int NOT NULL DEFAULT 0 COMMENT '价格类型：1=等级价（见 config/mks.php 与 GoodsPriceLogic）',
  `price1` decimal(16,4) NOT NULL DEFAULT 0.00 COMMENT '第二价格（划线价/原价）',
  `goods_sku_id` int NOT NULL DEFAULT 0 COMMENT '规格ID（0=全规格通用）',
  PRIMARY KEY (`id`),
  KEY `goods_price` (`goods_id`,`level_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='商品会员价表';

-- ---------------------------------------------------------------------------
-- [P0] mks_goods_ready_price — 定价模板（按价格区间批量生成各等级售价的规则）
-- ---------------------------------------------------------------------------
CREATE TABLE `mks_goods_ready_price` (
  `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT '定价模板ID',
  `vip_id` int NOT NULL DEFAULT 0 COMMENT '分站ID',
  `goods_id` int NOT NULL DEFAULT 0 COMMENT '绑定商品ID（0=通用模板）',
  `goods_sku_id` int NOT NULL DEFAULT 0 COMMENT '绑定规格ID',
  `uid` int NOT NULL DEFAULT 0 COMMENT '创建者',
  `type` tinyint(1) NOT NULL DEFAULT 2 COMMENT '模板类型 1=按价格区间（种子默认 1）',
  `start_price` decimal(16,4) NOT NULL DEFAULT 0.00 COMMENT '成本区间下限',
  `end_price` decimal(16,4) NOT NULL DEFAULT 0.00 COMMENT '成本区间上限',
  `plan_id` tinyint(1) NOT NULL DEFAULT 1 COMMENT '方案ID（1=按等级）',
  `cha_price` decimal(16,4) NOT NULL DEFAULT 0.00 COMMENT '加价金额/步长',
  `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '状态',
  `price_arr` text DEFAULT NULL COMMENT '各等级售价 JSON，如 {"1":1,"2":2,"3":3}（键=等级ID）',
  `is_default` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否默认模板',
  `name` varchar(255) NOT NULL DEFAULT '' COMMENT '模板名称',
  `level_md5` varchar(50) NOT NULL DEFAULT '' COMMENT '等级组合 md5（判重）',
  `sort` int NOT NULL DEFAULT 100 COMMENT '排序',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='定价模板（按价格区间批量生成各等级售价的规则）' AUTO_INCREMENT=2;

-- ---------------------------------------------------------------------------
-- [P0] mks_goods_stock — 商品库存/SKU 表：自营库存与对接渠道库存的统一库存行
-- ---------------------------------------------------------------------------
CREATE TABLE `mks_goods_stock` (
  `id` int NOT NULL AUTO_INCREMENT COMMENT '库存行ID（即 SKU 主键，order.stock_id 指向此）',
  `uid` int NOT NULL DEFAULT 0 COMMENT '供货商会员ID',
  `type` tinyint(1) NOT NULL DEFAULT 1 COMMENT '库存类型 1=卡密库存 2=对接库存（推断）',
  `name` varchar(255) NOT NULL DEFAULT '' COMMENT '库存/SKU 名称',
  `goods_id` int NOT NULL DEFAULT 0 COMMENT '所属商品ID → goods.id',
  `docking_id` int NOT NULL DEFAULT 0 COMMENT '来源渠道ID',
  `docking_goods_id` varchar(50) NOT NULL DEFAULT '0' COMMENT '渠道商品ID',
  `docking_goods_sku_id` varchar(255) NOT NULL DEFAULT '' COMMENT '渠道规格ID',
  `cost_value` decimal(16,5) NOT NULL DEFAULT 0.0000 COMMENT '进价（对该 SKU 的结算成本）',
  `stock_num` int unsigned NOT NULL DEFAULT 0 COMMENT '库存数量',
  `sort` int NOT NULL DEFAULT 100 COMMENT '排序',
  `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '状态 1=启用',
  `stop_status` tinyint(1) NOT NULL DEFAULT 0 COMMENT '停售状态',
  `create_time` int NOT NULL DEFAULT 0 COMMENT '创建时间',
  `card_cate_id` int NOT NULL DEFAULT 0 COMMENT '关联卡密库ID（卡密库存模式）',
  `docking_goods_info` longtext DEFAULT NULL COMMENT '渠道商品详情 JSON 快照',
  `docking_goods_info_id` int NOT NULL DEFAULT 0 COMMENT '详情快照ID → goods_stock_info.id',
  `docking_cate_id` varchar(255) NOT NULL DEFAULT '0' COMMENT '渠道分类ID',
  `docking_pcate_id` varchar(255) NOT NULL DEFAULT '0' COMMENT '渠道父分类ID',
  `sync_time` int NOT NULL DEFAULT 0 COMMENT '同步时间',
  `goods_img` varchar(500) NOT NULL DEFAULT '' COMMENT '商品图快照',
  `goods_name` varchar(500) NOT NULL DEFAULT '' COMMENT '商品名快照',
  `docking_goods_delete_status` tinyint NOT NULL DEFAULT 0 COMMENT '渠道侧是否已删除商品',
  `recharge_time_value` int NOT NULL DEFAULT 0 COMMENT '充值耗时（该 SKU）',
  `face_value` decimal(14,5) NOT NULL DEFAULT 0 COMMENT '面值',
  `sync_config` text DEFAULT NULL COMMENT '同步配置 JSON',
  `goods_type` tinyint(1) NOT NULL DEFAULT 2 COMMENT '商品类型（默认 2）',
  `docking_type` varchar(50) NOT NULL DEFAULT '' COMMENT '渠道类型',
  `sync_config_type` tinyint(1) NOT NULL DEFAULT 0 COMMENT '同步配置类型',
  `sync_goods_status` int NOT NULL DEFAULT 2 COMMENT '同步状态（默认 2）',
  `cost_value_vali` tinyint NOT NULL DEFAULT 0 COMMENT '是否启用成本区间',
  `cost_value_start` decimal(16,5) NOT NULL DEFAULT 0 COMMENT '成本区间起',
  `cost_value_end` decimal(16,5) NOT NULL DEFAULT 0 COMMENT '成本区间止',
  `goods_sku_id` int NOT NULL DEFAULT 0 COMMENT 'SKU 序号',
  `is_sync_channel` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否同步渠道',
  `auto_back_type` tinyint(1) NOT NULL DEFAULT 0 COMMENT '自动回退类型',
  `auto_back` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否自动回退',
  `auto_back_time` float NOT NULL DEFAULT 0 COMMENT '回退延迟',
  `auto_back_can_bu` tinyint(1) NOT NULL DEFAULT 1 COMMENT '回退后可否补单',
  `goods_besides_info` text DEFAULT NULL COMMENT '商品附加信息 JSON',
  `split_combine_id` int NOT NULL DEFAULT 0 COMMENT '拆分合并ID',
  `order_tpl_id` int NOT NULL DEFAULT 0 COMMENT '下单模板ID',
  `auto_back_fail_count` int NOT NULL DEFAULT 0 COMMENT '回退失败次数',
  `auto_back_fail_time` float NOT NULL DEFAULT 0 COMMENT '回退失败时间',
  `sales_time_config` varchar(250) NOT NULL DEFAULT '' COMMENT '售卖时间段配置（限时售卖，JSON/字符串）',
  `sync_docking_price` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否同步渠道价格',
  `stop_type` tinyint(1) NOT NULL DEFAULT 0 COMMENT '停售类型',
  `gong_tpl_id` int NOT NULL DEFAULT 0 COMMENT '工单模板ID',
  `goods_bei` int NOT NULL DEFAULT 0 COMMENT '倍数',
  `docking_price_open` int NOT NULL DEFAULT 0 COMMENT '是否启用渠道定价',
  `docking_price_id` int NOT NULL DEFAULT 0 COMMENT '渠道定价ID',
  `docking_add_price` decimal(16,4) DEFAULT 0 COMMENT '渠道加价',
  `old_cost_value` decimal(16,5) DEFAULT 0 COMMENT '上一次成本',
  PRIMARY KEY (`id`),
  KEY `goods_id` (`goods_id`),
  KEY `docking_id` (`docking_id`,`docking_cate_id`,`docking_goods_id`,`goods_type`),
  KEY `docking_goods_save` (`docking_id`,`docking_goods_id`,`docking_cate_id`,`recharge_time_value`,`goods_type`),
  KEY `auto_bu` (`uid`,`goods_id`,`goods_sku_id`),
  KEY `sync_goods_stock` (`docking_id`,`docking_goods_id`,`docking_goods_delete_status`,`goods_type`),
  KEY `idx_optimize_slow_query` (`docking_id`,`docking_goods_delete_status`,`docking_goods_id`,`goods_type`,`goods_id`,`sync_time`),
  KEY `sku_status` (`goods_id`,`goods_sku_id`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='商品库存/SKU 表';

-- ---------------------------------------------------------------------------
-- [P0] mks_goods_tags — 品牌/商品属性标签（前台角标、筛选）
-- ---------------------------------------------------------------------------
CREATE TABLE `mks_goods_tags` (
  `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT '标签ID',
  `uid` int NOT NULL DEFAULT 0 COMMENT '创建者',
  `name` varchar(100) NOT NULL COMMENT '标签名称',
  `short_name` varchar(100) NOT NULL COMMENT '简称',
  `sort` int NOT NULL COMMENT '排序',
  `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '状态',
  `color` varchar(10) NOT NULL DEFAULT '' COMMENT '颜色',
  `create_time` int NOT NULL DEFAULT 0 COMMENT '创建时间',
  `update_time` int NOT NULL DEFAULT 0 COMMENT '更新时间',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='品牌/商品属性标签（前台角标、筛选）';

-- ---------------------------------------------------------------------------
-- [P0] mks_card — 卡密本体表：一条卡密=卡号+卡密+归属卡库+成本+状态+发货订单
-- ---------------------------------------------------------------------------
CREATE TABLE `mks_card` (
  `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT '卡密ID',
  `goods_id` int NOT NULL DEFAULT 0 COMMENT '所属商品ID → goods.id（0=未绑定商品，仅入库）',
  `order_id` int NOT NULL DEFAULT 0 COMMENT '售出订单ID → order.id（0=未售）',
  `uid` int NOT NULL DEFAULT 0 COMMENT '购买会员ID',
  `card_no` varchar(2000) NOT NULL DEFAULT '' COMMENT '卡号（varchar2000，加密/长文本存储）',
  `card_password` varchar(2000) NOT NULL DEFAULT '' COMMENT '卡密',
  `card_cate_id` int NOT NULL DEFAULT 0 COMMENT '所属卡密库ID → card_cate.id',
  `cost` decimal(16,5) NOT NULL DEFAULT 0.0000 COMMENT '成本（4 位小数，与 decimal(10,4) 一致）',
  `end_time` bigint NOT NULL DEFAULT 0 COMMENT '有效期截止时间（bigint 时间戳，契合 goods.expire_time 用法）',
  `sale_first` int NOT NULL DEFAULT 0 COMMENT '首次上架/优先售卖标记（先进先出）',
  `docking_id` int NOT NULL DEFAULT 0 COMMENT '来源对接渠道ID',
  `docking_goods_id` varchar(50) NOT NULL DEFAULT '' COMMENT '渠道商品ID',
  `card_type` tinyint(1) NOT NULL DEFAULT 0 COMMENT '卡密类型（对应 card_cate.card_type）',
  `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '卡密状态：1=待售 2=已售 3=冻结',
  `create_time` int NOT NULL DEFAULT 0 COMMENT '入库时间',
  `update_time` int NOT NULL DEFAULT 0 COMMENT '更新时间',
  `delete_time` int NOT NULL DEFAULT 0 COMMENT '软删除时间',
  `card_show_type` tinyint(1) NOT NULL DEFAULT 1 COMMENT '卡密展示格式（卡号+卡密/仅卡号/仅卡密）',
  `history_id` int NOT NULL DEFAULT 0 COMMENT '导卡批次ID → card_history.id',
  `old_card_no` varchar(2000) NOT NULL DEFAULT '' COMMENT '变更前卡号（换卡留痕）',
  `old_card_password` varchar(2000) NOT NULL DEFAULT '' COMMENT '变更前卡密',
  `to_url` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否带跳转链接',
  `dual_status` tinyint(1) NOT NULL DEFAULT 2 COMMENT '二次处理状态（默认 2）',
  `dual_time` int NOT NULL DEFAULT 0 COMMENT '二次处理时间',
  `dual_type` int NOT NULL DEFAULT 0 COMMENT '二次处理类型',
  `dual_id` int NOT NULL DEFAULT 0 COMMENT '二次处理关联ID',
  `docking_order` varchar(255) NOT NULL DEFAULT '' COMMENT '渠道订单号',
  `mkshop_id` varchar(50) NOT NULL DEFAULT '' COMMENT 'MKSHOP店铺/商品标识',
  `mkshop_p_id` varchar(50) NOT NULL DEFAULT '' COMMENT 'mkshop 父级ID',
  `mkshop_s_id` varchar(50) NOT NULL DEFAULT '' COMMENT 'mkshop 规格ID',
  `mkshop_aftersale_id` varchar(50) NOT NULL DEFAULT '' COMMENT 'mkshop 售后单ID',
  `mkshop_aftersale_reason` varchar(255) NOT NULL DEFAULT '' COMMENT 'mkshop 售后原因',
  `mkshop_aftersale_result` varchar(255) NOT NULL DEFAULT '' COMMENT 'mkshop 售后结果',
  `mkshop_aftersale_status` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'mkshop 售后状态',
  `mkshop_code` varchar(500) NOT NULL DEFAULT '' COMMENT 'mkshop 兑换码/核销码',
  `mkshop_type` varchar(50) NOT NULL DEFAULT '' COMMENT 'mkshop 类型',
  `use_img` varchar(255) NOT NULL DEFAULT '' COMMENT '使用截图',
  `use_code` varchar(255) NOT NULL DEFAULT '' COMMENT '使用凭证码',
  `use_shop` varchar(255) NOT NULL DEFAULT '' COMMENT '使用门店',
  `card_no_md5` char(32) NOT NULL DEFAULT '' COMMENT '卡号 MD5（唯一性校验，配合 idx_cate_md5_delete 去重）',
  `card_password_md5` char(32) NOT NULL DEFAULT '' COMMENT '卡密 MD5（去重索引）',
  PRIMARY KEY (`id`),
  KEY `card_cate_id_card` (`card_cate_id`,`status`,`delete_time`),
  KEY `order_card` (`order_id`,`delete_time`),
  KEY `can_order` (`order_id`,`card_cate_id`,`status`,`delete_time`),
  KEY `idx_cate_md5_delete` (`card_cate_id`,`card_no_md5`,`card_password_md5`,`delete_time`),
  KEY `idx_cate_paw_md5_delete` (`card_cate_id`,`card_password_md5`,`delete_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='卡密本体表';

-- ---------------------------------------------------------------------------
-- [P0] mks_card_cate — 卡密库（卡种）：卡号/卡密别名、数量、成本、预警与限频策略
-- ---------------------------------------------------------------------------
CREATE TABLE `mks_card_cate` (
  `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT '卡密库ID',
  `uid` int NOT NULL DEFAULT 0 COMMENT '创建者/供货商会员ID',
  `name` varchar(255) NOT NULL COMMENT '卡密库名称（卡种名称）',
  `card_type` tinyint(1) NOT NULL DEFAULT 1 COMMENT '卡密类型：1=卡密 2=卡号/卡密 3=固定卡密 4=固定卡号卡密',
  `card_no_alias` varchar(255) NOT NULL COMMENT '“卡号”字段在前台的别名（如“账号”）',
  `card_password_alias` varchar(255) NOT NULL DEFAULT '' COMMENT '“卡密”字段的别名（如“密码”）',
  `card_num` int unsigned NOT NULL DEFAULT 0 COMMENT '当前库存数量（冗余）',
  `card_content` varchar(2000) NOT NULL DEFAULT '' COMMENT '卡密展示说明/发货模板',
  `cost` decimal(16,5) NOT NULL DEFAULT 0.00 COMMENT '卡密成本（用于计算利润）',
  `warn` int NOT NULL DEFAULT 0 COMMENT '库存预警数量',
  `create_time` int NOT NULL DEFAULT 0 COMMENT '创建时间',
  `update_time` int NOT NULL COMMENT '更新时间',
  `delete_time` int NOT NULL DEFAULT 0 COMMENT '软删除时间',
  `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '状态 1=启用',
  `sort` int NOT NULL DEFAULT 0 COMMENT '排序',
  `interval` int NOT NULL DEFAULT 10 COMMENT '同 IP/同用户的提取间隔（秒）',
  `frequency` int NOT NULL DEFAULT 3 COMMENT '间隔内允许提取次数',
  `warn_open` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否开启库存预警',
  `warn_time` int NOT NULL DEFAULT 0 COMMENT '最近预警时间',
  `pid` varchar(100) NOT NULL DEFAULT '' COMMENT '父级分类ID（卡组，字符串兼容多值）',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='卡密库（卡种）';

-- ---------------------------------------------------------------------------
-- [P0] mks_card_history — 导卡历史：每次导入/删除卡密的批次记录
-- ---------------------------------------------------------------------------
CREATE TABLE `mks_card_history` (
  `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT '导卡批次ID',
  `card_cate_id` int NOT NULL COMMENT '卡密库ID',
  `card_cate_name` varchar(255) NOT NULL DEFAULT '' COMMENT '卡密库名称快照',
  `count` int NOT NULL DEFAULT 0 COMMENT '本批导入/操作数量',
  `uid` varchar(100) NOT NULL COMMENT '操作人（varchar，兼容管理员/会员）',
  `cost` decimal(16,5) NOT NULL COMMENT '本批成本合计',
  `mark` varchar(255) NOT NULL DEFAULT '' COMMENT '备注',
  `sale_first` int NOT NULL DEFAULT 0 COMMENT '售卖优先标记',
  `create_time` int NOT NULL DEFAULT 0 COMMENT '创建时间',
  `end_time` int NOT NULL DEFAULT 0 COMMENT '批次有效期',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='导卡历史';

-- ---------------------------------------------------------------------------
-- [P0] mks_card_group — 卡组分类表：卡密库的分组目录（relation_id 关联 card_cate）
-- ---------------------------------------------------------------------------
CREATE TABLE `mks_card_group` (
  `id` int NOT NULL AUTO_INCREMENT COMMENT '主键ID（自增）',
  `sort` int NOT NULL DEFAULT 0 COMMENT '排序',
  `relation_id` int NOT NULL DEFAULT 0 COMMENT '关联id',
  `pid` int NOT NULL DEFAULT 0 COMMENT '父级ID',
  `name` varchar(50) NOT NULL DEFAULT '' COMMENT '分类名称',
  `enname` varchar(50) NOT NULL DEFAULT '' COMMENT '分类目录',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='卡组分类表';

-- ---------------------------------------------------------------------------
-- [P0] mks_order — 订单主表：下单快照、金额拆分、支付/发货/对接/自动补单全流程状态
-- ---------------------------------------------------------------------------
CREATE TABLE `mks_order` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT '订单ID（bigint）',
  `status` tinyint(1) NOT NULL DEFAULT 0 COMMENT '订单状态：-1=取消支付 0=等待支付 1=等待处理 2=正在处理 3=交易成功 4=取消交易 5=已经退款（另有 6=部分退款，未证实）',
  `docking_status` tinyint(1) NOT NULL DEFAULT 0 COMMENT '对接渠道侧状态（0=未对接/1=已提交/2=已完成/3=失败，推断）',
  `ordersn` varchar(50) NOT NULL DEFAULT '' COMMENT '系统订单号（唯一，前台展示与对账主键）',
  `cash_back_profit` decimal(16,4) NOT NULL DEFAULT 0.00 COMMENT '返现利润',
  `cash_back_amount` decimal(16,4) NOT NULL DEFAULT 0.00 COMMENT '返现金额',
  `buy_host` varchar(255) NOT NULL DEFAULT '' COMMENT '下单来源域名（多站点区分）',
  `nickname` varchar(50) NOT NULL DEFAULT '' COMMENT '下单会员昵称快照',
  `mobile` varchar(20) NOT NULL DEFAULT '' COMMENT '会员手机号快照',
  `real_name` varchar(100) NOT NULL DEFAULT '' COMMENT '收货人/实名姓名',
  `address_mobile` varchar(50) NOT NULL DEFAULT '' COMMENT '收货电话',
  `address` varchar(500) NOT NULL DEFAULT '' COMMENT '完整收货地址',
  `address_province` varchar(255) NOT NULL DEFAULT '' COMMENT '省（文本冗余）',
  `address_city` varchar(255) NOT NULL DEFAULT '' COMMENT '市',
  `address_area` varchar(255) NOT NULL DEFAULT '' COMMENT '区县',
  `address_street` varchar(255) NOT NULL DEFAULT '' COMMENT '街道',
  `address_detail` varchar(500) NOT NULL DEFAULT '' COMMENT '详细地址',
  `pid` int NOT NULL DEFAULT 0 COMMENT '父订单ID（拆单/合并下单场景，见 order_pid）',
  `uid` bigint NOT NULL DEFAULT 0 COMMENT '下单会员ID → member.id',
  `user_where` varchar(50) NOT NULL DEFAULT 'pc' COMMENT '下单端（pc/h5/app/xcx）',
  `supplier_id` bigint unsigned NOT NULL DEFAULT 0 COMMENT '供货商ID（供货商模式下的收益方）',
  `supplier_name` varchar(200) NOT NULL DEFAULT '' COMMENT '供货商名称快照',
  `supplier_type` tinyint(1) NOT NULL DEFAULT 0 COMMENT '供货商类型（平台/会员供货）',
  `actual_income` decimal(16,4) NOT NULL DEFAULT 0.00 COMMENT '实际收入（平台净收入）',
  `rate_value` decimal(4,2) NOT NULL DEFAULT 0.00 COMMENT '费率%（下单时的费率快照）',
  `goods_id` int NOT NULL DEFAULT 0 COMMENT '商品ID → goods.id',
  `goods_sku_id` int NOT NULL DEFAULT 0 COMMENT '商品规格ID → goods_stock.id',
  `goods_name` varchar(1000) NOT NULL DEFAULT '' COMMENT '商品名称快照（订单不随商品改名变化）',
  `goods_sku_name` varchar(500) NOT NULL DEFAULT '' COMMENT '规格名称快照',
  `express_type` tinyint(1) NOT NULL DEFAULT 0 COMMENT '发货方式：0=自动发货(卡密/虚拟), 1=人工/物流（推断）',
  `goods_img` varchar(255) NOT NULL DEFAULT '' COMMENT '商品图片快照',
  `goods_type` tinyint(1) NOT NULL DEFAULT 2 COMMENT '商品类型（与 goods.goods_type 一致）',
  `provide_type` tinyint(1) NOT NULL DEFAULT 1 COMMENT '供货方式（1=自营 2=对接渠道）',
  `face_value` decimal(14,4) NOT NULL DEFAULT 0.000 COMMENT '面值快照',
  `cost_value` decimal(16,5) NOT NULL DEFAULT 0.000 COMMENT '成本价快照（下单时刻的进价）',
  `real_price` decimal(16,4) NOT NULL DEFAULT 0.000 COMMENT '售价快照（单价）',
  `validdate_value` int NOT NULL DEFAULT 0 COMMENT '有效期快照',
  `quantity` int NOT NULL DEFAULT 1 COMMENT '购买数量',
  `goods_bei` int NOT NULL DEFAULT 0 COMMENT '商品倍数快照',
  `price` decimal(16,4) NOT NULL DEFAULT 0.000 COMMENT '单价（实付单价）',
  `total_price` decimal(16,4) NOT NULL DEFAULT 0.000 COMMENT '订单总额（含数量、运费前/后，见 price_set_content）',
  `freight_price` decimal(16,4) NOT NULL DEFAULT 0.000 COMMENT '运费',
  `order_price` decimal(16,4) NOT NULL DEFAULT 0.00 COMMENT '订单利润（售价-成本，报表用）',
  `recharge_account` varchar(500) NOT NULL DEFAULT '' COMMENT '充值账号（虚拟商品需要用户填写的账号）',
  `recharge_password` varchar(2000) NOT NULL DEFAULT '' COMMENT '充值密码/二次信息',
  `recharge_info` text DEFAULT NULL COMMENT '充值附加信息（JSON，来自下单模板字段）',
  `recharge_url` varchar(2000) NOT NULL DEFAULT '' COMMENT '充值跳转地址',
  `recharge_time_type` tinyint(1) NOT NULL DEFAULT 0 COMMENT '充值耗时单位',
  `recharge_time_value` int NOT NULL DEFAULT 0 COMMENT '充值耗时值',
  `mark` text DEFAULT NULL COMMENT '用户备注',
  `create_time` int NOT NULL DEFAULT 0 COMMENT '下单时间',
  `update_time` int NOT NULL DEFAULT 0 COMMENT '更新时间',
  `pay_time` int NOT NULL DEFAULT 0 COMMENT '支付时间',
  `finish_time` int NOT NULL DEFAULT 0 COMMENT '完成时间',
  `delete_time` int NOT NULL DEFAULT 0 COMMENT '软删除时间',
  `pay_type` varchar(50) NOT NULL DEFAULT '' COMMENT '支付方式：balance=余额 alipay=支付宝 wechat=微信 alipayuser=转账加款 qqpay=QQ支付 code=加款卡',
  `user_agent` varchar(1000) NOT NULL DEFAULT '' COMMENT '下单 UA',
  `ip` varchar(128) NOT NULL DEFAULT '' COMMENT '下单 IP',
  `ip_all` varchar(255) NOT NULL DEFAULT '' COMMENT 'IP 归属地',
  `province` varchar(20) NOT NULL DEFAULT '' COMMENT 'IP 省份',
  `city` varchar(20) NOT NULL DEFAULT '' COMMENT 'IP 城市',
  `reader` varchar(50) NOT NULL DEFAULT '' COMMENT '处理人（后台操作员）',
  `handler` varchar(20) NOT NULL DEFAULT '' COMMENT '处理方式/处理人标记',
  `start_progress` int NOT NULL DEFAULT 0 COMMENT '处理进度起点',
  `target_progress` int NOT NULL DEFAULT 0 COMMENT '处理进度目标',
  `current_progress` int NOT NULL DEFAULT 0 COMMENT '当前处理进度（与进度条展示配合）',
  `recharge_hints` varchar(1000) NOT NULL DEFAULT '' COMMENT '充值提示信息',
  `is_divide` tinyint(1) unsigned NOT NULL DEFAULT 0 COMMENT '是否已分账',
  `order_from` tinyint(1) unsigned NOT NULL DEFAULT 0 COMMENT '订单来源（下单入口）',
  `price_set_content` text DEFAULT NULL COMMENT '价格计算明细 JSON（原价/会员价/优惠/返现等拆分依据）',
  `commission_price` decimal(16,4) NOT NULL DEFAULT 0.00 COMMENT '本单产生的佣金总额',
  `is_fail` int NOT NULL DEFAULT 0 COMMENT '失败标记/失败次数',
  `has_back_money` decimal(16,4) NOT NULL DEFAULT 0.00 COMMENT '已退金额',
  `has_sync` tinyint(1) NOT NULL DEFAULT 1 COMMENT '是否已同步下游（默认 1）',
  `sync_time` int NOT NULL DEFAULT 0 COMMENT '同步时间',
  `sync_count` int NOT NULL DEFAULT 0 COMMENT '同步次数',
  `docking_id` int NOT NULL DEFAULT 0 COMMENT '对接渠道ID → docking.id（0=非渠道单）',
  `docking_type` varchar(50) NOT NULL DEFAULT '' COMMENT '渠道类型（对接协议）',
  `docking_info` varchar(50) NOT NULL DEFAULT '' COMMENT '渠道订单信息/渠道侧单号',
  `docking_order_array` text DEFAULT NULL COMMENT '渠道下单请求/响应 JSON',
  `docking_mark` varchar(1000) NOT NULL DEFAULT '' COMMENT '渠道备注',
  `docking_bu_order` varchar(50) NOT NULL DEFAULT '' COMMENT '渠道补单号',
  `docking_auto_bu` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否已自动补单',
  `auto_bu_back` tinyint(1) NOT NULL DEFAULT 0 COMMENT '自动补单回退标记',
  `docking_order_bu` tinyint(1) NOT NULL DEFAULT 0 COMMENT '渠道补单标记',
  `auto_bu_time` int NOT NULL DEFAULT 0 COMMENT '自动补单时间',
  `auto_order_time` int NOT NULL DEFAULT 0 COMMENT '自动下单时间',
  `auto_bu_count` int NOT NULL DEFAULT 0 COMMENT '自动补单次数',
  `order_bu_back` tinyint(1) NOT NULL DEFAULT 0 COMMENT '补单回退标记',
  `order_bu_count` int NOT NULL DEFAULT 1 COMMENT '补单次数上限',
  `now_bu_count` int NOT NULL DEFAULT 0 COMMENT '当前补单次数',
  `phone_or_mailbox` varchar(20) NOT NULL DEFAULT '' COMMENT '手机号或邮箱（部分下单模板用）',
  `order_floor` tinyint(1) NOT NULL DEFAULT 0 COMMENT '订单楼层/级别（拆单层级）',
  `external_orderno` varchar(50) NOT NULL DEFAULT '' COMMENT '外部订单号（API 下单方传入，用于幂等与回查）',
  `notify_url` varchar(255) NOT NULL DEFAULT '' COMMENT '商户回调地址（API 下单）',
  `notify_status` tinyint(1) NOT NULL DEFAULT 0 COMMENT '回调状态 0=未回调',
  `notify_count` int NOT NULL DEFAULT 0 COMMENT '回调尝试次数',
  `vip_id` int NOT NULL DEFAULT 0 COMMENT '分站ID（订单归属分站）',
  `vip_set_content` text DEFAULT NULL COMMENT '分站加价规则快照 JSON',
  `vip_uid` int NOT NULL DEFAULT 0 COMMENT '分站主会员ID',
  `vip_profit_price` decimal(16,4) NOT NULL DEFAULT 0.00 COMMENT '分站利润',
  `vip_purchase_price` decimal(16,4) NOT NULL DEFAULT 0.00 COMMENT '分站进货价',
  `vip_add_price` decimal(16,4) NOT NULL DEFAULT 0 COMMENT '分站加价',
  `vip_goods_price` decimal(16,4) NOT NULL DEFAULT 0 COMMENT '分站商品价',
  `tpl_data` text DEFAULT NULL COMMENT '下单模板填写数据 JSON',
  `recharge_log_id` int NOT NULL DEFAULT 0 COMMENT '关联充值订单ID → recharge_log.id（余额支付时）',
  `stock_id` int NOT NULL DEFAULT 0 COMMENT '扣减的库存行ID → goods_stock.id',
  `docking_goods_id` varchar(50) NOT NULL DEFAULT '' COMMENT '渠道商品ID',
  `docking_goods_sku_id` varchar(255) NOT NULL DEFAULT '' COMMENT '渠道规格ID',
  `api_type` tinyint(1) NOT NULL DEFAULT 1 COMMENT '下单来源类型 1=页面下单 2=API（推断）',
  `real_order_price` decimal(16,4) NOT NULL DEFAULT 0 COMMENT '真实订单金额（对账用）',
  `is_agiso` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否 agiso 渠道单',
  `is_goodscard` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否卡券/实物商品单（需物流）',
  `admin_mark` varchar(255) NOT NULL DEFAULT '' COMMENT '后台备注',
  `type` tinyint(1) NOT NULL DEFAULT 0 COMMENT '订单类型：0=普通 1=拼团（拼团为插件 goodsgroup）',
  `activity_id` int NOT NULL DEFAULT 0 COMMENT '活动ID → activity.id',
  `activity_append` varchar(255) NOT NULL DEFAULT '' COMMENT '活动附加信息',
  `groups_log_id` int NOT NULL DEFAULT 0 COMMENT '拼团记录ID → groups_log.id（该表不在 SQL 内）',
  `can_back` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否可退款',
  `has_base` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否有基础信息',
  `coupon_price` decimal(16,4) NOT NULL DEFAULT 0 COMMENT '优惠券抵扣金额',
  `coupon_id` int DEFAULT NULL COMMENT '优惠券ID（唯一索引 unique_coupon_id ⇒ 一张券只能用于一单）',
  `promotions_price` decimal(16,4) DEFAULT 0 COMMENT '促销优惠金额',
  `dockinggong_id` varchar(24) NOT NULL DEFAULT '' COMMENT '渠道工单号',
  `recharge_sku_id` int NOT NULL DEFAULT 0 COMMENT '充值规格ID',
  `is_quick_recharge` tinyint(1) DEFAULT 0 COMMENT '是否快捷充值单',
  `order_pid` int NOT NULL DEFAULT 0 COMMENT '父订单ID（子单指向主单，0=主单）',
  `order_info_text` text DEFAULT NULL COMMENT '订单信息文本（快照/导出用）',
  `order_son` int NOT NULL DEFAULT 0 COMMENT '子单数量/子单标记',
  `quick_type` tinyint(1) NOT NULL DEFAULT 0 COMMENT '快捷下单类型',
  `is_split_combine` int NOT NULL DEFAULT 0 COMMENT '是否拆分合并单',
  `complete_num` int unsigned NOT NULL DEFAULT 0 COMMENT '完成数量（部分完成场景）',
  `order_bu_time` int NOT NULL DEFAULT 0 COMMENT '补单时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `ordersn` (`ordersn`),
  UNIQUE KEY `unique_coupon_id` (`coupon_id`),
  KEY `delete_time` (`delete_time`),
  KEY `uid_delete` (`uid`,`delete_time`),
  KEY `status` (`status`),
  KEY `create_time` (`create_time`,`delete_time`),
  KEY `order_delete` (`ordersn`,`delete_time`),
  KEY `recharge_log_id` (`recharge_log_id`,`delete_time`),
  KEY `docking_bu_order` (`docking_bu_order`,`delete_time`),
  KEY `docking_id` (`status`,`delete_time`,`docking_id`),
  KEY `supplier_id` (`supplier_id`,`delete_time`),
  KEY `is_goodscard` (`is_goodscard`,`delete_time`),
  KEY `uid_external_orderno` (`uid`,`external_orderno`,`delete_time`),
  KEY `docking_info_order` (`docking_id`,`status`,`docking_info`,`delete_time`),
  KEY `docking_info` (`docking_info`,`delete_time`),
  KEY `ordersn_uid` (`uid`,`ordersn`,`delete_time`),
  KEY `ordersn_uid_create` (`uid`,`ordersn`,`delete_time`,`create_time`),
  KEY `external_orderno_uid_create` (`uid`,`external_orderno`,`delete_time`,`create_time`),
  KEY `recharge_account` (`recharge_account`,`delete_time`),
  KEY `external_orderno` (`external_orderno`,`delete_time`),
  KEY `recharge_log_buy` (`status`,`ordersn`,`delete_time`,`recharge_log_id`),
  KEY `status_create_time` (`delete_time`,`status`,`create_time`),
  KEY `status_finish_time` (`delete_time`,`status`,`finish_time`),
  KEY `status_delete_time` (`delete_time`,`status`),
  KEY `status_sum_order_price` (`delete_time`,`status`,`order_price`),
  KEY `status_sum_total_price` (`delete_time`,`status`,`total_price`),
  KEY `order_pid` (`delete_time`,`order_pid`),
  KEY `order_pid_supplier_id` (`delete_time`,`order_pid`,`supplier_id`),
  KEY `status_sum_finish_order_price` (`delete_time`,`status`,`finish_time`,`order_price`),
  KEY `status_sum_create_total_price` (`delete_time`,`status`,`create_time`,`total_price`),
  KEY `uid_order_pid_delete` (`uid`,`order_pid`,`delete_time`),
  KEY `is_vip_order` (`status`,`delete_time`,`vip_id`),
  KEY `goods_id` (`goods_id`,`delete_time`),
  KEY `status_pay_time` (`delete_time`,`status`,`pay_time`),
  KEY `idx_delete_is_split_create` (`delete_time`,`is_split_combine`,`create_time`),
  KEY `console_finish_time` (`finish_time`),
  KEY `front_order_list` (`uid`,`order_pid`,`delete_time`,`create_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='订单主表';

-- ---------------------------------------------------------------------------
-- [P0] mks_order_log — 订单操作日志（简版：type+mark）
-- ---------------------------------------------------------------------------
CREATE TABLE `mks_order_log` (
  `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT '主键ID（自增）',
  `type` tinyint(1) NOT NULL DEFAULT 1 COMMENT '类型标识（区分同表多种用途，码值见模块说明）',
  `mark` text NOT NULL COMMENT '标识/备注（短文本，代码中作为事件键或备注）',
  `order_id` int NOT NULL DEFAULT 0 COMMENT '订单ID → order.id',
  `create_time` int NOT NULL DEFAULT 0 COMMENT '创建时间（int 时间戳）',
  PRIMARY KEY (`id`),
  KEY `order_id` (`order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='订单操作日志（简版';

-- ---------------------------------------------------------------------------
-- [P0] mks_order_refund — 订单退款记录：退款金额、退款方式、操作人、可选部分退款
-- ---------------------------------------------------------------------------
CREATE TABLE `mks_order_refund` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT '退款记录ID',
  `order_id` varchar(255) NOT NULL DEFAULT '' COMMENT '订单ID（varchar，可存多单号/子单集合，与 order.ordersn 对应）',
  `money` decimal(16,4) NOT NULL DEFAULT 0.000 COMMENT '本次退款金额',
  `oper` int NOT NULL DEFAULT 0 COMMENT '操作人ID（管理员或会员ID）',
  `oper_nickname` varchar(50) NOT NULL DEFAULT '' COMMENT '操作人昵称',
  `oper_type` tinyint(1) NOT NULL DEFAULT 1 COMMENT '操作人类型 1=后台 2=前台会员（推断）',
  `create_time` int NOT NULL DEFAULT 0 COMMENT '退款时间',
  `backmoney_type` tinyint(1) NOT NULL DEFAULT 0 COMMENT '退款方式：0=退回余额/原路退回（推断，受 config.order.order_can_back_day 限制）',
  `backmoney_part` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否部分退款 0=全额 1=部分',
  `order_style` tinyint(1) NOT NULL DEFAULT 0 COMMENT '订单类型（订单/充值单）',
  `order_money` decimal(16,4) NOT NULL DEFAULT 0.000 COMMENT '订单原始金额',
  `order_money1` decimal(16,4) NOT NULL DEFAULT 0.000 COMMENT '实付金额（优惠后）',
  `days` int NOT NULL DEFAULT 0 COMMENT '距下单天数（校验退款期限）',
  `card_num` int NOT NULL DEFAULT 0 COMMENT '退回卡密数量（卡密订单退库存）',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='订单退款记录';

-- ---------------------------------------------------------------------------
-- [P0] mks_order_dolog — 库存/发货流水日志：记录扣卡、补单、回退等库存动作
-- ---------------------------------------------------------------------------
CREATE TABLE `mks_order_dolog` (
  `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT '主键ID（自增）',
  `docking_id` int NOT NULL DEFAULT 0 COMMENT '对接供货渠道ID → docking.id',
  `docking_type` varchar(255) NOT NULL DEFAULT '' COMMENT '渠道类型（对接协议）',
  `docking_goods_id` varchar(50) NOT NULL DEFAULT '' COMMENT '渠道商品ID',
  `stock_id` int NOT NULL COMMENT '库存行ID → goods_stock.id',
  `mark` longtext NOT NULL COMMENT '标识/备注（短文本，代码中作为事件键或备注）',
  `order_id` int NOT NULL DEFAULT 0 COMMENT '订单ID → order.id',
  `card_cate_id` int NOT NULL DEFAULT 0 COMMENT '卡密库ID → card_cate.id',
  `create_time` int NOT NULL DEFAULT 0 COMMENT '创建时间（int 时间戳）',
  `stock_log_type` tinyint(1) NOT NULL DEFAULT 0 COMMENT '库存日志类型（0=扣减 1=回退 等，码值见模块说明）',
  PRIMARY KEY (`id`),
  KEY `order_id` (`order_id`),
  KEY `stock_log_type` (`stock_log_type`,`order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='库存/发货流水日志';

-- ---------------------------------------------------------------------------
-- [P0] mks_order_tpl — 下单模板：动态表单字段定义(content=JSON)
-- ---------------------------------------------------------------------------
CREATE TABLE `mks_order_tpl` (
  `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT '下单模板ID',
  `uid` int unsigned NOT NULL DEFAULT 0 COMMENT '创建者',
  `name` varchar(255) NOT NULL DEFAULT '' COMMENT '模板名称（充值账号/手机号/QQ/收货地址…）',
  `tpl_type` tinyint(1) NOT NULL DEFAULT 0 COMMENT '模板类型',
  `isshare` tinyint unsigned NOT NULL DEFAULT 1 COMMENT '是否共享（1=全站可用）',
  `type` tinyint NOT NULL DEFAULT 0 COMMENT '适用类型',
  `content` longtext NOT NULL COMMENT '字段定义 JSON（type/name/key/vali/tip/options）',
  `create_time` int unsigned NOT NULL DEFAULT 0 COMMENT '创建时间',
  `update_time` int unsigned NOT NULL DEFAULT 0 COMMENT '更新时间',
  `status` tinyint NOT NULL DEFAULT 1 COMMENT '状态',
  `is_default` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否默认模板',
  `sort` int NOT NULL DEFAULT 0 COMMENT '排序',
  `tpl_info_id` int NOT NULL DEFAULT 0 COMMENT '模板快照ID → order_tpl_info.id',
  PRIMARY KEY (`id`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='下单模板' AUTO_INCREMENT=5;

-- ---------------------------------------------------------------------------
-- [P0] mks_order_tpl_info — 下单模板详情表：按 md5 存模板表单 JSON 快照
-- ---------------------------------------------------------------------------
CREATE TABLE `mks_order_tpl_info` (
  `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT '主键ID（自增）',
  `order_tpl_info` longtext DEFAULT NULL COMMENT '下单模板详情 JSON',
  `order_tpl_info_md5` varchar(32) NOT NULL DEFAULT '' COMMENT '数据的md5',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='下单模板详情表';

-- ---------------------------------------------------------------------------
-- [P0] mks_member_money — 会员余额流水（资金明细）：每次变动的 trade_type/前后余额
-- ---------------------------------------------------------------------------
CREATE TABLE `mks_member_money` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT '流水ID',
  `uid` int NOT NULL COMMENT '会员ID → member.id',
  `money` decimal(16,4) NOT NULL DEFAULT 0.00 COMMENT '本次变动金额（正=加 负=减，推断）',
  `trade_type` int NOT NULL COMMENT '业务类型（下单/退款/充值/提现/转账/后台加款…，枚举由后台 member_money/trade_type 接口提供）',
  `change_type` tinyint(1) NOT NULL COMMENT '变动方向 1=收入 2=支出（推断）',
  `before` decimal(14,4) NOT NULL DEFAULT 0.00 COMMENT '变动前余额',
  `after` decimal(14,4) NOT NULL DEFAULT 0.00 COMMENT '变动后余额',
  `create_time` int NOT NULL DEFAULT 0 COMMENT '发生时间',
  `extend_id` varchar(255) NOT NULL DEFAULT '' COMMENT '扩展ID（关联订单号/充值单号/提现单号）',
  `mark` varchar(255) NOT NULL DEFAULT '' COMMENT '备注',
  `order_id` int NOT NULL DEFAULT 0 COMMENT '关联订单ID',
  `admin_id` int NOT NULL DEFAULT 0 COMMENT '操作管理员ID（后台加减款时）',
  `only_order_no` varchar(255) DEFAULT '' COMMENT '唯一单号（幂等防重复入账）',
  PRIMARY KEY (`id`),
  KEY `uid` (`uid`),
  KEY `extend_id` (`extend_id`),
  KEY `order_id` (`order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='会员余额流水（资金明细）';

-- ---------------------------------------------------------------------------
-- [P0] mks_member_log — 会员操作日志
-- ---------------------------------------------------------------------------
CREATE TABLE `mks_member_log` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT '主键ID（自增）',
  `uid` int DEFAULT NULL COMMENT '会员ID → member.id',
  `title` varchar(255) NOT NULL COMMENT '标题',
  `ip` varchar(30) DEFAULT NULL COMMENT 'IP 地址',
  `ip_add` varchar(255) DEFAULT '' COMMENT 'IP 归属地',
  `ip_all` varchar(255) NOT NULL DEFAULT '' COMMENT 'IP 归属地（完整）',
  `type` tinyint(1) DEFAULT 0 COMMENT '类型标识（区分同表多种用途，码值见模块说明）',
  `change_type` int DEFAULT 0 COMMENT '变动类型/变更类型',
  `vip_id` int NOT NULL DEFAULT 0 COMMENT '分站ID（0=主站）',
  `mark` text DEFAULT NULL COMMENT '标识/备注（短文本，代码中作为事件键或备注）',
  `create_time` int DEFAULT NULL COMMENT '创建时间（int 时间戳）',
  `update_time` int DEFAULT NULL COMMENT '更新时间（int 时间戳）',
  `host` varchar(50) NOT NULL COMMENT '域名/主机',
  PRIMARY KEY (`id`),
  KEY `member_log_admin_id` (`uid`),
  KEY `member_log_admin_uid` (`uid`,`type`),
  KEY `member_log_admin_uid_type` (`uid`,`type`,`change_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='会员操作日志';

-- ---------------------------------------------------------------------------
-- [P0] mks_pay_list — 支付方式（支付宝/微信/QQ/加款卡）基础定义：限额、费率、图标
-- ---------------------------------------------------------------------------
CREATE TABLE `mks_pay_list` (
  `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT '支付方式ID',
  `name` varchar(255) NOT NULL DEFAULT '' COMMENT '支付方式标识（alipay/wechat/qqpay/code）',
  `icon` varchar(255) NOT NULL DEFAULT '' COMMENT '图标类名',
  `title` varchar(255) NOT NULL DEFAULT '' COMMENT '支付方式名称',
  `desc` varchar(255) NOT NULL DEFAULT '' COMMENT '说明文案',
  `min_fee` decimal(16,4) NOT NULL DEFAULT 0.00 COMMENT '最低手续费',
  `start_money` decimal(16,4) NOT NULL DEFAULT 0.00 COMMENT '单笔最小金额',
  `end_money` decimal(16,4) NOT NULL DEFAULT 0.00 COMMENT '单笔最大金额',
  `rate` decimal(5,2) unsigned NOT NULL COMMENT '费率（%）',
  `max_fee` decimal(16,4) NOT NULL COMMENT '最高手续费封顶',
  `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '状态：1=启用 0=停用',
  `sort` int NOT NULL DEFAULT 0 COMMENT '排序',
  `create_time` int NOT NULL DEFAULT 0 COMMENT '创建时间',
  `update_time` int NOT NULL COMMENT '更新时间',
  `delete_time` int NOT NULL DEFAULT 0 COMMENT '软删除',
  `recharge_tip` text DEFAULT NULL COMMENT '充值提示',
  `recharge_url` varchar(255) NOT NULL DEFAULT '' COMMENT '充值跳转地址',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='支付方式（支付宝/微信/QQ/加款卡）基础定义' AUTO_INCREMENT=5;

-- ---------------------------------------------------------------------------
-- [P0] mks_pay_channel — 支付通道：具体商户通道配置(config)、轮询、限额、费率、可用端
-- ---------------------------------------------------------------------------
CREATE TABLE `mks_pay_channel` (
  `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT '通道ID',
  `name` varchar(255) NOT NULL DEFAULT '' COMMENT '通道名称',
  `mark` varchar(255) NOT NULL DEFAULT '' COMMENT '通道标识（对应支付驱动）',
  `pay_channel` varchar(255) NOT NULL DEFAULT '' COMMENT '支付通道驱动标识',
  `pay_list_id` int NOT NULL DEFAULT 1 COMMENT '所属支付方式ID → mks_pay_list.id',
  `pay_type` int NOT NULL DEFAULT 1 COMMENT '通道类型（官方/三方/代付）',
  `status` tinyint(1) NOT NULL DEFAULT 0 COMMENT '启用状态',
  `config` longtext NOT NULL COMMENT '通道参数 JSON（appid/密钥/证书等）',
  `pay_time` int NOT NULL DEFAULT 0 COMMENT '支付有效时长',
  `create_time` int NOT NULL DEFAULT 0 COMMENT '创建时间',
  `expire_time` int NOT NULL DEFAULT 20 COMMENT '通道到期时间（购买/续费制通道）',
  `to_day_price` decimal(16,4) NOT NULL DEFAULT 0.00 COMMENT '日付价格',
  `to_month_price` decimal(16,4) NOT NULL DEFAULT 0.00 COMMENT '月付价格',
  `day_price` decimal(16,4) NOT NULL DEFAULT 0.00 COMMENT '日单价',
  `month_price` decimal(16,4) NOT NULL DEFAULT 0.00 COMMENT '月单价',
  `can_h5` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'H5 端可用',
  `can_app` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'APP 端可用',
  `can_gzh` tinyint(1) NOT NULL DEFAULT 0 COMMENT '公众号可用',
  `can_xcx` tinyint(1) NOT NULL DEFAULT 0 COMMENT '小程序可用',
  `can_pc` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'PC 端可用',
  `sort` int NOT NULL DEFAULT 0 COMMENT '排序（轮询顺序）',
  `host_id` int NOT NULL DEFAULT 0 COMMENT '归属站点/主域名ID',
  `pay_page` varchar(255) NOT NULL DEFAULT '1,2,3' COMMENT '可用支付页面（默认 1,2,3）',
  `tips` varchar(255) NOT NULL DEFAULT '' COMMENT '提示文案',
  `is_show` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否前台展示',
  `pay_image` varchar(255) NOT NULL DEFAULT '' COMMENT '支付图片（收款码）',
  `min_fee` decimal(16,4) NOT NULL DEFAULT 0.00 COMMENT '最低手续费',
  `start_money` decimal(16,4) NOT NULL DEFAULT 0.00 COMMENT '单笔最小金额',
  `end_money` decimal(16,4) NOT NULL DEFAULT 0.00 COMMENT '单笔最大金额',
  `rate` decimal(5,2) unsigned NOT NULL COMMENT '费率（%）',
  `pay_tips` text DEFAULT NULL COMMENT '支付页提示 HTML',
  `is_separate_rate` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否独立费率（不复用支付方式费率）',
  `is_vip_show` tinyint(1) NOT NULL DEFAULT 1 COMMENT '分站是否可见',
  `vip_id` int NOT NULL DEFAULT 0 COMMENT '归属分站ID（0=主站通道）',
  `auto_collect` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否自动归集',
  `is_review` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否需要人工审核',
  `level_ids` varchar(50) DEFAULT NULL COMMENT '可用会员等级集合',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='支付通道';

-- ---------------------------------------------------------------------------
-- [P0] mks_pay_channel_log — 支付通道流水：通道维度的手续费/服务费/金额统计
-- ---------------------------------------------------------------------------
CREATE TABLE `mks_pay_channel_log` (
  `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT '流水ID',
  `pay_channel_id` int NOT NULL DEFAULT 0 COMMENT '通道ID → mks_pay_channel.id',
  `type` tinyint(1) NOT NULL DEFAULT 0 COMMENT '流水类型（收入/手续费/结算）',
  `create_time` int NOT NULL DEFAULT 0 COMMENT '发生时间',
  `order_sn` varchar(255) NOT NULL DEFAULT '' COMMENT '关联订单号（充值单/订单）',
  `total_price` decimal(16,4) NOT NULL DEFAULT 0.00 COMMENT '订单金额',
  `host_id` int NOT NULL DEFAULT 0 COMMENT '站点ID',
  `service_price` decimal(16,4) NOT NULL DEFAULT 0.00 COMMENT '服务费（平台收取）',
  `service_price_provider` decimal(16,4) NOT NULL DEFAULT 0.00 COMMENT '服务费（上游收取）',
  `price` decimal(16,4) NOT NULL DEFAULT 0.00 COMMENT '结算净额',
  PRIMARY KEY (`id`),
  KEY `create_time` (`pay_channel_id`,`create_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='支付通道流水';

-- ---------------------------------------------------------------------------
-- [P0] mks_recharge_log — 充值订单表（余额充值主表）：支付方式、通道、到账、赠送、分润、回调
-- ---------------------------------------------------------------------------
CREATE TABLE `mks_recharge_log` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT '充值订单ID',
  `status` tinyint(1) NOT NULL DEFAULT 0 COMMENT '状态：0=待支付 1=已支付 2=已退款',
  `order_sn` varchar(40) NOT NULL DEFAULT '' COMMENT '充值单号',
  `title` varchar(255) NOT NULL DEFAULT '' COMMENT '充值标题/商品名',
  `uid` bigint NOT NULL DEFAULT 0 COMMENT '充值会员ID',
  `uname` varchar(20) NOT NULL DEFAULT '' COMMENT '会员账号快照',
  `payment_type` tinyint(1) NOT NULL DEFAULT 0 COMMENT '支付类型（余额/在线/人工）',
  `payment_gateway` varchar(10) NOT NULL DEFAULT '' COMMENT '支付网关标识（alipay/wechat/...）',
  `payment_source` tinyint(1) NOT NULL DEFAULT 0 COMMENT '支付来源端',
  `price` decimal(16,4) NOT NULL DEFAULT 0.00 COMMENT '充值金额',
  `total_price` decimal(16,4) NOT NULL DEFAULT 0.00 COMMENT '实付总额（含手续费）',
  `refund_price` decimal(16,4) NOT NULL DEFAULT 0 COMMENT '已退金额',
  `service_price` decimal(16,4) NOT NULL DEFAULT 0.00 COMMENT '平台手续费',
  `service_price_provider` decimal(16,4) NOT NULL DEFAULT 0.00 COMMENT '上游手续费',
  `trade_sn` varchar(255) NOT NULL DEFAULT '' COMMENT '第三方交易号',
  `create_time` int NOT NULL DEFAULT 0 COMMENT '创建时间',
  `update_time` int NOT NULL DEFAULT 0 COMMENT '更新时间',
  `payment_time` int NOT NULL DEFAULT 0 COMMENT '支付完成时间',
  `delete_time` int NOT NULL DEFAULT 0 COMMENT '软删除',
  `extend_time` int NOT NULL DEFAULT 0 COMMENT '延长/关闭时间',
  `dual_status` tinyint(1) NOT NULL DEFAULT 0 COMMENT '处理状态',
  `operator_type` tinyint(1) NOT NULL DEFAULT 0 COMMENT '操作人类型（会员/管理员/系统）',
  `operator_id` bigint NOT NULL DEFAULT 0 COMMENT '操作人ID',
  `operator_name` varchar(20) NOT NULL DEFAULT '0' COMMENT '操作人名',
  `mark` varchar(255) NOT NULL DEFAULT '' COMMENT '备注',
  `payment_mode` varchar(20) NOT NULL DEFAULT '' COMMENT '支付模式',
  `payment_provider` varchar(20) NOT NULL DEFAULT '' COMMENT '支付提供方',
  `payment_params` text DEFAULT NULL COMMENT '支付参数 JSON',
  `share_sn` varchar(40) NOT NULL DEFAULT '' COMMENT '分享单号（分销充值返佣）',
  `share_time` int NOT NULL DEFAULT 0 COMMENT '分润时间',
  `share_status` tinyint(1) NOT NULL DEFAULT 0 COMMENT '分润状态',
  `share_msg` varchar(40) NOT NULL DEFAULT '' COMMENT '分润信息',
  `share_amount` decimal(16,4) NOT NULL DEFAULT 0.00 COMMENT '分润金额',
  `type` int NOT NULL DEFAULT 1 COMMENT '充值类型：1=充值 2=下单 3=付费注册 4=付费升级 5=开通分站',
  `ip` varchar(255) NOT NULL DEFAULT '' COMMENT '充值 IP',
  `share_id` varchar(50) NOT NULL DEFAULT '' COMMENT '分润记录ID',
  `fenrun_price` decimal(16,4) NOT NULL DEFAULT 0.00 COMMENT '分润金额',
  `fenrun_order_sn` varchar(50) NOT NULL DEFAULT '' COMMENT '分润单号',
  `fenrun_time` int NOT NULL DEFAULT 0 COMMENT '分润时间',
  `pay_channel_id` int NOT NULL DEFAULT 0 COMMENT '支付通道ID → mks_pay_channel.id',
  `epay_no` varchar(255) NOT NULL DEFAULT '' COMMENT '易支付单号',
  `epay_no_list` varchar(1000) NOT NULL DEFAULT '' COMMENT '多通道支付单号集合',
  `user_where` varchar(255) NOT NULL DEFAULT 'pc' COMMENT '来源端',
  `vip_id` int NOT NULL DEFAULT 0 COMMENT '分站ID',
  `recharge_give_price` decimal(16,4) NOT NULL DEFAULT 0 COMMENT '赠送金额 → recharge_give',
  `pay_host` varchar(255) NOT NULL DEFAULT '' COMMENT '支付发起域名',
  `notify_url` varchar(255) NOT NULL DEFAULT '' COMMENT '回调地址',
  `return_url` varchar(255) NOT NULL DEFAULT '' COMMENT '同步跳回地址',
  `up_level_id` int NOT NULL DEFAULT 0 COMMENT '充值后升级到的等级',
  `usdt_money` float NOT NULL DEFAULT 0 COMMENT 'USDT 金额',
  `change_rate` float NOT NULL DEFAULT 0 COMMENT '汇率',
  `usdt_type` varchar(50) NOT NULL DEFAULT 'TRC20' COMMENT 'USDT 链类型（默认 TRC20）',
  `recharge_img` varchar(1000) NOT NULL DEFAULT '' COMMENT '转账凭证图片',
  `json_text` text DEFAULT NULL COMMENT '扩展 JSON',
  `pay_channel_vip_id` int NOT NULL DEFAULT 0 COMMENT '通道所属分站ID',
  `order_id` int NOT NULL DEFAULT 0 COMMENT '关联商品订单ID（余额支付时）',
  PRIMARY KEY (`id`),
  KEY `order_sn_del` (`order_sn`,`delete_time`),
  KEY `trade_sn` (`trade_sn`),
  KEY `payment_gateway` (`payment_gateway`),
  KEY `create_status` (`status`,`payment_gateway`,`create_time`),
  KEY `idx_uid_status_type_price` (`uid`,`status`,`type`,`price`),
  KEY `console_create_time` (`create_time`),
  KEY `console_payment_time` (`payment_time`),
  KEY `order_sn` (`order_sn`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='充值订单表（余额充值主表）';

-- ---------------------------------------------------------------------------
-- [P0] mks_recharge_code — 加款卡/充值卡密：卡号+密码+面值，供用户充值余额
-- ---------------------------------------------------------------------------
CREATE TABLE `mks_recharge_code` (
  `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT '加款卡ID',
  `uid` int NOT NULL DEFAULT 0 COMMENT '生成者/归属会员ID',
  `code` varchar(255) NOT NULL DEFAULT '' COMMENT '卡号',
  `password` varchar(255) NOT NULL DEFAULT '' COMMENT '卡密',
  `face_value` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT '面值（可充值金额）',
  `front_uid` int NOT NULL DEFAULT 0 COMMENT '前台使用会员ID',
  `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '状态',
  `used` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否已使用',
  `remark` varchar(255) NOT NULL DEFAULT '' COMMENT '备注',
  `expire_time` int NOT NULL DEFAULT 0 COMMENT '有效期',
  `create_time` int NOT NULL DEFAULT 0 COMMENT '创建时间',
  `update_time` int NOT NULL DEFAULT 0 COMMENT '更新时间',
  `delete_time` int NOT NULL DEFAULT 0 COMMENT '软删除',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='加款卡/充值卡密';

-- ---------------------------------------------------------------------------
-- [P0] mks_recharge_give — 充值满赠规则：满额门槛、赠送额、每日/累计限额、生效时间
-- ---------------------------------------------------------------------------
CREATE TABLE `mks_recharge_give` (
  `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT '规则ID',
  `money` decimal(16,4) NOT NULL DEFAULT 0.00 COMMENT '单笔充值满额门槛',
  `give_money` decimal(16,4) NOT NULL DEFAULT 0.00 COMMENT '赠送金额',
  `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '启用状态',
  `type` tinyint(1) NOT NULL DEFAULT 1 COMMENT '赠送类型（1=固定金额 2=比例，推断）',
  `sort` int NOT NULL DEFAULT 100 COMMENT '优先级',
  `day_count` int NOT NULL DEFAULT 0 COMMENT '每日可参与次数',
  `day_money` decimal(16,4) NOT NULL DEFAULT 0.00 COMMENT '每日赠送上限',
  `all_money` decimal(16,4) NOT NULL DEFAULT 0.00 COMMENT '累计赠送上限',
  `to_day_money` decimal(16,4) NOT NULL DEFAULT 0.00 COMMENT '当日已赠送',
  `to_all_money` decimal(16,4) NOT NULL DEFAULT 0.00 COMMENT '累计已赠送',
  `mark` varchar(255) NOT NULL DEFAULT '' COMMENT '备注',
  `expire_time` bigint NOT NULL DEFAULT 0 COMMENT '活动截止时间',
  `create_time` int NOT NULL DEFAULT 0 COMMENT '创建时间',
  `update_time` int NOT NULL DEFAULT 0 COMMENT '更新时间',
  `level_ids` varchar(100) DEFAULT '' COMMENT '适用会员等级集合',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='充值满赠规则';

-- ---------------------------------------------------------------------------
-- [P0] mks_recharge_give_log — 充值满赠发放记录
-- ---------------------------------------------------------------------------
CREATE TABLE `mks_recharge_give_log` (
  `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT '主键ID（自增）',
  `uid` int NOT NULL DEFAULT 0 COMMENT '会员ID → member.id',
  `give_money` decimal(16,4) NOT NULL DEFAULT 0.00 COMMENT '赠送金额',
  `type` tinyint(1) NOT NULL DEFAULT 1 COMMENT '类型标识（区分同表多种用途，码值见模块说明）',
  `recharge_log_id` int NOT NULL DEFAULT 0 COMMENT '充值订单ID → recharge_log.id',
  `recharge_give_id` int NOT NULL DEFAULT 0 COMMENT '满赠规则ID → recharge_give.id',
  `create_time` int NOT NULL DEFAULT 0 COMMENT '创建时间（int 时间戳）',
  `pay_time` int NOT NULL DEFAULT 0 COMMENT '支付时间',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='充值满赠发放记录';


-- ============================================================================
-- P1 资金结算 / 售后 / 会员扩展（起资金闭环，缺则佣金提现不可用）  （15 张表）
-- ============================================================================
-- ---------------------------------------------------------------------------
-- [P1] mks_member_commission — 会员佣金流水：佣金变动、来源订单、类型
-- ---------------------------------------------------------------------------
CREATE TABLE `mks_member_commission` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT '主键ID（自增）',
  `uid` int NOT NULL COMMENT '会员ID → member.id',
  `money` decimal(16,4) NOT NULL COMMENT '金额',
  `trade_type` int NOT NULL COMMENT '业务类型（下单/退款/充值/提现等，枚举在代码常量中）',
  `change_type` tinyint(1) NOT NULL COMMENT '变动类型/变更类型',
  `before` decimal(14,4) NOT NULL COMMENT '变动前值',
  `after` decimal(14,4) NOT NULL COMMENT '变动后值',
  `create_time` int NOT NULL DEFAULT 0 COMMENT '创建时间（int 时间戳）',
  `extend_id` varchar(255) NOT NULL DEFAULT '' COMMENT '扩展ID（关联单号：订单/充值/提现）',
  `mark` varchar(255) NOT NULL DEFAULT '' COMMENT '标识/备注（短文本，代码中作为事件键或备注）',
  `order_id` int NOT NULL DEFAULT 0 COMMENT '订单ID → order.id',
  PRIMARY KEY (`id`),
  KEY `uid` (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='会员佣金流水';

-- ---------------------------------------------------------------------------
-- [P1] mks_order_commission — 订单分销佣金：两级(uid1/uid2)+上级(puid)的分佣明细与结算状态
-- ---------------------------------------------------------------------------
CREATE TABLE `mks_order_commission` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT '分佣记录ID',
  `order_id` bigint unsigned NOT NULL DEFAULT 0 COMMENT '订单ID → order.id',
  `layer` int unsigned NOT NULL DEFAULT 1 COMMENT '分佣层级（默认 1）',
  `uid1` int NOT NULL DEFAULT 0 COMMENT '一级分佣会员ID',
  `uid1_name` varchar(50) NOT NULL DEFAULT '' COMMENT '一级分佣会员名',
  `price1` decimal(16,4) NOT NULL COMMENT '一级佣金金额',
  `uid2` int NOT NULL COMMENT '二级分佣会员ID',
  `uid2_name` varchar(50) NOT NULL DEFAULT '' COMMENT '二级分佣会员名',
  `puid` int NOT NULL DEFAULT 0 COMMENT '订单购买者上级会员ID',
  `puid_price` decimal(16,4) NOT NULL DEFAULT 0.000 COMMENT '上级佣金',
  `price2` decimal(16,4) NOT NULL COMMENT '二级佣金金额',
  `commision_price` decimal(16,4) NOT NULL COMMENT '本单佣金合计（字段名拼写为 commision，SQL 原文）',
  `total_commision_price` decimal(16,4) NOT NULL COMMENT '累计佣金',
  `back_commision_price` decimal(16,4) NOT NULL DEFAULT 0.00 COMMENT '已退回佣金（订单退款时回冲）',
  `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '状态 1=正常',
  `sure_status` tinyint(1) NOT NULL DEFAULT 0 COMMENT '结算状态：1=待结算 2=已结算 3=已退款 4=部分退款',
  `create_time` int NOT NULL COMMENT '创建时间',
  `sure_count` int NOT NULL DEFAULT 0 COMMENT '结算次数',
  `frozen_time` int NOT NULL DEFAULT 0 COMMENT '冻结截止时间',
  `level_price_difference` decimal(16,4) NOT NULL DEFAULT 0 COMMENT '等级差价收益',
  `vip_add_price` decimal(16,4) NOT NULL DEFAULT 0 COMMENT '分站加价部分',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='订单分销佣金';

-- ---------------------------------------------------------------------------
-- [P1] mks_order_supply — 订单供货分成/供货商结算记录
-- ---------------------------------------------------------------------------
CREATE TABLE `mks_order_supply` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT '供货结算ID',
  `order_id` bigint unsigned NOT NULL DEFAULT 0 COMMENT '订单ID',
  `supply_price` decimal(16,4) NOT NULL DEFAULT 0.00 COMMENT '供货金额（应结给供货商）',
  `back_supply_price` decimal(16,4) NOT NULL DEFAULT 0.00 COMMENT '已退回供货金额',
  `supply_id` int NOT NULL DEFAULT 0 COMMENT '供货商ID',
  `uid` int NOT NULL DEFAULT 0 COMMENT '订单会员ID',
  `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '状态',
  `sure_status` tinyint(1) NOT NULL DEFAULT 0 COMMENT '结算状态：1=待结算 2=已结算 3=已退款 4=部分退款',
  `create_time` int NOT NULL COMMENT '创建时间',
  `frozen_time` int NOT NULL DEFAULT 0 COMMENT '冻结截止时间',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='订单供货分成/供货商结算记录';

-- ---------------------------------------------------------------------------
-- [P1] mks_order_vip — 订单分站抽成：分站(vip_id)在该订单获得的分成与结算状态
-- ---------------------------------------------------------------------------
CREATE TABLE `mks_order_vip` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT '记录ID',
  `order_id` bigint unsigned NOT NULL DEFAULT 0 COMMENT '订单ID',
  `vip_id` int unsigned NOT NULL DEFAULT 1 COMMENT '分站ID',
  `uid` int NOT NULL DEFAULT 0 COMMENT '分站主会员ID',
  `commision_price` decimal(16,4) NOT NULL COMMENT '本单分站分成',
  `total_commision_price` decimal(16,4) NOT NULL COMMENT '累计分成',
  `back_commision_price` decimal(16,4) NOT NULL DEFAULT 0.00 COMMENT '已退回分成',
  `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '状态',
  `sure_status` tinyint(1) NOT NULL DEFAULT 0 COMMENT '结算状态：1=待结算 2=已结算 3=已退款 4=部分退款',
  `create_time` int NOT NULL COMMENT '创建时间',
  `frozen_time` int NOT NULL DEFAULT 0 COMMENT '冻结截止',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='订单分站抽成';

-- ---------------------------------------------------------------------------
-- [P1] mks_member_supply_money — 供货商货款流水：供货收入变动明细
-- ---------------------------------------------------------------------------
CREATE TABLE `mks_member_supply_money` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT '主键ID（自增）',
  `uid` int NOT NULL COMMENT '会员ID → member.id',
  `money` decimal(16,4) NOT NULL DEFAULT 0.00 COMMENT '金额',
  `trade_type` int NOT NULL COMMENT '业务类型（下单/退款/充值/提现等，枚举在代码常量中）',
  `change_type` tinyint(1) NOT NULL COMMENT '变动类型/变更类型',
  `before` decimal(14,4) NOT NULL DEFAULT 0.00 COMMENT '变动前值',
  `after` decimal(14,4) NOT NULL DEFAULT 0.00 COMMENT '变动后值',
  `create_time` int NOT NULL DEFAULT 0 COMMENT '创建时间（int 时间戳）',
  `extend_id` varchar(255) NOT NULL DEFAULT '' COMMENT '扩展ID（关联单号：订单/充值/提现）',
  `mark` varchar(255) NOT NULL DEFAULT '' COMMENT '标识/备注（短文本，代码中作为事件键或备注）',
  `merchant_id` int NOT NULL DEFAULT 0 COMMENT '商户ID（member.has_merchant=1 的会员）',
  `order_id` int NOT NULL DEFAULT 0 COMMENT '订单ID → order.id',
  PRIMARY KEY (`id`),
  KEY `uid` (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='供货商货款流水';

-- ---------------------------------------------------------------------------
-- [P1] mks_member_collect — 提现申请单：金额、手续费、账户快照、审核状态
-- ---------------------------------------------------------------------------
CREATE TABLE `mks_member_collect` (
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
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='提现申请单';

-- ---------------------------------------------------------------------------
-- [P1] mks_member_collect_way — 提现方式（银行卡/支付宝/微信/USDT 等）绑定与默认项
-- ---------------------------------------------------------------------------
CREATE TABLE `mks_member_collect_way` (
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
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='提现方式（银行卡/支付宝/微信/USDT 等）绑定与默认项';

-- ---------------------------------------------------------------------------
-- [P1] mks_member_admission_money — 订单分成明细（入场费）：按订单拆成本/利润/分站/供货商收益
-- ---------------------------------------------------------------------------
CREATE TABLE `mks_member_admission_money` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT '主键ID（自增）',
  `order_id` int NOT NULL DEFAULT 0 COMMENT '订单ID → order.id',
  `quantity` int unsigned NOT NULL DEFAULT 0 COMMENT '数量',
  `price` decimal(16,4) NOT NULL DEFAULT 0.000 COMMENT '价格/金额',
  `total_price` decimal(16,4) NOT NULL DEFAULT 0.000 COMMENT '总金额',
  `rate_value` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT '费率（%）',
  `rate_price` decimal(14,4) NOT NULL DEFAULT 0.000 COMMENT '费率计算金额',
  `extract_price` decimal(16,4) NOT NULL DEFAULT 0.000 COMMENT '抽取金额（平台抽成）',
  `admission_price` decimal(16,4) NOT NULL DEFAULT 0.000 COMMENT '入场费（平台从本单获得的固定分成）',
  `purchase_price` decimal(16,4) NOT NULL DEFAULT 0.000 COMMENT '进货价',
  `total_purchase_price` decimal(16,4) NOT NULL DEFAULT 0.000 COMMENT '进货价合计',
  `net_profit_price` decimal(16,4) NOT NULL DEFAULT 0.000 COMMENT '净利润',
  `refund_price` decimal(16,4) NOT NULL DEFAULT 0.000 COMMENT '退款金额',
  `create_time` int NOT NULL DEFAULT 0 COMMENT '创建时间（int 时间戳）',
  `goods_id` int NOT NULL DEFAULT 0 COMMENT '商品ID → goods.id',
  `uid` int NOT NULL DEFAULT 0 COMMENT '会员ID → member.id',
  `nickname` varchar(50) NOT NULL DEFAULT '' COMMENT '昵称',
  `supplier_id` int NOT NULL DEFAULT 0 COMMENT '供货商ID（member 中 merchant 身份）',
  `goods_name` varchar(255) NOT NULL DEFAULT '' COMMENT '商品名称（快照或冗余）',
  `goods_type` tinyint(1) NOT NULL DEFAULT 1 COMMENT '商品类型（1=卡密 2=虚拟 3=卡券，推断）',
  `vip_profit_price` decimal(16,4) NOT NULL DEFAULT 0.000 COMMENT '分站利润',
  `vip_purchase_price` decimal(16,4) NOT NULL DEFAULT 0.000 COMMENT '分站进货价',
  `order_price` decimal(16,4) NOT NULL DEFAULT 0.00 COMMENT '订单利润',
  PRIMARY KEY (`id`),
  KEY `order_id` (`order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='订单分成明细（入场费）';

-- ---------------------------------------------------------------------------
-- [P1] mks_money_day — 财务对账日表：按日×用户汇总订单额/成本/退款/佣金/充值/加扣款/期初期末余额
-- ---------------------------------------------------------------------------
CREATE TABLE `mks_money_day` (
  `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT '主键',
  `total_create_time` varchar(32) NOT NULL DEFAULT '' COMMENT '创建时间',
  `uid` int NOT NULL DEFAULT 0 COMMENT '用户ID',
  `order_order_price` decimal(16,4) NOT NULL DEFAULT 0.0000 COMMENT '订单利润',
  `order_total_price` decimal(16,4) NOT NULL DEFAULT 0.0000 COMMENT '订单金额',
  `order_cost_value` decimal(16,5) NOT NULL DEFAULT 0.0000 COMMENT '订单成本',
  `order_back_price` decimal(16,4) NOT NULL DEFAULT 0.0000 COMMENT '退款金额',
  `order_commission_money` decimal(16,4) NOT NULL DEFAULT 0.0000 COMMENT '订单佣金',
  `recharge_money` decimal(16,4) NOT NULL DEFAULT 0.0000 COMMENT '充值金额',
  `recharge_money_service` decimal(16,4) NOT NULL DEFAULT 0.0000 COMMENT '手续费',
  `money_add_money` decimal(16,4) NOT NULL DEFAULT 0.0000 COMMENT '加款',
  `money_sub_money` decimal(16,4) NOT NULL DEFAULT 0.0000 COMMENT '扣款',
  `money_before_balance` decimal(16,4) NOT NULL DEFAULT 0.0000 COMMENT '期初余额',
  `money_after_balance` decimal(16,4) NOT NULL DEFAULT 0.0000 COMMENT '期末余额',
  PRIMARY KEY (`id`),
  KEY `uid_create_time` (`total_create_time`,`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='订单统计表';

-- ---------------------------------------------------------------------------
-- [P1] mks_statics — 订单统计日表：按日汇总订单量/订单额/利润
-- ---------------------------------------------------------------------------
CREATE TABLE `mks_statics` (
  `total_create_time` varchar(20) NOT NULL DEFAULT '0000-00-00' COMMENT '创建时间',
  `order_order_price` decimal(16,4) NOT NULL DEFAULT 0.0000 COMMENT '订单利润',
  `order_total_price` decimal(16,4) NOT NULL DEFAULT 0.0000 COMMENT '订单金额',
  `order_order_id` int NOT NULL DEFAULT 0 COMMENT '订单数量',
  `update_time` int NOT NULL DEFAULT 0 COMMENT '更新时间',
  PRIMARY KEY (`total_create_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='订单统计表';

-- ---------------------------------------------------------------------------
-- [P1] mks_complain — 订单投诉/售后工单：主题、内容、处理状态、对接渠道回传
-- ---------------------------------------------------------------------------
CREATE TABLE `mks_complain` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT '主键ID（自增）',
  `uid` bigint NOT NULL DEFAULT 0 COMMENT '会员ID → member.id',
  `host` varchar(255) NOT NULL DEFAULT '' COMMENT '域名/主机',
  `nickname` varchar(50) NOT NULL DEFAULT '' COMMENT '昵称',
  `type_id` int NOT NULL COMMENT '类型ID → complain_type.id',
  `goods_name` varchar(255) NOT NULL DEFAULT '' COMMENT '商品名称（快照或冗余）',
  `goods_id` int NOT NULL DEFAULT 0 COMMENT '商品ID → goods.id',
  `subject` varchar(255) NOT NULL DEFAULT '' COMMENT '主题',
  `ordersn` varchar(50) NOT NULL DEFAULT '' COMMENT '订单号（唯一，前台展示/对账）',
  `order_id` int NOT NULL DEFAULT 0 COMMENT '订单ID → order.id',
  `content` text NOT NULL COMMENT '内容（HTML/JSON）',
  `status` int NOT NULL DEFAULT 0 COMMENT '售后状态：1=等待处理 2=正在处理 3=处理完成 4=终止处理',
  `create_time` int NOT NULL DEFAULT 0 COMMENT '创建时间（int 时间戳）',
  `update_time` int NOT NULL COMMENT '更新时间（int 时间戳）',
  `delete_time` int NOT NULL DEFAULT 0 COMMENT '软删除时间（0=未删除）',
  `add_count` int NOT NULL DEFAULT 1 COMMENT '追加次数（补充说明次数）',
  `reply_time` int unsigned NOT NULL DEFAULT 0 COMMENT '回复时间',
  `supplier_id` bigint unsigned NOT NULL DEFAULT 0 COMMENT '供货商ID（member 中 merchant 身份）',
  `supplier_name` varchar(50) NOT NULL DEFAULT '' COMMENT '供货商名称快照',
  `supplier_type` tinyint(1) NOT NULL DEFAULT 0 COMMENT '供货商类型',
  `docking_id` int NOT NULL DEFAULT 0 COMMENT '对接供货渠道ID → docking.id',
  `docking_info` varchar(299) NOT NULL DEFAULT '' COMMENT '渠道订单信息/渠道侧单号',
  `sync_time` int NOT NULL DEFAULT 0 COMMENT '最近同步时间',
  `is_push` int NOT NULL DEFAULT 2 COMMENT '是否已推送（默认 2）',
  `notify_url` varchar(255) NOT NULL DEFAULT '' COMMENT '回调通知地址',
  `total_price` decimal(16,4) NOT NULL DEFAULT 0.000 COMMENT '总金额',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='订单投诉/售后工单';

-- ---------------------------------------------------------------------------
-- [P1] mks_complain_type — 投诉主题/快捷回复话术字典（type 区分投诉类型与回复模板）
-- ---------------------------------------------------------------------------
CREATE TABLE `mks_complain_type` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT '主键ID（自增）',
  `name` varchar(255) NOT NULL COMMENT '名称',
  `type` tinyint(1) unsigned NOT NULL DEFAULT 1 COMMENT '类型标识（区分同表多种用途，码值见模块说明）',
  `sort` int NOT NULL COMMENT '排序值（越大越前，或正序，按模块实现）',
  `status` tinyint NOT NULL DEFAULT 1 COMMENT '状态机标识（1=正常/启用，0=关闭；各表具体码值含义见 §8.2）',
  `create_time` int NOT NULL COMMENT '创建时间（int 时间戳）',
  `update_time` int NOT NULL COMMENT '更新时间（int 时间戳）',
  `delete_time` int NOT NULL DEFAULT 0 COMMENT '软删除时间（0=未删除）',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='投诉主题/快捷回复话术字典（type 区分投诉类型与回复模板）' AUTO_INCREMENT=68;

-- ---------------------------------------------------------------------------
-- [P1] mks_member_address — 会员收货地址：省市区/街道 id + 文本冗余
-- ---------------------------------------------------------------------------
CREATE TABLE `mks_member_address` (
  `id` int NOT NULL AUTO_INCREMENT COMMENT '主键ID（自增）',
  `merchant_id` int unsigned DEFAULT 0 COMMENT '商户ID（member.has_merchant=1 的会员）',
  `email` varchar(50) DEFAULT '' COMMENT '邮箱',
  `uid` int unsigned DEFAULT 0 COMMENT '会员ID → member.id',
  `province` varchar(64) DEFAULT '' COMMENT '省份名称',
  `province_id` int unsigned DEFAULT 0 COMMENT '省份ID → provinces/city_area.id',
  `city` varchar(64) DEFAULT '' COMMENT '城市名称',
  `city_id` int unsigned DEFAULT 0 COMMENT '城市ID',
  `area` varchar(64) DEFAULT '' COMMENT '区县名称',
  `area_id` int unsigned DEFAULT 0 COMMENT '区县ID',
  `street` varchar(100) DEFAULT '' COMMENT '街道名称',
  `street_id` int DEFAULT 0 COMMENT '街道ID',
  `detail` varchar(200) DEFAULT '' COMMENT '详细地址',
  `address` varchar(500) DEFAULT '' COMMENT '地址（省市区+详细地址拼接文本）',
  `is_default` tinyint(1) unsigned DEFAULT 0 COMMENT '是否默认项',
  `real_name` varchar(100) DEFAULT '' COMMENT '真实姓名',
  `mobile` varchar(20) DEFAULT '' COMMENT '手机号',
  `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '状态机标识（1=正常/启用，0=关闭；各表具体码值含义见 §8.2）',
  `create_time` int unsigned DEFAULT 0 COMMENT '创建时间（int 时间戳）',
  `update_time` int unsigned DEFAULT 0 COMMENT '更新时间（int 时间戳）',
  `lng` varchar(20) DEFAULT '' COMMENT '经度',
  `lat` varchar(20) DEFAULT '' COMMENT '纬度',
  PRIMARY KEY (`id`),
  KEY `merchant_id` (`merchant_id`),
  KEY `province_id` (`province_id`),
  KEY `city_id` (`city_id`),
  KEY `area_id` (`area_id`),
  KEY `district_id` (`street_id`),
  KEY `member_id` (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='会员收货地址';

-- ---------------------------------------------------------------------------
-- [P1] mks_member_upgrade_log — 会员升级日志：升级前后等级与当时的升级指标快照
-- ---------------------------------------------------------------------------
CREATE TABLE `mks_member_upgrade_log` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT '主键ID（自增）',
  `uid` int NOT NULL DEFAULT 0 COMMENT '会员ID → member.id',
  `before_level_id` int NOT NULL DEFAULT 0 COMMENT '升级前等级ID',
  `to_level_id` int NOT NULL DEFAULT 0 COMMENT '升级后等级ID',
  `then_invite_num` int unsigned NOT NULL DEFAULT 0 COMMENT '升级时邀请人数快照',
  `then_commission_check` decimal(16,4) NOT NULL DEFAULT 0.00 COMMENT '升级时累计佣金快照',
  `then_total_commission_check` decimal(16,4) NOT NULL DEFAULT 0.00 COMMENT '升级时团队累计佣金快照',
  `create_time` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间（int 时间戳）',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='会员升级日志';

-- ---------------------------------------------------------------------------
-- [P1] mks_member_invoice — 前台用户发票管理：抬头/税号/金额/状态全流程
-- ---------------------------------------------------------------------------
CREATE TABLE `mks_member_invoice` (
  `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT '主键ID，自增',
  `user_id` int unsigned NOT NULL COMMENT '关联用户表的用户ID，前台用户唯一标识',
  `invoice_no` varchar(50) DEFAULT NULL COMMENT '发票号码，税务系统生成的唯一发票编号',
  `invoice_code` varchar(20) DEFAULT NULL COMMENT '发票代码，区分发票类型和所属地区',
  `invoice_type` int NOT NULL COMMENT '发票种类：1=增值税普通发票 2=增值税专用发票 3=电子普通发票',
  `title_type` int NOT NULL COMMENT '抬头类型：1=个人 2=企业/单位',
  `title` varchar(200) NOT NULL COMMENT '发票抬头：个人姓名或企业全称',
  `tax_no` varchar(50) DEFAULT NULL COMMENT '纳税人识别号：企业抬头必填，个人可为空',
  `register_address` varchar(500) DEFAULT NULL COMMENT '注册地址：专票必填，普票可选',
  `register_phone` varchar(20) DEFAULT NULL COMMENT '注册电话：专票必填，普票可选',
  `bank_name` varchar(100) DEFAULT NULL COMMENT '开户行：专票必填，普票可选',
  `bank_account` varchar(50) DEFAULT NULL COMMENT '银行账号：专票必填，普票可选',
  `amount` decimal(16,4) NOT NULL COMMENT '发票金额（含税），保留2位小数',
  `tax_amount` decimal(16,4) DEFAULT 0.00 COMMENT '税额，保留2位小数',
  `apply_time` int NOT NULL DEFAULT 0 COMMENT '用户申请开票时间',
  `issue_time` int NOT NULL DEFAULT 0 COMMENT '实际开票时间，未开票则为空',
  `status` int NOT NULL DEFAULT 0 COMMENT '发票状态：0=待审核 1=审核通过(待开票) 2=已开票 3=已作废 4=审核驳回 5=已红冲',
  `reject_reason` varchar(500) DEFAULT NULL COMMENT '驳回原因：状态为4时必填',
  `invoice_pdf_url` varchar(500) DEFAULT NULL COMMENT '电子发票PDF下载地址：已开票时必填',
  `operator_id` int unsigned DEFAULT NULL COMMENT '操作人ID：后台审核/开票人员ID',
  `remark` varchar(500) DEFAULT NULL COMMENT '用户备注/补充说明',
  `is_deleted` int NOT NULL DEFAULT 0 COMMENT '逻辑删除：0-未删除，1-已删除',
  `create_time` int NOT NULL DEFAULT 0 COMMENT '创建时间',
  `update_time` int NOT NULL DEFAULT 0 COMMENT '更新时间',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_invoice_no` (`invoice_no`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_status` (`status`),
  KEY `idx_apply_time` (`apply_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='前台用户发票管理表';


-- ============================================================================
-- P2 对接供货（做第三方渠道 / 供货商才需要）  （5 张表）
-- ============================================================================
-- ---------------------------------------------------------------------------
-- [P2] mks_docking — 对接供货渠道：接口地址、账号密钥、同步开关、余额预警、自动补单策略
-- ---------------------------------------------------------------------------
CREATE TABLE `mks_docking` (
  `id` int NOT NULL AUTO_INCREMENT COMMENT '渠道ID',
  `uid` int NOT NULL DEFAULT 0 COMMENT '渠道归属会员ID（供货商自有渠道）',
  `type` varchar(50) NOT NULL DEFAULT '' COMMENT '对接协议类型标识（对应后端 driver，如 mks/dujiaoka 等）',
  `name` varchar(255) NOT NULL DEFAULT '' COMMENT '渠道名称',
  `url` varchar(255) NOT NULL DEFAULT '' COMMENT '接口基地址',
  `b_url_open` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否启用备用地址',
  `b_url` varchar(2000) NOT NULL DEFAULT '' COMMENT '备用接口地址',
  `balance` varchar(255) NOT NULL DEFAULT '0.000' COMMENT '渠道余额（varchar 存储，兼容非数字）',
  `docking_money` decimal(16,4) NOT NULL DEFAULT 0 COMMENT '本系统在该渠道的预存金额',
  `warn` decimal(16,4) NOT NULL DEFAULT 0 COMMENT '余额预警阈值',
  `warn_open` tinyint(1) NOT NULL DEFAULT 1 COMMENT '是否开启余额预警',
  `warn_time` int NOT NULL DEFAULT 0 COMMENT '最近预警时间',
  `username` text DEFAULT NULL COMMENT '渠道账号（text，可能加密）',
  `password` text DEFAULT NULL COMMENT '渠道密码/密钥',
  `pay_userid` text DEFAULT NULL COMMENT '渠道支付账号',
  `pay_password` text DEFAULT NULL COMMENT '渠道支付密码',
  `params` text DEFAULT NULL COMMENT '渠道扩展参数 JSON',
  `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '状态：1=启用 0=停用',
  `docking_type` tinyint(1) NOT NULL DEFAULT 1 COMMENT '对接模式 1=商品/订单同步（推断）',
  `sync_config` text DEFAULT NULL COMMENT '字段映射配置 JSON（渠道字段 ↔ 本地字段）',
  `create_time` int NOT NULL DEFAULT 0 COMMENT '创建时间',
  `update_time` int NOT NULL DEFAULT 0 COMMENT '更新时间',
  `sync_goods` int NOT NULL DEFAULT 3 COMMENT '商品同步策略（默认 3）',
  `sync_order` int NOT NULL DEFAULT 2 COMMENT '订单同步策略（默认 2）',
  `sync_order_config` varchar(255) DEFAULT '[1,2]' COMMENT '订单同步配置（默认 [1,2]）',
  `sync_order_time` int NOT NULL DEFAULT 1 COMMENT '订单同步间隔（分钟）',
  `sync_goods_status` tinyint(1) NOT NULL DEFAULT 2 COMMENT '商品同步状态（默认 2）',
  `m_goods_url` varchar(500) NOT NULL DEFAULT '' COMMENT '手机端商品接口地址',
  `goods_url` varchar(500) NOT NULL DEFAULT '' COMMENT 'PC 端商品接口地址',
  `m_order_url` varchar(500) NOT NULL DEFAULT '' COMMENT '手机端下单接口',
  `order_url` varchar(500) NOT NULL DEFAULT '' COMMENT 'PC 端下单接口',
  `sort` int NOT NULL DEFAULT 0 COMMENT '排序',
  `log_config` text DEFAULT NULL COMMENT '日志记录配置 JSON',
  `interval` int NOT NULL DEFAULT 10 COMMENT '同步间隔（秒）',
  `frequency` int NOT NULL DEFAULT 3 COMMENT '间隔内同步次数',
  `auto_back` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否自动回退（渠道失败后自动退单）',
  `auto_back_time` float NOT NULL DEFAULT 0 COMMENT '自动回退延迟',
  `auto_back_can_bu` tinyint(1) NOT NULL DEFAULT 0 COMMENT '回退后是否允许补单',
  `is_while` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否循环同步',
  `while_ids` varchar(1000) NOT NULL DEFAULT '' COMMENT '循环同步的分类/商品ID集合',
  `auto_back_fail_count` int NOT NULL DEFAULT 0 COMMENT '回退失败次数',
  `auto_back_fail_time` float NOT NULL DEFAULT 0 COMMENT '回退失败时间',
  `msg_url` varchar(255) NOT NULL DEFAULT '' COMMENT '消息回调地址',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='对接供货渠道';

-- ---------------------------------------------------------------------------
-- [P2] mks_docking_log — 渠道接口日志：请求/响应原文、状态、耗时(create_time)
-- ---------------------------------------------------------------------------
CREATE TABLE `mks_docking_log` (
  `id` bigint NOT NULL AUTO_INCREMENT COMMENT '日志ID',
  `docking_id` int NOT NULL DEFAULT 0 COMMENT '渠道ID → docking.id',
  `log_type` tinyint(1) NOT NULL DEFAULT 1 COMMENT '日志类型 1=商品同步 2=下单 3=查单（推断）',
  `docking_goods_id` varchar(50) NOT NULL DEFAULT '' COMMENT '渠道商品ID',
  `ordersn` varchar(64) NOT NULL DEFAULT '' COMMENT '本地订单号',
  `goods_id` varchar(20) NOT NULL DEFAULT '' COMMENT '本地商品ID',
  `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '状态 1=成功 0=失败（推断）',
  `content` varchar(2000) NOT NULL DEFAULT '' COMMENT '摘要信息',
  `create_time` int NOT NULL DEFAULT 0 COMMENT '发生时间',
  `order_id` int NOT NULL DEFAULT 0 COMMENT '本地订单ID',
  `req` text DEFAULT NULL COMMENT '请求报文原文',
  `head` text DEFAULT NULL COMMENT '请求头',
  `res` longtext DEFAULT NULL COMMENT '响应报文原文',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='渠道接口日志';

-- ---------------------------------------------------------------------------
-- [P2] mks_goods_stock_info — 库存详情表：存对接渠道返回的原始商品详情 JSON（按 md5 去重）
-- ---------------------------------------------------------------------------
CREATE TABLE `mks_goods_stock_info` (
  `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT '主键ID（自增）',
  `docking_goods_info` longtext DEFAULT NULL COMMENT '渠道商品详情原文（JSON）',
  `docking_goods_info_md5` varchar(32) NOT NULL DEFAULT '' COMMENT '数据的md5',
  `docking_id` int NOT NULL DEFAULT 0 COMMENT '跨系统ID',
  `docking_goods_id` varchar(64) NOT NULL DEFAULT '0' COMMENT '渠道商品ID',
  `docking_cate_id` varchar(255) NOT NULL DEFAULT '0' COMMENT '跨系统商品分类ID',
  `goods_name` varchar(500) NOT NULL DEFAULT '' COMMENT '商品名称（快照或冗余）',
  `goods_type` tinyint(1) NOT NULL DEFAULT 0 COMMENT '商品类型（1=卡密 2=虚拟 3=卡券，推断）',
  PRIMARY KEY (`id`),
  KEY `docking_id` (`docking_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='库存详情表';

-- ---------------------------------------------------------------------------
-- [P2] mks_goods_notify_log — 商品变更回调通知队列（自建回调 URL）
-- ---------------------------------------------------------------------------
CREATE TABLE `mks_goods_notify_log` (
  `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT '主键ID（自增）',
  `uid` int DEFAULT 0 COMMENT '会员ID → member.id',
  `goods_id` int NOT NULL DEFAULT 0 COMMENT '商品ID → goods.id',
  `goods_sku_id` int NOT NULL DEFAULT 0 COMMENT '商品规格/库存行ID → goods_stock.id',
  `update_data` text DEFAULT NULL COMMENT '变更数据 JSON（回调/推送内容）',
  `update_time` int NOT NULL DEFAULT 0 COMMENT '更新时间（int 时间戳）',
  `create_time` int NOT NULL DEFAULT 0 COMMENT '创建时间（int 时间戳）',
  `notify_url` varchar(255) NOT NULL DEFAULT '' COMMENT '回调通知地址',
  `notify_status` tinyint(1) NOT NULL DEFAULT 0 COMMENT '回调状态',
  `notify_count` int NOT NULL DEFAULT 0 COMMENT '回调尝试次数',
  `content` varchar(2000) NOT NULL DEFAULT '' COMMENT '内容（HTML/JSON）',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='商品变更回调通知队列（自建回调 URL）';

-- ---------------------------------------------------------------------------
-- [P2] mks_goods_notify_agiso — 阿奇索/agiso 商品变更推送队列（guid/mer_id）
-- ---------------------------------------------------------------------------
CREATE TABLE `mks_goods_notify_agiso` (
  `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT '主键ID（自增）',
  `app_type` tinyint(1) NOT NULL DEFAULT 0 COMMENT '应用类型（agiso 推送）',
  `uid` int DEFAULT 0 COMMENT '会员ID → member.id',
  `goods_id` int NOT NULL DEFAULT 0 COMMENT '商品ID → goods.id',
  `goods_type` tinyint(1) NOT NULL DEFAULT 0 COMMENT '商品类型（1=卡密 2=虚拟 3=卡券，推断）',
  `goods_sku_id` int NOT NULL DEFAULT 0 COMMENT '商品规格/库存行ID → goods_stock.id',
  `update_data` text DEFAULT NULL COMMENT '变更数据 JSON（回调/推送内容）',
  `update_time` int NOT NULL DEFAULT 0 COMMENT '更新时间（int 时间戳）',
  `create_time` int NOT NULL DEFAULT 0 COMMENT '创建时间（int 时间戳）',
  `guid` varchar(255) NOT NULL DEFAULT '' COMMENT '外部唯一标识 guid',
  `notify_url` varchar(255) NOT NULL DEFAULT '' COMMENT '回调通知地址',
  `notify_status` tinyint(1) NOT NULL DEFAULT 0 COMMENT '回调状态',
  `notify_count` int NOT NULL DEFAULT 0 COMMENT '回调尝试次数',
  `content` varchar(2000) NOT NULL DEFAULT '' COMMENT '内容（HTML/JSON）',
  `mer_id` int NOT NULL DEFAULT 0 COMMENT '商户ID',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='阿奇索/agiso 商品变更推送队列（guid/mer_id）';


-- ============================================================================
-- P3 运营内容与通知（公告/广告/活动/站内信/邮件）  （16 张表）
-- ============================================================================
-- ---------------------------------------------------------------------------
-- [P3] mks_news — 公告/文章主表：标题、正文、分类、多端可见、点赞/阅读数
-- ---------------------------------------------------------------------------
CREATE TABLE `mks_news` (
  `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT '主键ID（自增）',
  `vip_id` int NOT NULL DEFAULT 0 COMMENT '分站ID（0=主站）',
  `uid` int NOT NULL DEFAULT 0 COMMENT '会员ID → member.id',
  `category_id` int unsigned NOT NULL DEFAULT 0 COMMENT '分类ID → goods_category/news_category.id',
  `title` varchar(255) NOT NULL DEFAULT '' COMMENT '标题',
  `is_top` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否置顶',
  `color` varchar(255) NOT NULL DEFAULT '' COMMENT '颜色值',
  `status` tinyint(1) NOT NULL DEFAULT 0 COMMENT '状态机标识（1=正常/启用，0=关闭；各表具体码值含义见 §8.2）',
  `is_link` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否外链',
  `desc` varchar(2000) NOT NULL DEFAULT '' COMMENT '描述',
  `content` text DEFAULT NULL COMMENT '内容（HTML/JSON）',
  `read_count` int NOT NULL DEFAULT 0 COMMENT '阅读数',
  `author` varchar(255) NOT NULL DEFAULT '系统' COMMENT '作者',
  `url` varchar(255) NOT NULL COMMENT '链接地址',
  `create_time` int NOT NULL DEFAULT 0 COMMENT '创建时间（int 时间戳）',
  `sort` int NOT NULL DEFAULT 0 COMMENT '排序值（越大越前，或正序，按模块实现）',
  `update_time` int NOT NULL DEFAULT 0 COMMENT '更新时间（int 时间戳）',
  `img` varchar(500) NOT NULL DEFAULT '' COMMENT '图片地址',
  `can_pc` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'PC 端可见/可用',
  `can_h5` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'H5 端可见/可用',
  `can_xcx` tinyint(1) NOT NULL DEFAULT 0 COMMENT '小程序端可见/可用',
  `can_app` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'APP 端可见/可用',
  `pid` int NOT NULL DEFAULT 0 COMMENT '父级ID（树形结构父节点，0=顶级）',
  `likes` int NOT NULL DEFAULT 0 COMMENT '点赞数',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='公告/文章主表';

-- ---------------------------------------------------------------------------
-- [P3] mks_news_category — 公告分类（type 区分公告/文章/帮助）
-- ---------------------------------------------------------------------------
CREATE TABLE `mks_news_category` (
  `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT '主键ID（自增）',
  `type` tinyint(1) NOT NULL DEFAULT 1 COMMENT '类型标识（区分同表多种用途，码值见模块说明）',
  `vip_id` int NOT NULL COMMENT '分站ID（0=主站）',
  `pid` int NOT NULL DEFAULT 0 COMMENT '父级ID（树形结构父节点，0=顶级）',
  `name` varchar(255) NOT NULL COMMENT '名称',
  `sort` int NOT NULL COMMENT '排序值（越大越前，或正序，按模块实现）',
  `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '状态机标识（1=正常/启用，0=关闭；各表具体码值含义见 §8.2）',
  `create_time` int NOT NULL DEFAULT 0 COMMENT '创建时间（int 时间戳）',
  `can_delete` tinyint(1) NOT NULL DEFAULT 1 COMMENT '是否允许删除',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='公告分类（type 区分公告/文章/帮助）' AUTO_INCREMENT=10000;

-- ---------------------------------------------------------------------------
-- [P3] mks_news_likes — 公告点赞记录（uid×new_id）
-- ---------------------------------------------------------------------------
CREATE TABLE `mks_news_likes` (
  `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `is_like` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否点赞',
  `uid` int NOT NULL DEFAULT 0 COMMENT '用户ID',
  `vip_id` int NOT NULL DEFAULT 0 COMMENT '分站ID',
  `new_id` int NOT NULL DEFAULT 0 COMMENT '文章ID',
  `create_time` int NOT NULL DEFAULT 0 COMMENT '创建时间',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='公告点赞';

-- ---------------------------------------------------------------------------
-- [P3] mks_ad — 广告位内容：图片/视频/链接、投放端(can_pc/h5/xcx/app)、有效期
-- ---------------------------------------------------------------------------
CREATE TABLE `mks_ad` (
  `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT '主键ID（自增）',
  `vip_id` int NOT NULL DEFAULT 0 COMMENT '分站ID（0=主站）',
  `ad_type_id` int NOT NULL DEFAULT 0 COMMENT '广告位类型ID → ad_type.id',
  `title` varchar(50) NOT NULL DEFAULT '' COMMENT '标题',
  `desc` varchar(255) NOT NULL COMMENT '描述',
  `link` varchar(255) NOT NULL DEFAULT '' COMMENT '链接地址',
  `img` varchar(500) NOT NULL DEFAULT '' COMMENT '图片地址',
  `sort` int NOT NULL COMMENT '排序值（越大越前，或正序，按模块实现）',
  `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '状态机标识（1=正常/启用，0=关闭；各表具体码值含义见 §8.2）',
  `create_time` int NOT NULL DEFAULT 0 COMMENT '创建时间（int 时间戳）',
  `expire_time` bigint NOT NULL DEFAULT 4102415999 COMMENT '过期/失效时间',
  `ad_type` tinyint NOT NULL DEFAULT 0 COMMENT '广告类型（0=图片 1=视频，推断）',
  `video` varchar(255) NOT NULL DEFAULT '' COMMENT '视频地址',
  `pid` int NOT NULL DEFAULT 0 COMMENT '父级ID（树形结构父节点，0=顶级）',
  `can_pc` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'PC 端可见/可用',
  `can_h5` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'H5 端可见/可用',
  `can_xcx` tinyint(1) NOT NULL DEFAULT 0 COMMENT '小程序端可见/可用',
  `can_app` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'APP 端可见/可用',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='广告位内容' AUTO_INCREMENT=50;

-- ---------------------------------------------------------------------------
-- [P3] mks_ad_type — 广告位类型字典（PC/H5 各位置）
-- ---------------------------------------------------------------------------
CREATE TABLE `mks_ad_type` (
  `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT '主键ID（自增）',
  `vip_id` int NOT NULL COMMENT '分站ID（0=主站）',
  `pid` int NOT NULL COMMENT '父级ID（树形结构父节点，0=顶级）',
  `name` varchar(50) NOT NULL DEFAULT '' COMMENT '名称',
  `type` tinyint NOT NULL DEFAULT 1 COMMENT '类型标识（区分同表多种用途，码值见模块说明）',
  `sort` int NOT NULL COMMENT '排序值（越大越前，或正序，按模块实现）',
  `status` tinyint NOT NULL COMMENT '状态机标识（1=正常/启用，0=关闭；各表具体码值含义见 §8.2）',
  `create_time` int NOT NULL COMMENT '创建时间（int 时间戳）',
  `can_delete` tinyint(1) NOT NULL DEFAULT 1 COMMENT '是否允许删除',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='广告位类型字典（PC/H5 各位置）' AUTO_INCREMENT=10000;

-- ---------------------------------------------------------------------------
-- [P3] mks_friend_link — 友情链接
-- ---------------------------------------------------------------------------
CREATE TABLE `mks_friend_link` (
  `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT '主键ID（自增）',
  `vip_id` int NOT NULL DEFAULT 0 COMMENT '分站ID（0=主站）',
  `name` varchar(50) NOT NULL DEFAULT '' COMMENT '名称',
  `link` varchar(100) NOT NULL COMMENT '链接地址',
  `img` varchar(500) NOT NULL DEFAULT '' COMMENT '图片地址',
  `status` tinyint(1) NOT NULL DEFAULT 0 COMMENT '状态机标识（1=正常/启用，0=关闭；各表具体码值含义见 §8.2）',
  `sort` int NOT NULL DEFAULT 0 COMMENT '排序值（越大越前，或正序，按模块实现）',
  `create_time` int NOT NULL DEFAULT 0 COMMENT '创建时间（int 时间戳）',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='友情链接' AUTO_INCREMENT=30;

-- ---------------------------------------------------------------------------
-- [P3] mks_search_key — 热搜词/搜索框推荐词（含颜色、热度、排序）
-- ---------------------------------------------------------------------------
CREATE TABLE `mks_search_key` (
  `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT '主键ID（自增）',
  `vip_id` int NOT NULL DEFAULT 0 COMMENT '分站ID（0=主站）',
  `isbox` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否显示在搜索框内推荐位',
  `color` varchar(16) NOT NULL DEFAULT '#000' COMMENT '颜色值',
  `ishot` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否热门（高亮）',
  `name` varchar(255) NOT NULL DEFAULT '' COMMENT '名称',
  `sort` int NOT NULL DEFAULT 0 COMMENT '排序值（越大越前，或正序，按模块实现）',
  `num` int NOT NULL DEFAULT 0 COMMENT '数量',
  `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '状态机标识（1=正常/启用，0=关闭；各表具体码值含义见 §8.2）',
  `create_time` int NOT NULL DEFAULT 0 COMMENT '创建时间（int 时间戳）',
  PRIMARY KEY (`id`),
  KEY `vip_id` (`vip_id`,`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='热搜词/搜索框推荐词（含颜色、热度、排序）' AUTO_INCREMENT=10;

-- ---------------------------------------------------------------------------
-- [P3] mks_activity — 平台活动/公告弹窗：标题、内容、起止时间、置顶、链接
-- ---------------------------------------------------------------------------
CREATE TABLE `mks_activity` (
  `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT '活动ID',
  `vip_id` int NOT NULL COMMENT '分站ID',
  `uid` int NOT NULL DEFAULT 0 COMMENT '创建者',
  `title` varchar(255) NOT NULL DEFAULT '' COMMENT '活动标题',
  `is_top` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否置顶',
  `color` varchar(255) NOT NULL DEFAULT '' COMMENT '标题颜色',
  `status` tinyint(1) NOT NULL DEFAULT 0 COMMENT '状态（默认 0=关闭）',
  `is_link` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否外链',
  `content` text NOT NULL COMMENT '活动内容 HTML',
  `url` varchar(255) NOT NULL DEFAULT '' COMMENT '跳转地址',
  `create_time` int NOT NULL DEFAULT 0 COMMENT '创建时间',
  `update_time` int NOT NULL DEFAULT 0 COMMENT '更新时间',
  `img` varchar(255) NOT NULL DEFAULT '0' COMMENT '活动图片',
  `start_time` int NOT NULL DEFAULT 0 COMMENT '开始时间',
  `end_time` int NOT NULL DEFAULT 0 COMMENT '结束时间',
  `short_title` varchar(100) NOT NULL DEFAULT '' COMMENT '短标题',
  `title_tip` varchar(100) NOT NULL DEFAULT '' COMMENT '标题提示',
  `sort` int NOT NULL DEFAULT 0 COMMENT '排序',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='平台活动/公告弹窗';

-- ---------------------------------------------------------------------------
-- [P3] mks_notice — 通知模板主表：按 mark 定义事件，各渠道(站内/APP/公众号/小程序/短信/邮件)文案
-- ---------------------------------------------------------------------------
CREATE TABLE `mks_notice` (
  `id` int NOT NULL AUTO_INCREMENT COMMENT '通知模板ID',
  `mark` varchar(50) NOT NULL DEFAULT '' COMMENT '事件标识（代码中按 mark 触发，如 send_neworder）',
  `name` varchar(50) NOT NULL DEFAULT '' COMMENT '事件名称',
  `title` varchar(100) NOT NULL DEFAULT '' COMMENT '通知标题',
  `is_system` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否发站内信 0=否 1=发给会员 2=发给后台（推断，结合 send_open_vip=2）',
  `is_app` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否 APP 推送',
  `is_wechat` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否公众号模板消息（0/1/2，2=按配置）',
  `is_routine` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否小程序订阅消息',
  `is_sms` tinyint(1) NOT NULL DEFAULT 2 COMMENT '是否短信（默认 2=按配置）',
  `is_email` tinyint(1) NOT NULL COMMENT '是否邮件',
  `email_title` varchar(255) NOT NULL DEFAULT '' COMMENT '邮件标题',
  `email_text` longtext DEFAULT NULL COMMENT '邮件正文模板',
  `is_ent_wechat` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否企业微信',
  `system_title` varchar(256) NOT NULL DEFAULT '' COMMENT '站内信标题模板',
  `system_text` varchar(512) NOT NULL DEFAULT '' COMMENT '站内信正文模板',
  `sms_text` varchar(255) NOT NULL DEFAULT '' COMMENT '短信模板内容',
  `app_id` int NOT NULL DEFAULT 0 COMMENT 'APP 推送模板ID',
  `wechat_id` varchar(50) NOT NULL DEFAULT '0' COMMENT '公众号模板ID',
  `routine_id` varchar(50) NOT NULL DEFAULT '0' COMMENT '小程序模板ID',
  `sms_id` varchar(50) NOT NULL DEFAULT '' COMMENT '短信模板ID',
  `ent_wechat_text` varchar(512) NOT NULL DEFAULT '' COMMENT '企业微信模板内容',
  `variable` varchar(200) NOT NULL DEFAULT '' COMMENT '可用变量列表（如 ${订单号}）',
  `notice_params` text DEFAULT NULL COMMENT '参数定义 JSON',
  `url` varchar(512) NOT NULL DEFAULT '' COMMENT '点击跳转地址',
  `type` tinyint(1) NOT NULL DEFAULT 1 COMMENT '通知类型 1=用户 2=后台（推断）',
  `create_time` int NOT NULL DEFAULT 0 COMMENT '创建时间',
  `params` text DEFAULT NULL COMMENT '参数默认值 JSON',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='通知模板主表' AUTO_INCREMENT=19;

-- ---------------------------------------------------------------------------
-- [P3] mks_notice_sys — 站内信群发记录（按等级/区域/角色筛选接收人）
-- ---------------------------------------------------------------------------
CREATE TABLE `mks_notice_sys` (
  `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT '站内信ID',
  `send_uid` int NOT NULL DEFAULT 0 COMMENT '发送人管理员ID',
  `type` int NOT NULL DEFAULT 1 COMMENT '接收人类型：1=用户 2=等级 3=地区 4=权限',
  `receive_uid` varchar(1000) NOT NULL DEFAULT '' COMMENT '指定接收会员ID串（varchar1000）',
  `select_level` int NOT NULL DEFAULT 0 COMMENT '按等级筛选的等级ID',
  `select_area` varchar(20) NOT NULL DEFAULT '' COMMENT '按区域筛选的区域码',
  `select_role` int NOT NULL DEFAULT 0 COMMENT '按角色筛选',
  `create_time` int NOT NULL DEFAULT 0 COMMENT '发送时间',
  `title` varchar(255) NOT NULL DEFAULT '' COMMENT '标题',
  `content` text DEFAULT NULL COMMENT '正文',
  `mes_is_show` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否弹窗显示',
  `mark` varchar(255) NOT NULL DEFAULT '' COMMENT '标识',
  `notice_type` tinyint(1) NOT NULL DEFAULT 0 COMMENT '通知类型',
  `sys_type` tinyint(1) NOT NULL DEFAULT 0 COMMENT '系统类型（1=用户 2=后台）',
  `url` varchar(255) NOT NULL DEFAULT '' COMMENT '跳转地址',
  `see_status` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否已读（汇总）',
  `position` varchar(255) NOT NULL DEFAULT 'bottom-right' COMMENT '弹窗位置（默认 bottom-right）',
  PRIMARY KEY (`id`),
  KEY `idx_core` (`sys_type`,`type`,`id`),
  KEY `idx_type_1` (`type`,`receive_uid`(200),`id`),
  KEY `idx_type_2` (`type`,`select_level`,`id`),
  KEY `idx_type_3` (`type`,`select_area`,`id`),
  KEY `idx_type_4` (`type`,`select_role`,`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='站内信群发记录（按等级/区域/角色筛选接收人）';

-- ---------------------------------------------------------------------------
-- [P3] mks_notice_sys_see — 站内信已读状态
-- ---------------------------------------------------------------------------
CREATE TABLE `mks_notice_sys_see` (
  `see_id` int unsigned NOT NULL AUTO_INCREMENT COMMENT '主键ID（站内信已读记录）',
  `sms_id` int DEFAULT NULL COMMENT '站内信ID → notice_sys.id',
  `sms_uid` int DEFAULT NULL COMMENT '接收会员ID → member.id',
  `sms_see_time` int DEFAULT NULL COMMENT '阅读时间',
  `sms_see_ip` varchar(255) DEFAULT NULL COMMENT '阅读IP',
  `sms_see_status` tinyint DEFAULT 1 COMMENT '阅读状态（1=已读）',
  PRIMARY KEY (`see_id`),
  KEY `idx_user_msg` (`sms_uid`,`sms_id`,`sms_see_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='站内信已读状态';

-- ---------------------------------------------------------------------------
-- [P3] mks_notice_user — 通知接收人配置（某事件由哪些管理员接收、走哪些渠道）
-- ---------------------------------------------------------------------------
CREATE TABLE `mks_notice_user` (
  `id` int NOT NULL AUTO_INCREMENT COMMENT '主键ID（自增）',
  `notice_id` int NOT NULL DEFAULT 0 COMMENT '通知模板ID → notice.id',
  `admin_id` int NOT NULL DEFAULT 0 COMMENT '管理员ID → admin.id',
  `uid` int NOT NULL DEFAULT 0 COMMENT '会员ID → member.id',
  `status` tinyint NOT NULL DEFAULT 1 COMMENT '状态机标识（1=正常/启用，0=关闭；各表具体码值含义见 §8.2）',
  `mark` varchar(255) NOT NULL DEFAULT '' COMMENT '标识/备注（短文本，代码中作为事件键或备注）',
  `type` tinyint(1) NOT NULL DEFAULT 1 COMMENT '类型标识（区分同表多种用途，码值见模块说明）',
  `create_time` int NOT NULL DEFAULT 0 COMMENT '创建时间（int 时间戳）',
  `update_time` int NOT NULL DEFAULT 0 COMMENT '更新时间（int 时间戳）',
  `email` varchar(255) NOT NULL DEFAULT '' COMMENT '邮箱',
  `mobile` varchar(255) NOT NULL DEFAULT '' COMMENT '手机号',
  `is_system` tinyint(1) NOT NULL DEFAULT 1 COMMENT '是否发站内信',
  `is_wechat` tinyint(1) NOT NULL DEFAULT 1 COMMENT '是否发公众号模板消息',
  `is_app` tinyint(1) NOT NULL DEFAULT 1 COMMENT '是否 APP 推送',
  `is_email` tinyint(1) NOT NULL DEFAULT 1 COMMENT '是否发邮件',
  `vip_id` int NOT NULL DEFAULT 0 COMMENT '分站ID（0=主站）',
  `mkslogin_id` varchar(100) NOT NULL DEFAULT '' COMMENT '第三方登录标识（mks_login）',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='通知接收人配置（某事件由哪些管理员接收、走哪些渠道）' AUTO_INCREMENT=5;

-- ---------------------------------------------------------------------------
-- [P3] mks_notice_goods — 商品到货/上架提醒订阅
-- ---------------------------------------------------------------------------
CREATE TABLE `mks_notice_goods` (
  `id` int NOT NULL AUTO_INCREMENT COMMENT '主键ID（自增）',
  `goods_id` int NOT NULL DEFAULT 0 COMMENT '商品ID → goods.id',
  `uid` int NOT NULL DEFAULT 0 COMMENT '会员ID → member.id',
  `status` tinyint NOT NULL DEFAULT 1 COMMENT '状态机标识（1=正常/启用，0=关闭；各表具体码值含义见 §8.2）',
  `create_time` int NOT NULL DEFAULT 0 COMMENT '创建时间（int 时间戳）',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='商品到货/上架提醒订阅';

-- ---------------------------------------------------------------------------
-- [P3] mks_notice_wechat — 公众号/小程序模板消息配置（模板ID、字段映射）
-- ---------------------------------------------------------------------------
CREATE TABLE `mks_notice_wechat` (
  `id` int NOT NULL AUTO_INCREMENT COMMENT '主键ID（自增）',
  `vip_id` int NOT NULL DEFAULT 0 COMMENT '分站ID（0=主站）',
  `temp_content` varchar(1000) NOT NULL DEFAULT '' COMMENT '模板内容示例',
  `mark` varchar(50) NOT NULL DEFAULT '' COMMENT '标识/备注（短文本，代码中作为事件键或备注）',
  `name` varchar(50) NOT NULL DEFAULT '' COMMENT '名称',
  `title` varchar(100) NOT NULL DEFAULT '' COMMENT '标题',
  `routine_id` varchar(50) NOT NULL DEFAULT '0' COMMENT '小程序模板ID',
  `type` tinyint(1) NOT NULL DEFAULT 1 COMMENT '类型标识（区分同表多种用途，码值见模块说明）',
  `add_time` int NOT NULL DEFAULT 0 COMMENT '添加时间',
  `tempkey` char(50) NOT NULL DEFAULT '' COMMENT '模板键',
  `temp_type` tinyint(1) NOT NULL DEFAULT 0 COMMENT '模板类型 0=公众号 1=小程序（推断）',
  `temp_kid` varchar(255) NOT NULL DEFAULT '' COMMENT '模板类目键',
  `temp_tempid` char(100) NOT NULL DEFAULT '' COMMENT '第三方模板ID',
  `temp_name` varchar(255) NOT NULL DEFAULT '' COMMENT '模板名称',
  PRIMARY KEY (`id`),
  UNIQUE KEY `mark` (`mark`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='公众号/小程序模板消息配置（模板ID、字段映射）' AUTO_INCREMENT=23;

-- ---------------------------------------------------------------------------
-- [P3] mks_email — SMTP 邮箱账号配置
-- ---------------------------------------------------------------------------
CREATE TABLE `mks_email` (
  `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '状态',
  `smtp_host` varchar(255) NOT NULL DEFAULT '' COMMENT '服务器地址',
  `smtp_port` int NOT NULL DEFAULT 587 COMMENT '邮箱端口',
  `smtp_name` varchar(255) NOT NULL DEFAULT '' COMMENT '用户名',
  `smtp_user` varchar(255) NOT NULL DEFAULT '' COMMENT '邮箱账号',
  `smtp_pass` varchar(255) NOT NULL DEFAULT '' COMMENT '授权码',
  `create_time` int NOT NULL DEFAULT 0 COMMENT '创建时间',
  `update_time` int NOT NULL DEFAULT 0 COMMENT '更新时间',
  `name` varchar(255) NOT NULL DEFAULT '' COMMENT '名称',
  `sort` int NOT NULL DEFAULT 0 COMMENT '排序',
  `error` varchar(2000) NOT NULL DEFAULT '' COMMENT '错误信息（最近一次发信错误）',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='邮箱表' AUTO_INCREMENT=2;

-- ---------------------------------------------------------------------------
-- [P3] mks_email_notice — 邮箱与通知事件的关联表
-- ---------------------------------------------------------------------------
CREATE TABLE `mks_email_notice` (
  `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `email_id` int NOT NULL DEFAULT 0 COMMENT '邮箱ID',
  `notice_id` int NOT NULL DEFAULT 0 COMMENT '消息ID',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='邮箱消息关联表';


-- ============================================================================
-- P4 装潢 DIY 与多站点（可视化装修 / 域名绑定）  （9 张表）
-- ============================================================================
-- ---------------------------------------------------------------------------
-- [P4] mks_diy_page — DIY 页面：页面级配置(主题色/背景/底部导航/组件 JSON 的父容器)
-- ---------------------------------------------------------------------------
CREATE TABLE `mks_diy_page` (
  `id` int NOT NULL AUTO_INCREMENT COMMENT '页面ID',
  `pid` int NOT NULL DEFAULT 0 COMMENT '页面类型ID（1=商城首页 2=分类 3=用户中心 4=商品详情 5=商品列表 10000=自定义 20000=PC首页 20001=PC分类，见系统内置页面）',
  `template_id` int NOT NULL DEFAULT 1 COMMENT '所属模板ID → diy_template.id（1=手机端 2=PC端）',
  `vip_id` int NOT NULL DEFAULT 0 COMMENT '分站ID',
  `uid` int NOT NULL DEFAULT 0 COMMENT '创建者',
  `type` tinyint(1) NOT NULL DEFAULT 0 COMMENT '端类型（0=H5/小程序 1=PC，推断）',
  `addon_name` varchar(255) NOT NULL DEFAULT 'mks' COMMENT '所属插件（默认 mks）',
  `template` varchar(255) NOT NULL DEFAULT 'diy' COMMENT '模板目录名（默认 diy）',
  `name` varchar(255) NOT NULL DEFAULT '' COMMENT '页面标识名',
  `title` varchar(100) NOT NULL DEFAULT '' COMMENT '页面标题',
  `desc` varchar(255) NOT NULL DEFAULT '' COMMENT '描述',
  `version` varchar(125) NOT NULL DEFAULT '' COMMENT '版本号（唯一索引，模板导入标识）',
  `img` varchar(255) NOT NULL DEFAULT '' COMMENT '缩略图',
  `long_img` varchar(255) NOT NULL DEFAULT '' COMMENT '长图预览',
  `is_theme_config` tinyint DEFAULT 0 COMMENT '是否启用主题配置',
  `theme_config` text DEFAULT NULL COMMENT '主题配色 JSON',
  `is_gzh_share` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否配置公众号分享',
  `gzh_share` text DEFAULT NULL COMMENT '分享配置 JSON',
  `is_to_top` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否显示回到顶部',
  `to_top_config` text DEFAULT NULL COMMENT '回到顶部配置',
  `extend_config` longtext DEFAULT NULL COMMENT '扩展配置 JSON',
  `value` longtext DEFAULT NULL COMMENT '页面级组件结构 JSON（组件实例的主存储是 diy_page_component）',
  `author` varchar(255) NOT NULL DEFAULT '' COMMENT '作者',
  `key` varchar(255) NOT NULL DEFAULT '' COMMENT '模板唯一键',
  `key_config` varchar(1000) DEFAULT NULL COMMENT '键配置',
  `status` tinyint(1) NOT NULL DEFAULT 0 COMMENT '状态 0=草稿 1=已发布（推断）',
  `is_sys` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否系统内置（内置页面不可删）',
  `is_show` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否前台展示',
  `delete_time` int NOT NULL DEFAULT 0 COMMENT '软删除',
  `is_bg_color` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否启用背景色',
  `is_bg_pic` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否启用背景图',
  `color_picker` varchar(50) NOT NULL DEFAULT '' COMMENT '背景色值',
  `bg_pic` varchar(256) NOT NULL DEFAULT '' COMMENT '背景图',
  `bg_tab_val` tinyint(1) NOT NULL DEFAULT 0 COMMENT '背景作用范围（整页/tab）',
  `order_status` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否显示订单模块',
  `my_banner_status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '是否显示我的横幅',
  `menu_status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '是否显示菜单',
  `service_status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '是否显示客服',
  `open_start_time` int NOT NULL DEFAULT 0 COMMENT '生效开始时间',
  `open_end_time` int NOT NULL DEFAULT 0 COMMENT '生效结束时间',
  `create_time` int NOT NULL DEFAULT 0 COMMENT '创建时间',
  `update_time` int NOT NULL DEFAULT 0 COMMENT '更新时间',
  `is_show_foot` tinyint(1) NOT NULL DEFAULT 1 COMMENT '是否显示底部',
  `hidden_foot` tinyint(1) NOT NULL DEFAULT 0 COMMENT '隐藏底部导航',
  `row_gutter` int NOT NULL DEFAULT 0 COMMENT '栅格间距',
  `row_overflow` tinyint(1) NOT NULL DEFAULT 0 COMMENT '横向溢出滚动',
  `navbar_title` varchar(255) DEFAULT NULL COMMENT '导航栏标题',
  `navbar_bg` text DEFAULT NULL COMMENT '导航栏背景',
  `system_button_style` varchar(255) DEFAULT NULL COMMENT '系统按钮样式',
  `is_immersive` tinyint DEFAULT NULL COMMENT '沉浸式导航',
  `scroll_bg_color` varchar(255) DEFAULT NULL COMMENT '滚动背景色',
  `left_nav_bar` text DEFAULT NULL COMMENT '左侧导航配置',
  `middel_nav_bar` text DEFAULT NULL COMMENT '中间导航配置',
  `right_navbar` text DEFAULT NULL COMMENT '右侧导航配置',
  PRIMARY KEY (`id`),
  UNIQUE KEY `version` (`version`),
  KEY `template_name` (`addon_name`,`type`),
  KEY `status_type` (`status`,`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='DIY 页面' AUTO_INCREMENT=10054;

-- ---------------------------------------------------------------------------
-- [P4] mks_diy_page_cate — DIY 页面分类（按页面类型 pid 分组）
-- ---------------------------------------------------------------------------
CREATE TABLE `mks_diy_page_cate` (
  `id` int NOT NULL AUTO_INCREMENT COMMENT '主键ID（自增）',
  `img` varchar(255) NOT NULL DEFAULT '' COMMENT '图片地址',
  `template_id` int NOT NULL DEFAULT 1 COMMENT '模板ID → diy_template.id',
  `page_url` varchar(255) NOT NULL DEFAULT '' COMMENT '页面预览地址',
  `vip_id` int NOT NULL DEFAULT 0 COMMENT '分站ID（0=主站）',
  `uid` int NOT NULL DEFAULT 0 COMMENT '会员ID → member.id',
  `type` tinyint(1) NOT NULL DEFAULT 1 COMMENT '类型标识（区分同表多种用途，码值见模块说明）',
  `page_type` tinyint(1) NOT NULL DEFAULT 1 COMMENT '页面类型',
  `sort` int NOT NULL DEFAULT 0 COMMENT '排序值（越大越前，或正序，按模块实现）',
  `pid` int NOT NULL DEFAULT 0 COMMENT '父级ID（树形结构父节点，0=顶级）',
  `status` tinyint(1) NOT NULL DEFAULT 0 COMMENT '状态机标识（1=正常/启用，0=关闭；各表具体码值含义见 §8.2）',
  `is_sys` int NOT NULL DEFAULT 0 COMMENT '是否系统内置（内置数据不可删）',
  `name` varchar(50) NOT NULL DEFAULT '' COMMENT '名称',
  `delete_time` int NOT NULL DEFAULT 0 COMMENT '软删除时间（0=未删除）',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='DIY 页面分类（按页面类型 pid 分组）' AUTO_INCREMENT=20002;

-- ---------------------------------------------------------------------------
-- [P4] mks_diy_page_component — DIY 组件实例：页面内每个组件的位置与配置值(value JSON)
-- ---------------------------------------------------------------------------
CREATE TABLE `mks_diy_page_component` (
  `id` int NOT NULL AUTO_INCREMENT COMMENT '组件实例ID',
  `pid` int NOT NULL DEFAULT 0 COMMENT '父组件ID（组件嵌套，0=顶层）',
  `template_id` int NOT NULL DEFAULT 1 COMMENT '模板ID（与页面一致）',
  `addon_name` varchar(255) NOT NULL DEFAULT 'mks' COMMENT '插件名',
  `page_id` int NOT NULL DEFAULT 0 COMMENT '所在页面ID → diy_page.id',
  `page_cate_id` int NOT NULL DEFAULT 0 COMMENT '页面类型ID（决定可用组件范围）',
  `is_common` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否通用组件（1=跨页面复用）',
  `vip_id` int NOT NULL DEFAULT 0 COMMENT '分站ID',
  `uid` int NOT NULL DEFAULT 0 COMMENT '创建者',
  `type` tinyint(1) NOT NULL DEFAULT 0 COMMENT '端类型',
  `only` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否唯一（同页只允许一个）',
  `conflict` varchar(255) NOT NULL DEFAULT '' COMMENT '互斥组件名（不能共存）',
  `name` varchar(125) NOT NULL DEFAULT '' COMMENT '组件名（Banner/Menus/GoodsList...，与 /views 组件目录对应）',
  `title` varchar(100) NOT NULL DEFAULT '' COMMENT '组件中文名',
  `desc` varchar(255) NOT NULL DEFAULT '' COMMENT '描述',
  `version` varchar(255) NOT NULL DEFAULT '' COMMENT '版本',
  `img` varchar(255) NOT NULL DEFAULT '' COMMENT '组件缩略图',
  `value` longtext DEFAULT NULL COMMENT '组件配置 JSON（该组件全部可配字段的值）',
  `author` varchar(255) NOT NULL DEFAULT '' COMMENT '作者',
  `sort` int NOT NULL DEFAULT 0 COMMENT '页面内排序',
  `status` tinyint(1) NOT NULL DEFAULT 0 COMMENT '状态',
  `is_sys` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否系统内置组件',
  `create_time` int NOT NULL DEFAULT 0 COMMENT '创建时间',
  `update_time` int NOT NULL DEFAULT 0 COMMENT '更新时间',
  `delete_time` int NOT NULL DEFAULT 0 COMMENT '软删除',
  PRIMARY KEY (`id`),
  UNIQUE KEY `diy_name` (`name`,`template_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='DIY 组件实例' AUTO_INCREMENT=1000090;

-- ---------------------------------------------------------------------------
-- [P4] mks_diy_page_component_cate — DIY 组件分类字典
-- ---------------------------------------------------------------------------
CREATE TABLE `mks_diy_page_component_cate` (
  `id` int NOT NULL AUTO_INCREMENT COMMENT '主键ID（自增）',
  `template_id` int NOT NULL DEFAULT 1 COMMENT '模板ID → diy_template.id',
  `img` varchar(255) NOT NULL DEFAULT '' COMMENT '图片地址',
  `uid` int NOT NULL DEFAULT 0 COMMENT '会员ID → member.id',
  `sort` int NOT NULL DEFAULT 0 COMMENT '排序值（越大越前，或正序，按模块实现）',
  `pid` int NOT NULL DEFAULT 0 COMMENT '父级ID（树形结构父节点，0=顶级）',
  `status` tinyint(1) NOT NULL DEFAULT 0 COMMENT '状态机标识（1=正常/启用，0=关闭；各表具体码值含义见 §8.2）',
  `is_sys` int NOT NULL DEFAULT 0 COMMENT '是否系统内置（内置数据不可删）',
  `name` varchar(50) NOT NULL DEFAULT '' COMMENT '名称',
  `delete_time` int NOT NULL DEFAULT 0 COMMENT '软删除时间（0=未删除）',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='DIY 组件分类字典' AUTO_INCREMENT=6;

-- ---------------------------------------------------------------------------
-- [P4] mks_diy_page_link — DIY 跳转链接库：页面/组件可选的站内跳转地址与参数
-- ---------------------------------------------------------------------------
CREATE TABLE `mks_diy_page_link` (
  `id` int NOT NULL AUTO_INCREMENT COMMENT '主键ID（自增）',
  `template_id` int NOT NULL DEFAULT 1 COMMENT '模板ID → diy_template.id',
  `vip_id` int NOT NULL DEFAULT 0 COMMENT '分站ID（0=主站）',
  `uid` int NOT NULL DEFAULT 0 COMMENT '会员ID → member.id',
  `pid` int NOT NULL DEFAULT 0 COMMENT '父级ID（树形结构父节点，0=顶级）',
  `type` tinyint(1) NOT NULL DEFAULT 1 COMMENT '类型标识（区分同表多种用途，码值见模块说明）',
  `link_type` varchar(50) NOT NULL DEFAULT '0' COMMENT '链接类型（站内页/外链/自定义）',
  `group_id` varchar(255) NOT NULL DEFAULT '0' COMMENT '分组ID',
  `name` varchar(50) NOT NULL DEFAULT '' COMMENT '名称',
  `url` varchar(255) NOT NULL DEFAULT '' COMMENT '链接地址',
  `param` varchar(255) NOT NULL DEFAULT '' COMMENT '参数',
  `example` varchar(255) NOT NULL DEFAULT '' COMMENT '示例地址',
  `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '状态机标识（1=正常/启用，0=关闭；各表具体码值含义见 §8.2）',
  `sort` int NOT NULL DEFAULT 0 COMMENT '排序值（越大越前，或正序，按模块实现）',
  `is_sys` tinyint NOT NULL DEFAULT 0 COMMENT '是否系统内置（内置数据不可删）',
  `create_time` int NOT NULL DEFAULT 0 COMMENT '创建时间（int 时间戳）',
  `update_time` int NOT NULL DEFAULT 0 COMMENT '更新时间（int 时间戳）',
  `delete_time` int NOT NULL DEFAULT 0 COMMENT '软删除时间（0=未删除）',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='DIY 跳转链接库' AUTO_INCREMENT=36;

-- ---------------------------------------------------------------------------
-- [P4] mks_diy_page_link_cate — 链接分类：定义每个链接组的表单/表格/搜索字段
-- ---------------------------------------------------------------------------
CREATE TABLE `mks_diy_page_link_cate` (
  `id` int NOT NULL AUTO_INCREMENT COMMENT '主键ID（自增）',
  `name` varchar(50) NOT NULL DEFAULT '' COMMENT '名称',
  `template_id` int NOT NULL DEFAULT 1 COMMENT '模板ID → diy_template.id',
  `vip_id` int NOT NULL DEFAULT 0 COMMENT '分站ID（0=主站）',
  `img` varchar(255) NOT NULL DEFAULT '' COMMENT '图片地址',
  `uid` int NOT NULL DEFAULT 0 COMMENT '会员ID → member.id',
  `type` tinyint(1) NOT NULL DEFAULT 1 COMMENT '类型标识（区分同表多种用途，码值见模块说明）',
  `page_link` varchar(255) NOT NULL DEFAULT '' COMMENT '页面链接标识',
  `req_url` varchar(255) NOT NULL DEFAULT '' COMMENT '数据请求接口地址',
  `req_params` text DEFAULT NULL COMMENT '请求参数定义（JSON）',
  `table_header` text DEFAULT NULL COMMENT '表格列定义（JSON）',
  `search_form` text DEFAULT NULL COMMENT '搜索表单定义（JSON）',
  `props` text DEFAULT NULL COMMENT '扩展属性（JSON）',
  `sort` int NOT NULL DEFAULT 0 COMMENT '排序值（越大越前，或正序，按模块实现）',
  `pid` int NOT NULL DEFAULT 0 COMMENT '父级ID（树形结构父节点，0=顶级）',
  `status` tinyint(1) NOT NULL DEFAULT 0 COMMENT '状态机标识（1=正常/启用，0=关闭；各表具体码值含义见 §8.2）',
  `is_sys` int NOT NULL DEFAULT 0 COMMENT '是否系统内置（内置数据不可删）',
  `delete_time` int NOT NULL DEFAULT 0 COMMENT '软删除时间（0=未删除）',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='链接分类' AUTO_INCREMENT=20007;

-- ---------------------------------------------------------------------------
-- [P4] mks_diy_page_link_group — 链接分组
-- ---------------------------------------------------------------------------
CREATE TABLE `mks_diy_page_link_group` (
  `id` int NOT NULL AUTO_INCREMENT COMMENT '主键ID（自增）',
  `link_cate_id` int NOT NULL DEFAULT 0 COMMENT '链接分类ID → diy_page_link_cate.id',
  `sort` int NOT NULL DEFAULT 0 COMMENT '排序值（越大越前，或正序，按模块实现）',
  `is_sys` int NOT NULL DEFAULT 0 COMMENT '是否系统内置（内置数据不可删）',
  `name` varchar(50) NOT NULL DEFAULT '' COMMENT '名称',
  `delete_time` int NOT NULL DEFAULT 0 COMMENT '软删除时间（0=未删除）',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='链接分组' AUTO_INCREMENT=3;

-- ---------------------------------------------------------------------------
-- [P4] mks_diy_template — DIY 模板套件：整套页面/组件的打包(config/content)
-- ---------------------------------------------------------------------------
CREATE TABLE `mks_diy_template` (
  `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT '主键ID（自增）',
  `vip_id` int NOT NULL DEFAULT 0 COMMENT '分站ID（0=主站）',
  `uid` int NOT NULL DEFAULT 0 COMMENT '会员ID → member.id',
  `imgs` varchar(2000) NOT NULL COMMENT '图片地址集合',
  `desc` varchar(500) NOT NULL COMMENT '描述',
  `content` longtext NOT NULL COMMENT '内容（HTML/JSON）',
  `name` varchar(255) NOT NULL DEFAULT '' COMMENT '名称',
  `addon_name` varchar(255) NOT NULL COMMENT '所属插件名（默认 mks）',
  `type` tinyint(1) NOT NULL DEFAULT 1 COMMENT '类型标识（区分同表多种用途，码值见模块说明）',
  `sort` int NOT NULL DEFAULT 0 COMMENT '排序值（越大越前，或正序，按模块实现）',
  `author` varchar(255) NOT NULL DEFAULT '官方' COMMENT '作者',
  `host` varchar(255) NOT NULL DEFAULT '' COMMENT '域名/主机',
  `form_config` longtext DEFAULT NULL COMMENT '表单配置 JSON',
  `config` text DEFAULT NULL COMMENT '配置（JSON）',
  `key` varchar(500) NOT NULL DEFAULT '' COMMENT '键/唯一标识',
  `is_html` varchar(255) NOT NULL DEFAULT '1' COMMENT '是否 HTML 模板（1=是）',
  `is_sys` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否系统内置（内置数据不可删）',
  `status` tinyint(1) NOT NULL DEFAULT 0 COMMENT '状态机标识（1=正常/启用，0=关闭；各表具体码值含义见 §8.2）',
  `create_time` int NOT NULL DEFAULT 0 COMMENT '创建时间（int 时间戳）',
  `update_time` int NOT NULL DEFAULT 0 COMMENT '更新时间（int 时间戳）',
  `expire_time` int NOT NULL DEFAULT 0 COMMENT '过期/失效时间',
  `delete_time` int NOT NULL DEFAULT 0 COMMENT '软删除时间（0=未删除）',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='DIY 模板套件' AUTO_INCREMENT=3;

-- ---------------------------------------------------------------------------
-- [P4] mks_sys_url — 站点域名绑定：域名→PC/H5 模板、首页/商品/分类模板、关闭站点提示、主站标记
-- ---------------------------------------------------------------------------
CREATE TABLE `mks_sys_url` (
  `id` int NOT NULL AUTO_INCREMENT COMMENT '站点域名记录ID',
  `vip_id` int NOT NULL DEFAULT 0 COMMENT '所属分站ID（0=主站）',
  `type` tinyint(1) NOT NULL DEFAULT 1 COMMENT '域名类型（主域名/绑定域名）',
  `url` varchar(255) NOT NULL DEFAULT '' COMMENT '域名（唯一索引）',
  `create_time` int NOT NULL DEFAULT 0 COMMENT '创建时间',
  `update_time` int NOT NULL DEFAULT 0 COMMENT '更新时间',
  `close_site_show` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否显示关闭提示',
  `close_site` varchar(255) NOT NULL DEFAULT '' COMMENT '是否关闭该站点',
  `close_site_type` tinyint(1) NOT NULL DEFAULT 1 COMMENT '关闭提示类型 1=提示 2=跳转（推断）',
  `close_site_tip` text DEFAULT NULL COMMENT '关闭提示 HTML',
  `close_site_url` varchar(255) NOT NULL DEFAULT '' COMMENT '关闭跳转地址',
  `is_main` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否主站',
  `addon_name` varchar(255) NOT NULL DEFAULT 'mks' COMMENT '使用的插件（默认 mks）',
  `template` varchar(255) NOT NULL DEFAULT 'default' COMMENT 'H5 模板目录（默认 default）',
  `template_pc` varchar(255) NOT NULL DEFAULT 'default' COMMENT 'PC 模板目录（默认 default）',
  `is_qq_hong` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否启用 QQ 红点/跳转',
  `is_wx_hong` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否启用微信红点/跳转',
  `qq_hong` text DEFAULT NULL COMMENT 'QQ 跳转配置',
  `wx_hong` text DEFAULT NULL COMMENT '微信跳转配置',
  `index_front` varchar(255) NOT NULL DEFAULT '' COMMENT 'PC 首页自定义模板',
  `goods_front` varchar(255) NOT NULL DEFAULT '' COMMENT 'PC 商品页模板',
  `category_front` varchar(255) NOT NULL DEFAULT '' COMMENT 'PC 分类页模板',
  `index_h5` varchar(255) NOT NULL DEFAULT '' COMMENT 'H5 首页模板',
  `goods_h5` varchar(255) NOT NULL DEFAULT '' COMMENT 'H5 商品页模板',
  `category_h5` varchar(255) NOT NULL DEFAULT '' COMMENT 'H5 分类页模板',
  `is_main_diy_h5` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'H5 是否启用 DIY 模板',
  `is_main_diy_pc` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'PC 是否启用 DIY 模板',
  PRIMARY KEY (`id`),
  UNIQUE KEY `url` (`url`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='站点域名绑定';


-- ============================================================================
-- P5 运维与周边（日志/素材/收藏/推荐/分站商品，可后置）  （16 张表）
-- ============================================================================
-- ---------------------------------------------------------------------------
-- [P5] mks_blacklist — IP 黑名单
-- ---------------------------------------------------------------------------
CREATE TABLE `mks_blacklist` (
  `id` int NOT NULL AUTO_INCREMENT COMMENT '主键ID（自增）',
  `ip` varchar(50) NOT NULL DEFAULT '' COMMENT 'IP 地址',
  `remark` varchar(100) NOT NULL DEFAULT '' COMMENT '备注',
  `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '状态机标识（1=正常/启用，0=关闭；各表具体码值含义见 §8.2）',
  `create_time` int NOT NULL DEFAULT 0 COMMENT '创建时间（int 时间戳）',
  `update_time` int NOT NULL DEFAULT 0 COMMENT '更新时间（int 时间戳）',
  PRIMARY KEY (`id`),
  UNIQUE KEY `ip` (`ip`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='IP 黑名单';

-- ---------------------------------------------------------------------------
-- [P5] mks_sensitive — 敏感词替换表
-- ---------------------------------------------------------------------------
CREATE TABLE `mks_sensitive` (
  `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT '主键ID（自增）',
  `name` varchar(255) NOT NULL COMMENT '名称',
  `value` varchar(255) NOT NULL DEFAULT '***' COMMENT '值（配置值/属性值）',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='敏感词替换表';

-- ---------------------------------------------------------------------------
-- [P5] mks_file — 上传文件/素材库（存储方式、md5、归属业务 relation_id）
-- ---------------------------------------------------------------------------
CREATE TABLE `mks_file` (
  `id` int NOT NULL AUTO_INCREMENT COMMENT '文件ID',
  `uid` int NOT NULL DEFAULT 0 COMMENT '上传者ID',
  `type` tinyint(1) NOT NULL DEFAULT 1 COMMENT '归属业务类型（1=系统 2=商品 3=文章…）',
  `file_type` tinyint(1) NOT NULL DEFAULT 1 COMMENT '文件类型（1=图片 2=视频 3=文件）',
  `relation_id` int NOT NULL DEFAULT 0 COMMENT '业务主键（多态关联）',
  `name` varchar(500) NOT NULL DEFAULT '' COMMENT '文件名',
  `att_dir` varchar(500) NOT NULL DEFAULT '' COMMENT '原图路径',
  `satt_dir` varchar(500) NOT NULL DEFAULT '' COMMENT '缩略图路径',
  `att_size` char(30) NOT NULL DEFAULT '' COMMENT '文件大小',
  `att_type` char(30) NOT NULL DEFAULT '' COMMENT 'MIME 类型',
  `pid` int NOT NULL DEFAULT 0 COMMENT '父文件ID（切片/关联）',
  `time` int NOT NULL DEFAULT 0 COMMENT '上传时间',
  `image_type` varchar(10) NOT NULL DEFAULT '' COMMENT '图片存储类型',
  `real_name` varchar(500) NOT NULL DEFAULT '' COMMENT '原始文件名',
  `md5` varchar(32) NOT NULL DEFAULT '' COMMENT '文件 md5（秒传/去重）',
  `is_del` tinyint(1) NOT NULL DEFAULT 0 COMMENT '删除标记',
  `scan_token` varchar(255) NOT NULL DEFAULT '' COMMENT '安全扫描 token',
  `create_time` int NOT NULL DEFAULT 0 COMMENT '创建时间',
  `update_time` int NOT NULL DEFAULT 0 COMMENT '更新时间',
  `delete_url` varchar(255) NOT NULL DEFAULT '' COMMENT '远程删除接口/地址',
  `is_addlink` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否为外链',
  PRIMARY KEY (`id`),
  KEY `type` (`type`,`relation_id`),
  KEY `file_type` (`file_type`),
  KEY `time` (`time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='上传文件/素材库（存储方式、md5、归属业务 relation_id）';

-- ---------------------------------------------------------------------------
-- [P5] mks_file_group — 素材分组
-- ---------------------------------------------------------------------------
CREATE TABLE `mks_file_group` (
  `id` int NOT NULL AUTO_INCREMENT COMMENT '主键ID（自增）',
  `uid` int NOT NULL DEFAULT 0 COMMENT '会员ID → member.id',
  `type` tinyint(1) NOT NULL DEFAULT 1 COMMENT '类型标识（区分同表多种用途，码值见模块说明）',
  `file_type` tinyint(1) NOT NULL DEFAULT 1 COMMENT '文件类型（1=图片 2=视频 3=文件）',
  `relation_id` int NOT NULL DEFAULT 0 COMMENT '关联业务ID（多态：relation_id + type）',
  `pid` int NOT NULL DEFAULT 0 COMMENT '父级ID（树形结构父节点，0=顶级）',
  `name` varchar(50) NOT NULL DEFAULT '' COMMENT '名称',
  `enname` varchar(50) NOT NULL DEFAULT '' COMMENT '英文名/目录名',
  `sort` int NOT NULL DEFAULT 0 COMMENT '排序值（越大越前，或正序，按模块实现）',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='素材分组' AUTO_INCREMENT=100;

-- ---------------------------------------------------------------------------
-- [P5] mks_sys_timer — 定时任务定义（mark/cycle/url/上次执行时间）
-- ---------------------------------------------------------------------------
CREATE TABLE `mks_sys_timer` (
  `id` int NOT NULL AUTO_INCREMENT COMMENT '主键ID（自增）',
  `name` varchar(50) NOT NULL DEFAULT '' COMMENT '名称',
  `mark` varchar(50) DEFAULT NULL COMMENT '标识/备注（短文本，代码中作为事件键或备注）',
  `type` tinyint(1) NOT NULL DEFAULT 0 COMMENT '类型标识（区分同表多种用途，码值见模块说明）',
  `title` varchar(255) NOT NULL DEFAULT '' COMMENT '标题',
  `status` tinyint(1) NOT NULL DEFAULT 0 COMMENT '状态机标识（1=正常/启用，0=关闭；各表具体码值含义见 §8.2）',
  `cycle` varchar(255) DEFAULT NULL COMMENT '执行周期（如 30/0、1/30/30，格式由任务类型决定）',
  `url` varchar(255) NOT NULL DEFAULT '' COMMENT '链接地址',
  `last_execution_time` int NOT NULL DEFAULT 0 COMMENT '上次执行时间',
  `update_execution_time` int DEFAULT 0 COMMENT '本次执行时间',
  `create_time` int NOT NULL DEFAULT 0 COMMENT '创建时间（int 时间戳）',
  `delete_time` int NOT NULL DEFAULT 0 COMMENT '软删除时间（0=未删除）',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='定时任务定义（mark/cycle/url/上次执行时间）' AUTO_INCREMENT=1000;

-- ---------------------------------------------------------------------------
-- [P5] mks_export_table — 异步导出任务（导出类型、字段、进度、文件地址）
-- ---------------------------------------------------------------------------
CREATE TABLE `mks_export_table` (
  `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT 'ID',
  `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '导出状态：1=待导出 2=导出中 3=已完成',
  `dual_count` int NOT NULL DEFAULT 0 COMMENT '当前处理数量',
  `page_count` int NOT NULL DEFAULT 0 COMMENT '导出页数',
  `export_count` int NOT NULL DEFAULT 0 COMMENT '导出数量',
  `file_name` varchar(255) NOT NULL DEFAULT '' COMMENT '文件名称',
  `file_url` varchar(255) NOT NULL DEFAULT '' COMMENT '文件路径',
  `create_time` int NOT NULL DEFAULT 0 COMMENT '创建时间',
  `dual_time` int NOT NULL DEFAULT 0 COMMENT '导出时间',
  `success_time` int NOT NULL DEFAULT 0 COMMENT '完成时间',
  `delete_time` int NOT NULL DEFAULT 0 COMMENT '删除时间',
  `type` varchar(255) NOT NULL DEFAULT '' COMMENT '导出类型:order=订单表',
  `export_field` text DEFAULT NULL COMMENT '导出字段',
  `search_data` text DEFAULT NULL COMMENT '查询条件',
  `admin_id` int NOT NULL DEFAULT 0 COMMENT '后台用户ID',
  `user_id` int NOT NULL DEFAULT 0 COMMENT '前台用户ID',
  `uid_type` tinyint NOT NULL DEFAULT 1 COMMENT '导出人类型:1=后台,2=前台',
  `mark` varchar(255) NOT NULL DEFAULT '' COMMENT '备注',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='导出数据';

-- ---------------------------------------------------------------------------
-- [P5] mks_request_log — 接口请求日志（后台/前台、URL、参数、UA、IP）
-- ---------------------------------------------------------------------------
CREATE TABLE `mks_request_log` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT '主键ID（自增）',
  `admin_id` int NOT NULL DEFAULT 0 COMMENT '管理员ID → admin.id',
  `user_id` int NOT NULL DEFAULT 0 COMMENT '用户ID（前台会员）',
  `method` varchar(50) NOT NULL DEFAULT '' COMMENT 'HTTP 方法',
  `module` varchar(255) NOT NULL DEFAULT '' COMMENT '模块名',
  `title` varchar(100) NOT NULL DEFAULT '' COMMENT '标题',
  `url` varchar(255) NOT NULL DEFAULT '' COMMENT '链接地址',
  `route` varchar(255) NOT NULL DEFAULT '' COMMENT '路由地址',
  `post_data` longtext NOT NULL COMMENT 'POST 参数（原文）',
  `get_data` longtext NOT NULL COMMENT 'GET 参数（原文）',
  `ua` text NOT NULL COMMENT 'User-Agent',
  `ip` varchar(64) NOT NULL DEFAULT '' COMMENT 'IP 地址',
  `ip_add` varchar(255) NOT NULL DEFAULT '' COMMENT 'IP 归属地',
  `create_time` int NOT NULL DEFAULT 0 COMMENT '创建时间（int 时间戳）',
  `update_time` int NOT NULL DEFAULT 0 COMMENT '更新时间（int 时间戳）',
  PRIMARY KEY (`id`),
  KEY `admin_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='接口请求日志（后台/前台、URL、参数、UA、IP）';

-- ---------------------------------------------------------------------------
-- [P5] mks_goods_log — 商品操作日志（改价/上下架/审核等）
-- ---------------------------------------------------------------------------
CREATE TABLE `mks_goods_log` (
  `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT '主键ID（自增）',
  `goods_id` bigint NOT NULL COMMENT '商品ID → goods.id',
  `sys_type` int NOT NULL COMMENT '系统类型（1=用户侧 2=后台侧）',
  `op` varchar(50) NOT NULL COMMENT '操作标识',
  `create_time` int NOT NULL DEFAULT 0 COMMENT '创建时间（int 时间戳）',
  `ip` varchar(255) NOT NULL COMMENT 'IP 地址',
  `city` varchar(255) NOT NULL COMMENT '城市名称',
  `log_type` int NOT NULL COMMENT '日志类型（码值见模块说明）',
  `content` text NOT NULL COMMENT '内容（HTML/JSON）',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='商品操作日志（改价/上下架/审核等）';

-- ---------------------------------------------------------------------------
-- [P5] mks_goods_see_log — 商品浏览足迹/访问日志（按 uid+goods_id 计数）
-- ---------------------------------------------------------------------------
CREATE TABLE `mks_goods_see_log` (
  `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT '记录ID',
  `goods_id` bigint NOT NULL COMMENT '商品ID',
  `uid` varchar(50) NOT NULL COMMENT '会员ID（varchar50，兼容游客标识）',
  `count` int NOT NULL DEFAULT 1 COMMENT '浏览次数',
  `create_time` int NOT NULL DEFAULT 0 COMMENT '首次浏览时间',
  `update_time` int NOT NULL DEFAULT 0 COMMENT '最近浏览时间',
  `ip` varchar(255) NOT NULL COMMENT 'IP',
  `ip_add` varchar(255) NOT NULL COMMENT 'IP 归属地',
  `mark` text NOT NULL COMMENT '备注',
  `vip_id` int NOT NULL DEFAULT 0 COMMENT '分站ID',
  `merchant_uid` int NOT NULL DEFAULT 0 COMMENT '商户/供货商ID',
  PRIMARY KEY (`id`),
  KEY `see` (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='商品浏览足迹/访问日志（按 uid+goods_id 计数）';

-- ---------------------------------------------------------------------------
-- [P5] mks_goods_favorite — 会员商品收藏（uid+goods_id 关系表）
-- ---------------------------------------------------------------------------
CREATE TABLE `mks_goods_favorite` (
  `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT '主键ID（自增）',
  `uid` int NOT NULL COMMENT '会员ID → member.id',
  `goods_id` bigint unsigned NOT NULL COMMENT '商品ID → goods.id',
  `create_time` int NOT NULL DEFAULT 0 COMMENT '创建时间（int 时间戳）',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='会员商品收藏（uid+goods_id 关系表）';

-- ---------------------------------------------------------------------------
-- [P5] mks_goods_price_log — 商品调价流水：记录每次改价的旧价/新价/差价
-- ---------------------------------------------------------------------------
CREATE TABLE `mks_goods_price_log` (
  `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT '主键ID（自增）',
  `goods_id` int NOT NULL COMMENT '商品ID → goods.id',
  `level_id` int NOT NULL COMMENT '会员等级ID → member_level.id',
  `price` decimal(16,4) NOT NULL DEFAULT 0 COMMENT '价格/金额',
  `price1` decimal(16,4) NOT NULL DEFAULT 0 COMMENT '第二价格（划线价/原价）',
  `pricecha` decimal(16,4) NOT NULL DEFAULT 0 COMMENT '价差（本次调价差额）',
  `price_type` int NOT NULL DEFAULT 1 COMMENT '价格类型（1=等级价 等，码值见模块说明）',
  `create_time` int NOT NULL DEFAULT 0 COMMENT '创建时间（int 时间戳）',
  `goods_sku_id` int NOT NULL DEFAULT 0 COMMENT '商品规格/库存行ID → goods_stock.id',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='商品调价流水';

-- ---------------------------------------------------------------------------
-- [P5] mks_goods_recommend_category — 推荐目录（首页/分类页运营位分组，含配色与角标）
-- ---------------------------------------------------------------------------
CREATE TABLE `mks_goods_recommend_category` (
  `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT '主键ID（自增）',
  `vip_id` int NOT NULL DEFAULT 0 COMMENT '分站ID（0=主站）',
  `name` varchar(100) NOT NULL DEFAULT '' COMMENT '名称',
  `short_name` varchar(100) NOT NULL DEFAULT '' COMMENT '简称/短标题',
  `color` varchar(10) NOT NULL DEFAULT '' COMMENT '颜色值',
  `create_time` int NOT NULL DEFAULT 0 COMMENT '创建时间（int 时间戳）',
  `update_time` int NOT NULL DEFAULT 0 COMMENT '更新时间（int 时间戳）',
  `sort` int NOT NULL DEFAULT 0 COMMENT '排序值（越大越前，或正序，按模块实现）',
  `img` varchar(500) NOT NULL DEFAULT '' COMMENT '图片地址',
  `img2` varchar(500) NOT NULL DEFAULT '' COMMENT '第二张图片（选中态/移动端图）',
  `cate_ids` varchar(255) NOT NULL DEFAULT '' COMMENT '包含的分类ID集合',
  `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '状态机标识（1=正常/启用，0=关闭；各表具体码值含义见 §8.2）',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='推荐目录（首页/分类页运营位分组，含配色与角标）';

-- ---------------------------------------------------------------------------
-- [P5] mks_goods_recommend_goods — 推荐目录与商品的关联表（含排序）
-- ---------------------------------------------------------------------------
CREATE TABLE `mks_goods_recommend_goods` (
  `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT '主键ID（自增）',
  `recommend_category_id` int NOT NULL DEFAULT 0 COMMENT '推荐目录ID → goods_recommend_category.id',
  `goods_id` int NOT NULL DEFAULT 0 COMMENT '商品ID → goods.id',
  `sort` int NOT NULL DEFAULT 0 COMMENT '排序值（越大越前，或正序，按模块实现）',
  `vip_id` int NOT NULL DEFAULT 0 COMMENT '分站ID（0=主站）',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='推荐目录与商品的关联表（含排序）';

-- ---------------------------------------------------------------------------
-- [P5] mks_merchant_favorite — 会员关注商户（商户关注列表）
-- ---------------------------------------------------------------------------
CREATE TABLE `mks_merchant_favorite` (
  `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT '主键ID（自增）',
  `uid` int NOT NULL COMMENT '会员ID → member.id',
  `merchant_id` bigint unsigned NOT NULL COMMENT '商户ID（member.has_merchant=1 的会员）',
  `create_time` int NOT NULL COMMENT '创建时间（int 时间戳）',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='会员关注商户（商户关注列表）';

-- ---------------------------------------------------------------------------
-- [P5] mks_vip_goods — 分站商品编辑表：分站对主站商品做名称/简介/图片的覆盖
-- ---------------------------------------------------------------------------
CREATE TABLE `mks_vip_goods` (
  `goods_id` int NOT NULL DEFAULT 0 COMMENT '商品ID → goods.id',
  `goods_name` varchar(1000) DEFAULT NULL COMMENT '商品名称（快照或冗余）',
  `goods_info` text DEFAULT NULL COMMENT '商品详情（富文本）',
  `goods_imgs` varchar(2000) NOT NULL DEFAULT '' COMMENT '商品图片集合（多图）',
  `vip_id` int NOT NULL DEFAULT 0 COMMENT '分站ID（0=主站）',
  `create_time` int NOT NULL DEFAULT 0 COMMENT '创建时间',
  `update_time` int NOT NULL DEFAULT 0 COMMENT '更新时间',
  `goods_short_name` varchar(255) NOT NULL DEFAULT '' COMMENT '商品简称',
  PRIMARY KEY (`goods_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='分站商品编辑表';

-- ---------------------------------------------------------------------------
-- [P5] mks_member_abnormal — 会员风控/异常记录（异常类型、IP、处理状态）
-- ---------------------------------------------------------------------------
CREATE TABLE `mks_member_abnormal` (
  `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT '主键ID（自增）',
  `uid` int NOT NULL DEFAULT 0 COMMENT '会员ID → member.id',
  `type` tinyint NOT NULL DEFAULT 1 COMMENT '类型标识（区分同表多种用途，码值见模块说明）',
  `ip` varchar(100) NOT NULL DEFAULT '' COMMENT 'IP 地址',
  `ip_add` varchar(255) NOT NULL DEFAULT '' COMMENT 'IP 归属地',
  `ip_all` varchar(255) NOT NULL DEFAULT '' COMMENT 'IP 归属地（完整）',
  `content` varchar(2000) NOT NULL DEFAULT '' COMMENT '内容（HTML/JSON）',
  `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '状态机标识（1=正常/启用，0=关闭；各表具体码值含义见 §8.2）',
  `mark` varchar(200) NOT NULL DEFAULT '' COMMENT '标识/备注（短文本，代码中作为事件键或备注）',
  `create_time` int NOT NULL DEFAULT 0 COMMENT '创建时间（int 时间戳）',
  `nickname` varchar(50) NOT NULL DEFAULT '' COMMENT '昵称',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='会员风控/异常记录（异常类型、IP、处理状态）';


-- ============================================================================
-- P6 插件补建表（自研替代方案；官方为付费插件，字段来自 _deobf 源码实际读写）
--    依据：01b §6 与 §5，详见附录 H-缺失表与插件清单.csv
-- ============================================================================
-- ---------------------------------------------------------------------------
-- [P6] mks_goods_freight — 插件表（字段来自源码，类型为推断）
-- ---------------------------------------------------------------------------
CREATE TABLE `mks_goods_freight` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键',
  `name` varchar(100) NOT NULL DEFAULT '' COMMENT '模板名称（GoodsFreightLogic:32）',
  `type` tinyint(1) NOT NULL DEFAULT 1 COMMENT '计费方式：1=按件 2=按重量 3=按体积（推断，见 save(type)）',
  `appoint` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否启用“指定地区包邮”（GoodsFreightLogic:32,53）',
  `no_delivery` tinyint(1) NOT NULL DEFAULT 0 COMMENT '是否启用“指定地区不送达”（:32,58）',
  `sort` int(10) NOT NULL DEFAULT 0 COMMENT '排序（:32）',
  `create_time` int(11) NOT NULL DEFAULT 0 COMMENT '创建时间（推断）',
  `update_time` int(11) NOT NULL DEFAULT 0 COMMENT '更新时间（推断）',
  `delete_time` int(10) NOT NULL DEFAULT 0 COMMENT '软删除（推断，BaseModel 惯例）',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='goods_freight';

-- ---------------------------------------------------------------------------
-- [P6] mks_goods_freight_region — 插件表（字段来自源码，类型为推断）
-- ---------------------------------------------------------------------------
CREATE TABLE `mks_goods_freight_region` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键',
  `temp_id` int(11) NOT NULL DEFAULT 0 COMMENT '运费模板ID → goods_freight.id（GoodsFreightRegionLogic:60）',
  `city_id` int(11) NOT NULL DEFAULT 0 COMMENT '城市ID（city_area.id，:60）',
  `value` text NULL COMMENT '该城市原始选择值 JSON（多级选择，:60）',
  `first` int(11) NOT NULL DEFAULT 0 COMMENT '首件/首重（:60）',
  `first_price` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT '首件运费（:60）',
  `continue` int(11) NOT NULL DEFAULT 0 COMMENT '续件/续重（:60）',
  `continue_price` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT '续件运费（:60）',
  `type` tinyint(1) NOT NULL DEFAULT 1 COMMENT '计费方式（随模板 type，:60）',
  `uniqid` varchar(64) NOT NULL DEFAULT '' COMMENT '同一行分组标识（:60，编辑时按 uniqid 归并）',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='goods_freight_region';

-- ---------------------------------------------------------------------------
-- [P6] mks_goods_freight_free — 插件表（字段来自源码，类型为推断）
-- ---------------------------------------------------------------------------
CREATE TABLE `mks_goods_freight_free` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键',
  `temp_id` int(11) NOT NULL DEFAULT 0 COMMENT '运费模板ID（GoodsFreightFreeLogic:60）',
  `city_id` int(11) NOT NULL DEFAULT 0 COMMENT '包邮城市ID（:60）',
  `value` text NULL COMMENT '原始选择值 JSON（:60）',
  `number` int(11) NOT NULL DEFAULT 0 COMMENT '满件数包邮（:60）',
  `price` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT '满金额包邮（:60）',
  `type` tinyint(1) NOT NULL DEFAULT 1 COMMENT '计费方式（:60）',
  `uniqid` varchar(64) NOT NULL DEFAULT '' COMMENT '分组标识（:60）',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='goods_freight_free';

-- ---------------------------------------------------------------------------
-- [P6] mks_goods_freight_no_delivery — 插件表（字段来自源码，类型为推断）
-- ---------------------------------------------------------------------------
CREATE TABLE `mks_goods_freight_no_delivery` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键',
  `temp_id` int(11) NOT NULL DEFAULT 0 COMMENT '运费模板ID（GoodsFreightNoDeliveryLogic:58）',
  `city_id` int(11) NOT NULL DEFAULT 0 COMMENT '不送达城市ID（:58）',
  `value` text NULL COMMENT '原始选择值 JSON（:58）',
  `uniqid` varchar(64) NOT NULL DEFAULT '' COMMENT '分组标识（:58）',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='goods_freight_no_delivery';

-- ---------------------------------------------------------------------------
-- [P6] mks_goods_spec — 插件表（字段来自源码，类型为推断）
-- ---------------------------------------------------------------------------
CREATE TABLE `mks_goods_spec` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键',
  `goods_id` int(11) NOT NULL DEFAULT 0 COMMENT '商品ID → goods.id',
  `uid` int(11) NOT NULL DEFAULT 0 COMMENT '供货商ID',
  `type` tinyint(1) NOT NULL DEFAULT 1 COMMENT '规格维度序号（多级规格时的层级，货品规格组）',
  `name` varchar(100) NOT NULL DEFAULT '' COMMENT '规格名（如“面值”“地区”）',
  `rule_value` text NULL COMMENT '规格取值列表 JSON（GoodsSpecLogic:189 读取 rule_value）',
  `sort` int(10) NOT NULL DEFAULT 0 COMMENT '排序',
  `create_time` int(11) NOT NULL DEFAULT 0 COMMENT '创建时间',
  `update_time` int(11) NOT NULL DEFAULT 0 COMMENT '更新时间',
  `delete_time` int(10) NOT NULL DEFAULT 0 COMMENT '软删除',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='goods_spec';

-- ---------------------------------------------------------------------------
-- [P6] mks_goods_spec_attr — 插件表（字段来自源码，类型为推断）
-- ---------------------------------------------------------------------------
CREATE TABLE `mks_goods_spec_attr` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键',
  `goods_id` int(11) NOT NULL DEFAULT 0 COMMENT '商品ID（GoodsSpecAttrLogic:30 按 goods_id+type 删）',
  `type` tinyint(1) NOT NULL DEFAULT 1 COMMENT '规格层级/类型（:30）',
  `name` varchar(100) NOT NULL DEFAULT '' COMMENT '属性名（推断，来自 post attrs）',
  `value` text NULL COMMENT '属性值 JSON（推断）',
  `sort` int(10) NOT NULL DEFAULT 0 COMMENT '排序（推断）',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='goods_spec_attr';

-- ---------------------------------------------------------------------------
-- [P6] mks_goods_spec_result — 插件表（字段来自源码，类型为推断）
-- ---------------------------------------------------------------------------
CREATE TABLE `mks_goods_spec_result` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键',
  `goods_id` int(11) NOT NULL DEFAULT 0 COMMENT '商品ID（GoodsSpecResultLogic::getResult 按 goods_id+type）',
  `type` tinyint(1) NOT NULL DEFAULT 1 COMMENT '规格层级/类型',
  `spec` text NULL COMMENT '规格结构 JSON（spec 树）',
  `attrs` text NULL COMMENT '属性组合 JSON（attrs 矩阵）',
  `create_time` int(11) NOT NULL DEFAULT 0 COMMENT '创建时间',
  `update_time` int(11) NOT NULL DEFAULT 0 COMMENT '更新时间',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='goods_spec_result';

-- ---------------------------------------------------------------------------
-- [P6] mks_goods_spec_value — 插件表（字段来自源码，类型为推断）
-- ---------------------------------------------------------------------------
CREATE TABLE `mks_goods_spec_value` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键（即 order.goods_sku_id 指向的 SKU ID）',
  `goods_id` bigint(20) NOT NULL DEFAULT 0 COMMENT '商品ID（DockingLogic:1385 等）',
  `uid` int(11) NOT NULL DEFAULT 0 COMMENT '供货商ID（GoodsSpecLogic:162）',
  `type` tinyint(1) NOT NULL DEFAULT 1 COMMENT '规格类型/层级（GoodsSpecLogic:162）',
  `suk` varchar(255) NOT NULL DEFAULT '' COMMENT '规格组合键（如 1_2_3，唯一标识一个 SKU，GoodsSpecLogic:120）',
  `unique` varchar(64) NOT NULL DEFAULT '' COMMENT '唯一串（createAttrUnique，:120）',
  `goods_img` varchar(500) NOT NULL DEFAULT '' COMMENT 'SKU 图片（:120）',
  `goods_type` tinyint(1) NOT NULL DEFAULT 1 COMMENT '商品类型（:120）',
  `face_value` decimal(14,4) NOT NULL DEFAULT 0.0000 COMMENT '面值（:120）',
  `cost_value` decimal(14,5) NOT NULL DEFAULT 0.00000 COMMENT '成本价（:120）',
  `stock_num` int(10) UNSIGNED NOT NULL DEFAULT 0 COMMENT '库存（:120, GoodsCartLogic:320）',
  `bar_code` varchar(64) NOT NULL DEFAULT '' COMMENT '条形码（:120）',
  `code` varchar(64) NOT NULL DEFAULT '' COMMENT 'SKU 编码（OrderTplLogic:803 按 code 反查）',
  `weight` decimal(10,3) NOT NULL DEFAULT 0.000 COMMENT '重量（:120，实体商品）',
  `volume` decimal(10,3) NOT NULL DEFAULT 0.000 COMMENT '体积（:120）',
  `goods_price_id` int(10) NOT NULL DEFAULT 0 COMMENT '定价模板ID（:120, GoodsPriceLogic:184）',
  `batch_goods_price_id` int(10) NOT NULL DEFAULT 0 COMMENT '批量定价模板ID（:123）',
  `order_tpl_id` int(10) NOT NULL DEFAULT 0 COMMENT '下单模板ID（:120）',
  `stock_auto_get` tinyint(1) NOT NULL DEFAULT 0 COMMENT '库存自动获取（:120, GoodsStockLogic:44）',
  `stock_set_price` tinyint(1) NOT NULL DEFAULT 0 COMMENT '库存影响价格（:120, GoodsStockLogic:44）',
  `stock_save_list` text NULL COMMENT '库存行初始配置 JSON（:120）',
  `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '状态（推断，1=启用）',
  `create_time` int(11) NOT NULL DEFAULT 0 COMMENT '创建时间',
  `update_time` int(11) NOT NULL DEFAULT 0 COMMENT '更新时间',
  `delete_time` int(10) NOT NULL DEFAULT 0 COMMENT '软删除',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='goods_spec_value';

-- ---------------------------------------------------------------------------
-- [P6] mks_goods_cart — 插件表（字段来自源码，类型为推断）
-- ---------------------------------------------------------------------------
CREATE TABLE `mks_goods_cart` (
  `id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '主键',
  `uid` int(11) NOT NULL DEFAULT 0 COMMENT '会员ID（GoodsCartLogic:25,57）',
  `mer_id` int(11) NOT NULL DEFAULT 0 COMMENT '商户/供货商ID（:33,57）',
  `goods_id` bigint(20) NOT NULL DEFAULT 0 COMMENT '商品ID（:33,57）',
  `goods_sku_id` int(11) NOT NULL DEFAULT 0 COMMENT 'SKU ID → goods_spec_value.id（:33,57）',
  `num` int(11) NOT NULL DEFAULT 1 COMMENT '数量（:33,57）',
  `type` tinyint(1) NOT NULL DEFAULT 0 COMMENT '购物车类型（:33）',
  `batch_tpl` text NULL COMMENT '批量下单模板（:33,98 有 getBatchTplAttr 访问器）',
  `attach` text NULL COMMENT '附加附件 JSON（:33,95 getAttachAttr 访问器）',
  `mark` varchar(255) NOT NULL DEFAULT '' COMMENT '备注（:33）',
  `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '状态（:33）',
  `create_time` int(11) NOT NULL DEFAULT 0 COMMENT '创建时间（推断）',
  `update_time` int(11) NOT NULL DEFAULT 0 COMMENT '更新时间（推断）',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='goods_cart';

SET FOREIGN_KEY_CHECKS = 1;

-- 建表完成。请继续执行 G-字典与种子数据.sql，再导入 provinces / city_area。