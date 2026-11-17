#!/bin/sh
# MKSHOP 恢复脚本（M6-D）
# 用法：
#   sh deploy/scripts/restore.sh /opt/mkshop/backup/mysql/mkshop-20261112-033000.sql.gz
# 安全：默认要求输入 yes 确认；CI 可用 FORCE=1 跳过
# 凭据：默认从 /root/.mkshop-credentials 读 MySQL root 与 Redis 口令（2026-11-17 轮换后）；
#       可用 DB_PASS / REDIS_PW / CRED_FILE 覆盖，本地开发仍可 DB_PASS=root。
# 注意：恢复会覆盖当前库数据；建议先停应用写入（维护模式）并现做一次备份
set -eu

DUMP=${1:-}
if [ -z "$DUMP" ] || [ ! -f "$DUMP" ]; then
  echo "用法: sh deploy/scripts/restore.sh <dump.sql.gz>" >&2
  exit 1
fi

MYSQL_CONTAINER=${MYSQL_CONTAINER:-mkshop-mysql}
DB_NAME=${DB_NAME:-mkshop}
CRED_FILE=${CRED_FILE:-/root/.mkshop-credentials}
if [ -z "${DB_PASS:-}" ] && [ -r "$CRED_FILE" ]; then
  DB_PASS=$(sed -n 's/^MYSQL_ROOT_PASSWORD=//p' "$CRED_FILE")
fi
DB_USER=${DB_USER:-root}
DB_PASS=${DB_PASS:-root}
if [ -z "${REDIS_PW:-}" ] && [ -r "$CRED_FILE" ]; then
  REDIS_PW=$(sed -n 's/^REDIS_PASSWORD=//p' "$CRED_FILE")
fi

if [ "${FORCE:-0}" != "1" ]; then
  printf '将覆盖数据库 %s，输入 yes 继续: ' "$DB_NAME"
  read -r ANSWER
  [ "$ANSWER" = "yes" ] || { echo '已取消'; exit 1; }
fi

echo "[$(date '+%F %T')] 开始恢复 $DUMP"
gunzip -c "$DUMP" | docker exec -i -e MYSQL_PWD="$DB_PASS" "$MYSQL_CONTAINER" mysql -u"$DB_USER" "$DB_NAME"

# 表结构可能来自旧版本，清理字段缓存避免 ORM 使用过期元数据
# Redis 自 2026-11-17 起启用 requirepass，无认证 FLUSHDB 会被拒；失败必须显式告警而不是静默跳过
if [ -n "${REDIS_PW:-}" ]; then
  docker exec -e REDISCLI_AUTH="$REDIS_PW" mkshop-redis redis-cli FLUSHDB >/dev/null \
    || echo '警告：Redis 缓存清理失败，请手动 FLUSHDB，否则 ORM 可能沿用过期字段缓存' >&2
else
  docker exec mkshop-redis redis-cli FLUSHDB >/dev/null \
    || echo '警告：Redis 缓存清理失败（若已启用 requirepass 请设置 REDIS_PW 或 CRED_FILE），请手动 FLUSHDB' >&2
fi

echo "[$(date '+%F %T')] 恢复完成；请重启 PHP 服务并访问 /healthz 复核"
