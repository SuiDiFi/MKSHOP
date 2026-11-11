-- ============================================================================
-- MKSHOP · M0 增量 DDL + 字典种子（重写新增，原系统无此三表）
-- 依据：_analysis/appendix/I-重写建模建议.md §5（修正版 RBAC）§7（sys_dict）
-- 可重复执行（幂等）。
-- 字典码值来源：config/kss.php 的 *_select 数组（明文权威来源）；
-- 其余枚举组在 M2 实现时按 01b-枚举与配置字典.md 补齐。
-- ============================================================================

-- 1. 字典表（19 组枚举的统一落点，前后端文案/标签色统一取此表）
CREATE TABLE IF NOT EXISTS `mks_sys_dict` (
  `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT '主键',
  `dict_type` varchar(50) NOT NULL DEFAULT '' COMMENT '字典类型，如 order_status/goods_type/card_status',
  `code` varchar(50) NOT NULL DEFAULT '' COMMENT '码值（统一存字符串，便于表达 -1/字符串码）',
  `label` varchar(100) NOT NULL DEFAULT '' COMMENT '中文名',
  `color` varchar(20) NOT NULL DEFAULT '' COMMENT '前端标签色',
  `sort` int NOT NULL DEFAULT 0 COMMENT '排序',
  `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '启用状态',
  `vip_id` int NOT NULL DEFAULT 0 COMMENT '分站ID（0=平台）',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_type_code_vip` (`dict_type`,`code`,`vip_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='系统字典表';

-- 2. 接口权限表（修正版 RBAC：菜单树留在 mks_admin_router，接口 ACL 独立成表）
--    修正点：默认拒绝（未登记即拒绝）+ (path, methods) 二元组校验
CREATE TABLE IF NOT EXISTS `mks_api_permission` (
  `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT '主键',
  `path` varchar(255) NOT NULL DEFAULT '' COMMENT '接口路径（不含组前缀）',
  `methods` varchar(50) NOT NULL DEFAULT '' COMMENT 'HTTP方法（逗号多值：GET,POST）',
  `title` varchar(100) NOT NULL DEFAULT '' COMMENT '权限点中文名',
  `group` varchar(50) NOT NULL DEFAULT '' COMMENT '所属端/模块分组',
  `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '启用状态',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_path_methods` (`path`,`methods`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='接口权限表';

CREATE TABLE IF NOT EXISTS `mks_role_api_permission` (
  `id` int unsigned NOT NULL AUTO_INCREMENT COMMENT '主键',
  `role_id` int NOT NULL COMMENT '角色ID → mks_admin_role.id',
  `api_id` int unsigned NOT NULL COMMENT '接口权限ID → mks_api_permission.id',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_role_api` (`role_id`,`api_id`),
  KEY `idx_api` (`api_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='角色-接口权限关联';

-- 3. 队列失败任务表（红线 5：失败落库可重放；对应 config/queue.php failed）
CREATE TABLE IF NOT EXISTS `mks_failed_jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT COMMENT '主键',
  `connection` text NOT NULL COMMENT '队列连接',
  `queue` text NOT NULL COMMENT '队列名',
  `payload` longtext NOT NULL COMMENT '任务负载',
  `exception` longtext NOT NULL COMMENT '异常信息',
  `failed_at` int NOT NULL DEFAULT 0 COMMENT '失败时间',
  PRIMARY KEY (`id`),
  KEY `idx_queue` (`queue`(191))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='队列失败任务';

-- 4. 会话管理白名单（登录态必需、不做 RBAC 权限点检查的接口）
INSERT IGNORE INTO `mks_api_permission` (`path`,`methods`,`title`,`group`) VALUES
  ('login/logout',  'POST', '登出',          'admin'),
  ('login/getInfo', 'GET',  '我的信息',      'admin'),
  ('login/getMenus','GET',  '后台菜单树',    'admin'),
  ('dict/options',  'GET',  '字典选项（公共下拉）', 'admin');

-- 5. 字典种子：核心枚举（来源 config/kss.php，码值精确）
INSERT IGNORE INTO `mks_sys_dict` (`dict_type`,`code`,`label`,`sort`) VALUES
  -- 商品类型 goods_type
  ('goods_type','1','卡密商品',1),('goods_type','2','虚拟商品',2),('goods_type','3','实物商品',3),
  -- 供货方式 provide_type
  ('provide_type','1','自营',1),('provide_type','5','供货',2),('provide_type','6','渠道',3),
  -- 商品状态 goods_status（9 值）
  ('goods_status','1','销售中',1),('goods_status','2','暂停中',2),('goods_status','3','已下架',3),
  ('goods_status','4','待审核',4),('goods_status','5','已拒绝',5),('goods_status','6','已售罄',6),
  ('goods_status','7','已预警',7),('goods_status','8','回收站',8),('goods_status','9','已审核',9),
  -- 订单状态 order_status（7 值；「部分退款」扩展码 M2 按 01b 定案后补）
  ('order_status','-1','取消支付',1),('order_status','0','等待支付',2),('order_status','1','等待处理',3),
  ('order_status','2','正在处理',4),('order_status','3','交易成功',5),('order_status','4','取消交易',6),
  ('order_status','5','已经退款',7),
  -- 支付方式 pay_type（字符串码）
  ('pay_type','balance','余额支付',1),('pay_type','alipay','支付宝',2),('pay_type','wechat','微信支付',3),
  ('pay_type','alipayuser','转账加款',4),('pay_type','qqpay','QQ支付',5);
