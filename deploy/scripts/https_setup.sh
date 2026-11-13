#!/bin/sh
# ===========================================================================
# MKSHOP 免费 HTTPS 一键准备/签发（mksup.top）
# 依赖：Ubuntu + nginx + acme.sh；证书 Let's Encrypt，acme.sh 自动续期
# 行为：DNS 未指向本机时只准备 nginx/ACME 环境并以退出码 2 报告阻塞；
#       DNS 就绪后重跑即可签发、安装证书并启用 443 + HTTP 跳转。
# 用法：sh /opt/mkshop/deploy/scripts/https_setup.sh
# ===========================================================================
set -e

DOMAIN="mksup.top"
ALT_DOMAIN="www.mksup.top"
VIP_DOMAIN="vip.mksup.top"
SERVER_IP="154.222.25.115"
WEBROOT="/var/www/acme"
CERTDIR="/etc/nginx/ssl/${DOMAIN}"
CONF_SRC="/opt/mkshop/deploy/https/${DOMAIN}.conf"
CONF_DST="/etc/nginx/sites-available/${DOMAIN}.conf"

echo "[1/5] 检查依赖"
if ! command -v nginx >/dev/null 2>&1; then
    apt-get update -qq
    DEBIAN_FRONTEND=noninteractive apt-get install -y -qq nginx
fi
if [ ! -x /root/.acme.sh/acme.sh ]; then
    curl -s https://get.acme.sh | sh -s email=admin@${DOMAIN}
fi
mkdir -p "${WEBROOT}/.well-known/acme-challenge"

resolve_ok() {
    getent ahostsv4 "$1" 2>/dev/null | awk '{print $1}' | grep -qx "${SERVER_IP}"
}

echo "[2/5] 写入临时 HTTP 配置（仅 ACME + 反代，保证 80 可用）"
cat > "${CONF_DST}" <<EOF
server {
    listen 80 default_server;
    listen [::]:80 default_server;
    server_name ${DOMAIN} ${ALT_DOMAIN} _;

    location ^~ /.well-known/acme-challenge/ {
        root ${WEBROOT};
        default_type text/plain;
    }

    location / {
        proxy_pass http://127.0.0.1:8080;
        proxy_set_header Host \$host;
        proxy_set_header X-Real-IP \$remote_addr;
        proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto http;
    }
}
EOF
ln -sf "${CONF_DST}" "/etc/nginx/sites-enabled/${DOMAIN}.conf"
rm -f /etc/nginx/sites-enabled/default
nginx -t
systemctl enable nginx >/dev/null 2>&1 || true
systemctl reload nginx 2>/dev/null || systemctl start nginx

echo "[3/5] 检查 DNS 解析"
if ! resolve_ok "${DOMAIN}"; then
    CURRENT=$(getent ahostsv4 "${DOMAIN}" 2>/dev/null | awk '{print $1}' | sort -u | tr '\n' ' ')
    echo "BLOCKED: ${DOMAIN} 未解析到 ${SERVER_IP}（当前: ${CURRENT:-无 A 记录}）"
    echo "80 端口与 ACME 校验目录已就绪；请在 DNSPod 为 ${DOMAIN} 添加 A 记录 → ${SERVER_IP}，生效后重跑本脚本。"
    exit 2
fi
DOMAINS="-d ${DOMAIN}"
if resolve_ok "${ALT_DOMAIN}"; then
    DOMAINS="${DOMAINS} -d ${ALT_DOMAIN}"
else
    echo "提示: ${ALT_DOMAIN} 未解析到本机，本次只签发 ${DOMAIN}"
fi
if resolve_ok "${VIP_DOMAIN}"; then
    DOMAINS="${DOMAINS} -d ${VIP_DOMAIN}"
else
    echo "提示: ${VIP_DOMAIN} 未解析到本机，本次不签发该子域"
fi

echo "[4/5] 通过 webroot 签发 Let's Encrypt 证书"
# shellcheck disable=SC2086
/root/.acme.sh/acme.sh --issue ${DOMAINS} --webroot "${WEBROOT}" --server letsencrypt --keylength ec-256

echo "[5/5] 安装证书并启用 443 + HTTP 跳转"
mkdir -p "${CERTDIR}"
# shellcheck disable=SC2086
/root/.acme.sh/acme.sh --install-cert -d "${DOMAIN}" --ecc \
    --key-file "${CERTDIR}/privkey.pem" \
    --fullchain-file "${CERTDIR}/fullchain.pem" \
    --reloadcmd "systemctl reload nginx"

cp "${CONF_SRC}" "${CONF_DST}"
ln -sf "${CONF_DST}" "/etc/nginx/sites-enabled/${DOMAIN}.conf"
nginx -t
systemctl reload nginx

echo "自检:"
curl -fsSI "https://${DOMAIN}/" | head -1
curl -fsSI "http://${DOMAIN}/" | head -1
echo "HTTPS_OK"
