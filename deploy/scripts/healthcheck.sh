#!/bin/sh
# MKSHOP 健康检查（M6-D）：供 cron/监控调用，非 200 返回非零
# 用法：sh deploy/scripts/healthcheck.sh [base_url]
set -eu

BASE=${1:-http://127.0.0.1:8080}
BODY=$(curl -sS -m 5 -o - -w '\n%{http_code}' "$BASE/index.php?s=/healthz" || true)
CODE=$(printf '%s' "$BODY" | tail -n 1)
JSON=$(printf '%s' "$BODY" | sed '$d')

if [ "$CODE" = "200" ]; then
  echo "OK $JSON"
  exit 0
fi
echo "FAIL http=$CODE $JSON" >&2
exit 1
