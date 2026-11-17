#!/bin/sh
# MKSHOP 恢复演练（P0-3）：把备份导入隔离临时库，逐表比对行数，再删除临时库。
# 用法：sh deploy/scripts/restore_drill.sh [dump.sql.gz]    # 缺省取最新一份
# 变量：BACKUP_DIR / MYSQL_CONTAINER / DB_NAME / DB_USER / DB_PASS / CRED_FILE / DRILL_DB
# 安全边界：只创建并删除 DRILL_DB（必须以 mks_restore_ 开头且不等于业务库）。
#           绝不写业务库、绝不 DROP 业务库、不清 Redis —— 本脚本不调用 restore.sh。
set -eu

ROOT_DIR=${ROOT_DIR:-/opt/mkshop}
BACKUP_DIR=${BACKUP_DIR:-$ROOT_DIR/backup}
MYSQL_CONTAINER=${MYSQL_CONTAINER:-mkshop-mysql}
DB_NAME=${DB_NAME:-mkshop}
DRILL_DB=${DRILL_DB:-mks_restore_test}
CRED_FILE=${CRED_FILE:-/root/.mkshop-credentials}
if [ -z "${DB_PASS:-}" ] && [ -r "$CRED_FILE" ]; then
  DB_PASS=$(sed -n 's/^MYSQL_ROOT_PASSWORD=//p' "$CRED_FILE")
fi
DB_USER=${DB_USER:-root}
DB_PASS=${DB_PASS:-root}
BT='`'

case "$DRILL_DB" in
  mks_restore_*) : ;;
  *) echo "拒绝：DRILL_DB 必须以 mks_restore_ 开头，当前=$DRILL_DB" >&2; exit 1 ;;
esac
if [ "$DRILL_DB" = "$DB_NAME" ]; then
  echo "拒绝：DRILL_DB 不能等于业务库 $DB_NAME" >&2; exit 1
fi

DUMP=${1:-}
if [ -z "$DUMP" ]; then
  DUMP=$(ls -1t "$BACKUP_DIR/mysql/$DB_NAME"-*.sql.gz 2>/dev/null | head -n 1 || true)
fi
if [ -z "$DUMP" ] || [ ! -f "$DUMP" ]; then
  echo "用法: sh deploy/scripts/restore_drill.sh <dump.sql.gz>（或先跑 backup.sh）" >&2
  exit 1
fi

mysql_q() {
  docker exec -e MYSQL_PWD="$DB_PASS" "$MYSQL_CONTAINER" mysql -u"$DB_USER" -N -B \
    --default-character-set=utf8mb4 "$@"
}

# 从 stdin 喂 SQL 必须带 -i，否则 docker exec 不转发 stdin，查询会静默变成空结果
mysql_file() {
  docker exec -i -e MYSQL_PWD="$DB_PASS" "$MYSQL_CONTAINER" mysql -u"$DB_USER" -N -B \
    --default-character-set=utf8mb4 "$1"
}

# 临时库必须不存在：存在就说明有别人的数据，宁可失败也不删
if [ -n "$(mysql_q -e "SHOW DATABASES LIKE '$DRILL_DB';")" ]; then
  echo "拒绝：$DRILL_DB 已存在，本脚本不删除非自己创建的库" >&2
  exit 1
fi

TMP_SQL=$(mktemp /tmp/mks-drill-XXXXXX.sql)
QF=$(mktemp /tmp/mks-drill-q-XXXXXX)
SRC_COUNTS=$(mktemp /tmp/mks-drill-src-XXXXXX)
DST_COUNTS=$(mktemp /tmp/mks-drill-dst-XXXXXX)
CREATED=0
cleanup() {
  rm -f "$TMP_SQL" "$QF" "$SRC_COUNTS" "$DST_COUNTS"
  if [ "$CREATED" = "1" ]; then
    mysql_q -e "DROP DATABASE ${BT}$DRILL_DB${BT};" \
      || echo "警告：临时库 $DRILL_DB 未自动删除，请手工清理" >&2
  fi
}
trap cleanup EXIT INT TERM

echo "[$(date '+%F %T')] 演练开始：$DUMP -> $DRILL_DB"
gunzip -t "$DUMP"
gunzip -c "$DUMP" > "$TMP_SQL"
if ! tail -n 5 "$TMP_SQL" | grep -q 'Dump completed'; then
  echo "失败：dump 缺少 Dump completed 收尾标记" >&2; exit 1
fi

mysql_q -e "CREATE DATABASE ${BT}$DRILL_DB${BT} DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;"
CREATED=1

# 导入退出码直接取自 mysql（不经管道），失败不会被吞
if ! docker exec -i -e MYSQL_PWD="$DB_PASS" "$MYSQL_CONTAINER" mysql -u"$DB_USER" \
  --default-character-set=utf8mb4 "$DRILL_DB" < "$TMP_SQL"; then
  echo "失败：导入 $DRILL_DB 出错" >&2; exit 1
fi

# information_schema.table_rows 对 InnoDB 是估算值，必须逐表真 COUNT(*)
TABLES=$(mysql_q -e \
  "SELECT table_name FROM information_schema.tables WHERE table_schema='$DB_NAME' AND table_type='BASE TABLE' ORDER BY table_name;")
Q=""
for t in $TABLES; do
  [ -n "$Q" ] && Q="$Q UNION ALL"
  Q="$Q SELECT '$t' AS tbl, COUNT(*) AS n FROM ${BT}$t${BT}"
done
printf '%s;\n' "$Q" > "$QF"

mysql_file "$DB_NAME"  < "$QF" > "$SRC_COUNTS"
mysql_file "$DRILL_DB" < "$QF" > "$DST_COUNTS"

SRC_TABLES=$(wc -l < "$SRC_COUNTS")
DST_TABLES=$(wc -l < "$DST_COUNTS")
SRC_ROWS=$(awk '{s+=$2} END{print s+0}' "$SRC_COUNTS")
DST_ROWS=$(awk '{s+=$2} END{print s+0}' "$DST_COUNTS")
echo "源库 $DB_NAME：$SRC_TABLES 表 / $SRC_ROWS 行"
echo "演练 $DRILL_DB：$DST_TABLES 表 / $DST_ROWS 行"

RC=0
# 空结果绝不能算通过：stdin 没喂进去时两边都是 0 表 0 行，diff 会假阳性
if [ "$SRC_TABLES" -eq 0 ] || [ "$DST_TABLES" -eq 0 ]; then
  echo "失败：行数统计为空，比对无效" >&2; RC=1
fi
if [ "$SRC_TABLES" != "$DST_TABLES" ]; then
  echo "失败：表数不一致" >&2; RC=1
fi
if ! diff -u "$SRC_COUNTS" "$DST_COUNTS"; then
  echo "失败：逐表行数不一致（上方 diff 即差异）" >&2; RC=1
fi

mysql_q -e "DROP DATABASE ${BT}$DRILL_DB${BT};"
CREATED=0
echo "[$(date '+%F %T')] 已删除临时库 $DRILL_DB"

if [ "$RC" = "0" ]; then
  echo "DRILL_PASS 表数=$SRC_TABLES 行数=$SRC_ROWS dump=$DUMP"
else
  echo "DRILL_FAIL dump=$DUMP" >&2
fi
exit "$RC"