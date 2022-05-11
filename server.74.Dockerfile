FROM pure/php:7.4-yii2-alpine

WORKDIR /app

RUN set -ex \
    && apk --no-cache add postgresql-dev \
    && docker-php-ext-install pdo pdo_pgsql \
    && composer self-update --2

ADD docker/php-fpm/server.php.ini $PHP_INI_DIR/conf.d/php.ini
ADD docker/php-fpm/server.www.conf /usr/local/etc/php-fpm.d/www.conf

COPY --chown=www-data:www-data ./www/ /app