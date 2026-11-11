FROM php:8.2-fpm-alpine

RUN sed -i 's/dl-cdn.alpinelinux.org/mirrors.tuna.tsinghua.edu.cn/g' /etc/apk/repositories

# 常用扩展：pdo_mysql / redis / bcmath / gd / zip / pcntl / swoole
COPY --from=mlocati/php-extension-installer:2 /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions pdo_mysql redis bcmath gd zip pcntl sockets @composer

RUN set -eux; \
    apk add --no-cache $PHPIZE_DEPS; \
    pecl install swoole-5.1.2; \
    docker-php-ext-enable swoole; \
    apk del $PHPIZE_DEPS

# 国内 Composer 加速（可按需移除）
RUN composer config -g repos.packagist composer https://mirrors.aliyun.com/composer/

WORKDIR /var/www/mkshop
