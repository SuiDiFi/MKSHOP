-- 每日库存对账任务（§5.2 收口配套）：mark=stock_audit，type=0 cycle=86400（每 86400 秒 ≈ 每日一次）
-- 处理器在 app/common/commands/Timer.php 的 handlers 注册表；只读对账，漂移仅 Log::error 告警不改数
INSERT INTO `mks_sys_timer`
  (`name`,`mark`,`type`,`title`,`status`,`cycle`,`url`,`last_execution_time`,`update_execution_time`,`create_time`,`delete_time`)
SELECT '库存每日对账', 'stock_audit', 0, '', 1, '86400', '', 0, UNIX_TIMESTAMP(), UNIX_TIMESTAMP(), 0
FROM (SELECT 1 AS i) t
WHERE NOT EXISTS (SELECT 1 FROM (SELECT `mark` FROM `mks_sys_timer` WHERE `mark` = 'stock_audit' LIMIT 1) x);
