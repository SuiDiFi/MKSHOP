#!/bin/sh
# MKSHOP 恢复脚本（M6-D）
# 用法：
#   sh deploy/scripts/restore.sh /opt/mkshop/backup/mysql/mkshop-20261112-033000.sql.gz
# 安全：默认要求输入 yes 确认；CI 可用 FORCE=1 跳过
# 注意：恢复会覆盖当前库数据；建议先停应用写入（维护模式）并现做一次备份
set -eu

DUMP=${1:-}
if [ -z "$DUMP" ] || [ ! -f "$DUMP" ]; then
  echo "用法: sh deploy/scripts/restore.sh <dump.sql.gz>" >&2
  exit 1
fi

MYSQL_CONTAINER=${MYSQL_CONTAINER:-mkshop-mysql}
DB_NAME=${DB_NAME:-mkshop}
DB_USER=${DB_USER:-root}
DB_PASS=${DB_PASS:-root}

if [ "${FORCE:-0}" != "1" ]; then
  printf '将覆盖数据库 %s，输入 yes 继续: ' "$DB_NAME"
  read -r ANSWER
  [ "$ANSWER" = "yes" ] || { echo '已取消'; exit 1; }
fi

echo "[$(date '+%F %T')] 开始恢复 $DUMP"
gunzip -c "$DUMP" | docker exec -i "$MYSQL_CONTAINER" mysql -u"$DB_USER" -p"$DB_PASS" "$DB_NAME"

# 表结构可能来自旧版本，清理字段缓存避免 ORM 使用过期元数据
docker exec mkshop-redis redis-cli FLUSHDB >/dev/null 2>&1 || true

echo "[$(date '+%F %T')] 恢复完成；请重启 PHP 服务并访问 /healthz 复核"
