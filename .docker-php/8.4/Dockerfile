ARG ALPINE_VERSION=3.21
FROM alpine:${ALPINE_VERSION}
LABEL Maintainer="Chinhanh Local"
LABEL Description="Nginx and PHP 8.4 for Laravel Livewire."

WORKDIR /var/www/html

RUN apk add --no-cache \
  php84 \
  php84-fpm \
  php84-bcmath \
  php84-ctype \
  php84-curl \
  php84-dom \
  php84-exif \
  php84-fileinfo \
  php84-gd \
  php84-iconv \
  php84-intl \
  php84-mbstring \
  php84-opcache \
  php84-openssl \
  php84-pcntl \
  php84-pdo \
  php84-pdo_mysql \
  php84-pdo_sqlite \
  php84-sqlite3 \
  php84-pecl-memcached \
  php84-pecl-redis \
  php84-phar \
  php84-posix \
  php84-session \
  php84-simplexml \
  php84-tokenizer \
  php84-xml \
  php84-xmlreader \
  php84-xmlwriter \
  php84-zip \
  curl \
  nginx \
  nodejs \
  npm \
  runit \
  tzdata

RUN npm install -g pnpm

ENV TZ=Asia/Ho_Chi_Minh
RUN ln -sf /usr/share/zoneinfo/Asia/Ho_Chi_Minh /etc/localtime \
    && ln -sf /usr/bin/php84 /usr/bin/php

COPY --from=composer/composer:2-bin /composer /usr/bin/composer
ADD rootfs /
COPY boot.sh /sbin/boot.sh

ARG WWWUSER=1000
ARG WWWGROUP=1000
RUN addgroup -g ${WWWGROUP} app \
    && adduser -D -u ${WWWUSER} -G app -s /bin/sh app \
    && mkdir -p /var/www/html /var/cache/nginx/imgproxy /run/nginx /tmp/client_temp /tmp/proxy_temp /tmp/fastcgi_temp \
    && chown -R app:app /var/www/html /run /var/lib/nginx /var/log/nginx /var/cache/nginx /tmp/client_temp /tmp/proxy_temp /tmp/fastcgi_temp \
    && chmod +x /sbin/boot.sh /etc/service/nginx/run /etc/service/php/run

EXPOSE 80 443
CMD ["sh", "/sbin/boot.sh"]
HEALTHCHECK --timeout=10s CMD curl --silent --fail http://127.0.0.1/fpm-ping
