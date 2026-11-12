#!/bin/sh
# MKSHOP 备份脚本（M6-D）：MySQL 全备 + 可选上传目录
# 用法：
#   sh deploy/scripts/backup.sh                 # 仅数据库
#   BACKUP_UPLOADS=1 sh deploy/scripts/backup.sh # 数据库 + public/uploads
# 变量：ROOT_DIR / BACKUP_DIR / MYSQL_CONTAINER / DB_NAME / DB_USER / DB_PASS / KEEP_DAYS
# 建议 crontab：30 3 * * * cd /opt/mkshop && BACKUP_UPLOADS=1 sh deploy/scripts/backup.sh >> /var/log/mkshop-backup.log 2>&1
set -eu

ROOT_DIR=${ROOT_DIR:-/opt/mkshop}
BACKUP_DIR=${BACKUP_DIR:-$ROOT_DIR/backup}
MYSQL_CONTAINER=${MYSQL_CONTAINER:-mkshop-mysql}
DB_NAME=${DB_NAME:-mkshop}
DB_USER=${DB_USER:-root}
DB_PASS=${DB_PASS:-root}
KEEP_DAYS=${KEEP_DAYS:-7}
TS=$(date +%Y%m%d-%H%M%S)

mkdir -p "$BACKUP_DIR/mysql" "$BACKUP_DIR/uploads"

echo "[$(date '+%F %T')] 开始备份数据库 $DB_NAME"
docker exec "$MYSQL_CONTAINER" mysqldump \
  -u"$DB_USER" -p"$DB_PASS" \
  --single-transaction --routines --triggers --default-character-set=utf8mb4 \
  "$DB_NAME" | gzip > "$BACKUP_DIR/mysql/$DB_NAME-$TS.sql.gz"

if [ "${BACKUP_UPLOADS:-0}" = "1" ] && [ -d "$ROOT_DIR/public/uploads" ]; then
  echo "[$(date '+%F %T')] 备份上传目录"
  tar -czf "$BACKUP_DIR/uploads/uploads-$TS.tar.gz" -C "$ROOT_DIR/public" uploads
fi

# 清理过期备份
find "$BACKUP_DIR/mysql" -name '*.sql.gz' -mtime +"$KEEP_DAYS" -delete 2>/dev/null || true
find "$BACKUP_DIR/uploads" -name '*.tar.gz' -mtime +"$KEEP_DAYS" -delete 2>/dev/null || true

echo "[$(date '+%F %T')] 备份完成："
ls -lh "$BACKUP_DIR/mysql" | tail -n 3
