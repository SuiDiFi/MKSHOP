FROM php:8.2-fpm-alpine

RUN sed -i 's/dl-cdn.alpinelinux.org/mirrors.tuna.tsinghua.edu.cn/g' /etc/apk/repositories

# 常用扩展：pdo_mysql / redis / bcmath / gd / zip / pcntl / sockets / composer
# pecl 偶发 5xx，加重试（最多 5 次）
COPY --from=mlocati/php-extension-installer:2 /usr/bin/install-php-extensions /usr/local/bin/
RUN set -eux; \
    n=0; until install-php-extensions pdo_mysql redis bcmath gd zip pcntl sockets @composer; do \
      n=$((n+1)); [ $n -ge 5 ] && echo "install-php-extensions failed after $n attempts" && exit 1; \
      echo "retry $n/5 after pecl failure"; sleep 15; \
    done

# Swoole（pecl 源同样不稳，重试 5 次）
RUN set -eux; \
    apk add --no-cache --virtual .swdeps $PHPIZE_DEPS; \
    n=0; until pecl install swoole-5.1.2; do \
      n=$((n+1)); [ $n -ge 5 ] && echo "swoole install failed after $n attempts" && exit 1; \
      echo "retry $n/5"; sleep 15; \
    done; \
    docker-php-ext-enable swoole; \
    apk del .swdeps

# Composer 国内加速（海外服务器可移除）
RUN composer config -g repos.packagist composer https://mirrors.aliyun.com/composer/

WORKDIR /var/www/mkshop
