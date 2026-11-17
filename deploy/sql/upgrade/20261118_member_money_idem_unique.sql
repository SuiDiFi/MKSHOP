-- =================================================================------------
-- 20261118_member_money_idem_unique.sql
-- 资金幂等键 only_order_no 加唯一索引（运营审查报告 §5.4）
--
-- 背景：MemberMoneyService 的幂等检查在事务与行锁之外，两个并发同键请求可同时通过
--       「不存在」检查 → 重复入账；行锁只串行化，不阻止第二次入账。且该列无任何索引，
--       每次幂等查询都是全表扫描，流水表越大越慢。
-- 口径：only_order_no 是 varchar(255) NULL DEFAULT ''。MySQL 唯一索引允许多个 NULL，
--       但空串之间会互相冲突 —— 所以先把历史空串归一化为 NULL，再建索引。
--       代码侧同步改成「无幂等键写 NULL」，否则第二条无键流水必然 1062。
-- 前置：属生产 SQL，需点名授权。先备份 mks_member_money，再跑第 1 步清查；
--       dup_keys 不为 0 时停下人工核对，不要继续（ALTER 会直接失败）。
-- 幂等：ADD UNIQUE KEY 用 information_schema 守卫，手工重复执行也不报错。
-- 注意：升级器把失败文件也记进 mks_schema_version，而待执行清单只按 version 过滤、
--       不看 status —— 本文件若执行失败，重跑 mks:upgrade 不会重试它，
--       必须先 DELETE 掉台账里 status='fail' 的这行再重跑。
-- =================================================================------------

-- 1) 重复键清查（期望 dup_keys=0；本行是注释，需手工执行确认）
-- SELECT COUNT(*) AS dup_keys FROM (
--   SELECT `only_order_no` FROM `mks_member_money`
--    WHERE `only_order_no` IS NOT NULL AND `only_order_no` <> ''
--    GROUP BY `only_order_no` HAVING COUNT(*) > 1
-- ) d;

-- 2) 历史空串归一化为 NULL（无幂等键的流水不该互相冲突）
UPDATE `mks_member_money` SET `only_order_no` = NULL WHERE `only_order_no` = '';

-- 3) 唯一索引（已存在则跳过）
SET @uk_exists := (SELECT COUNT(*) FROM information_schema.statistics
                    WHERE table_schema = DATABASE()
                      AND table_name   = 'mks_member_money'
                      AND index_name   = 'uk_only_order_no');
SET @ddl := IF(@uk_exists = 0,
  'ALTER TABLE `mks_member_money` ADD UNIQUE KEY `uk_only_order_no` (`only_order_no`)',
  'SELECT ''uk_only_order_no already exists'' AS msg');
PREPARE stmt FROM @ddl;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 自检（期望：uk_only_order_no 的 Non_unique=0；empties=0）
-- SHOW INDEX FROM `mks_member_money` WHERE Key_name = 'uk_only_order_no';
-- SELECT COUNT(*) AS total, SUM(`only_order_no` = '') AS empties,
--        SUM(`only_order_no` IS NULL) AS nulls FROM `mks_member_money`;