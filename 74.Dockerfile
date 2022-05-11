FROM pure/php:7.4-yii2-alpine

WORKDIR /app

RUN set -ex \
    && apk --no-cache add postgresql-dev \
    && docker-php-ext-install pdo pdo_pgsql \
    && composer self-update --2

COPY docker/php-fpm/php.ini /usr/local/etc/php
COPY docker/php-fpm/www.conf /usr/local/etc/php-fpm.d/www.conf