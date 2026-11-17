#!/bin/sh
# MKSHOP 备份脚本（M6-D / P0-3）：MySQL 全备 + 可选上传目录与 .env
# 用法：
#   sh deploy/scripts/backup.sh                  # 仅数据库
#   BACKUP_UPLOADS=1 sh deploy/scripts/backup.sh  # 数据库 + public/uploads + .env
# 变量：ROOT_DIR / BACKUP_DIR / MYSQL_CONTAINER / DB_NAME / DB_USER / DB_PASS / CRED_FILE / KEEP_DAYS
# 凭据：生产 MySQL root 口令已于 2026-11-17 轮换，默认从 /root/.mkshop-credentials 读取（600，仅 root）。
#       本地开发仍可用 DB_PASS=root 覆盖。口令经 MYSQL_PWD 注入，不出现在命令行/ps 中。
# 建议 crontab（生产用 /root/backups：在应用树之外，不会被部署覆盖也不会被 nginx 伺服）：
#   30 3 * * * cd /opt/mkshop && BACKUP_DIR=/root/backups BACKUP_UPLOADS=1 sh deploy/scripts/backup.sh >> /var/log/mkshop-backup.log 2>&1
set -eu

ROOT_DIR=${ROOT_DIR:-/opt/mkshop}
BACKUP_DIR=${BACKUP_DIR:-$ROOT_DIR/backup}
MYSQL_CONTAINER=${MYSQL_CONTAINER:-mkshop-mysql}
DB_NAME=${DB_NAME:-mkshop}
CRED_FILE=${CRED_FILE:-/root/.mkshop-credentials}
if [ -z "${DB_PASS:-}" ] && [ -r "$CRED_FILE" ]; then
  DB_PASS=$(sed -n 's/^MYSQL_ROOT_PASSWORD=//p' "$CRED_FILE")
fi
DB_USER=${DB_USER:-root}
DB_PASS=${DB_PASS:-root}
KEEP_DAYS=${KEEP_DAYS:-7}
TS=$(date +%Y%m%d-%H%M%S)

mkdir -p "$BACKUP_DIR/mysql" "$BACKUP_DIR/uploads" "$BACKUP_DIR/env"
chmod 700 "$BACKUP_DIR" "$BACKUP_DIR/mysql" "$BACKUP_DIR/uploads" "$BACKUP_DIR/env"

# 同一时刻只允许一个备份进程，避免 cron 与手工跑互相踩
exec 9>"$BACKUP_DIR/.backup.lock"
if ! flock -n 9; then
  echo "[$(date '+%F %T')] 已有备份在运行，跳过本次"
  exit 0
fi

OUT="$BACKUP_DIR/mysql/$DB_NAME-$TS.sql.gz"
TMP_SQL="$BACKUP_DIR/mysql/.$DB_NAME-$TS.sql.partial"
TMP_GZ="$OUT.partial"
trap 'rm -f "$TMP_SQL" "$TMP_GZ"' EXIT INT TERM

echo "[$(date '+%F %T')] 开始备份数据库 $DB_NAME"

# /bin/sh 是 dash，没有 pipefail：`mysqldump | gzip` 的退出码取自 gzip，
# 认证失败会被吞掉并留下一个几十字节的"成功"空壳。必须拆两步、先判 dump 退出码。
if ! docker exec -e MYSQL_PWD="$DB_PASS" "$MYSQL_CONTAINER" mysqldump \
  -u"$DB_USER" \
  --single-transaction --routines --triggers --default-character-set=utf8mb4 \
  "$DB_NAME" > "$TMP_SQL"; then
  echo "[$(date '+%F %T')] 失败：mysqldump 退出非零，未生成备份" >&2
  exit 1
fi

gzip -c "$TMP_SQL" > "$TMP_GZ"

# 三重自检：压缩完整性 + dump 收尾标记 + 表数与源库一致。
# 只看"文件非 0 字节"不足以证明备份可用。
gunzip -t "$TMP_GZ"
if ! tail -n 5 "$TMP_SQL" | grep -q 'Dump completed'; then
  echo "[$(date '+%F %T')] 失败：dump 缺少 Dump completed 收尾标记（可能被截断）" >&2
  exit 1
fi
GOT=$(grep -c '^CREATE TABLE' "$TMP_SQL")
EXPECT=$(docker exec -e MYSQL_PWD="$DB_PASS" "$MYSQL_CONTAINER" mysql -u"$DB_USER" -N -B -e \
  "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='$DB_NAME' AND table_type='BASE TABLE';")
if [ "$GOT" != "$EXPECT" ]; then
  echo "[$(date '+%F %T')] 失败：dump 表数 $GOT 与源库 $EXPECT 不一致" >&2
  exit 1
fi

# 自检通过才发布成正式文件名，失败不会留下看起来可用的备份
mv "$TMP_GZ" "$OUT"
rm -f "$TMP_SQL"
chmod 600 "$OUT"
echo "[$(date '+%F %T')] 数据库备份 OK：$OUT（$GOT 表，$(wc -c < "$OUT") 字节）"

if [ "${BACKUP_UPLOADS:-0}" = "1" ]; then
  if [ -d "$ROOT_DIR/public/uploads" ]; then
    echo "[$(date '+%F %T')] 备份上传目录"
    tar -czf "$BACKUP_DIR/uploads/uploads-$TS.tar.gz" -C "$ROOT_DIR/public" uploads
    chmod 600 "$BACKUP_DIR/uploads/uploads-$TS.tar.gz"
  fi
  # .env 含 APP_KEY 等密钥，只能留在本机 600 文件里，不得外传
  if [ -f "$ROOT_DIR/.env" ]; then
    cp -p "$ROOT_DIR/.env" "$BACKUP_DIR/env/env-$TS.bak"
    chmod 600 "$BACKUP_DIR/env/env-$TS.bak"
    echo "[$(date '+%F %T')] 已备份 .env（600）"
  fi
fi

# 清理过期备份（只在本次成功后执行）
find "$BACKUP_DIR/mysql" -name '*.sql.gz' -mtime +"$KEEP_DAYS" -delete 2>/dev/null || true
find "$BACKUP_DIR/uploads" -name '*.tar.gz' -mtime +"$KEEP_DAYS" -delete 2>/dev/null || true
find "$BACKUP_DIR/env" -name 'env-*.bak' -mtime +"$KEEP_DAYS" -delete 2>/dev/null || true

echo "[$(date '+%F %T')] 备份完成："
ls -lh "$BACKUP_DIR/mysql" | tail -n 3