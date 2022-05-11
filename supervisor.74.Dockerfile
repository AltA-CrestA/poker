FROM pure/php:7.4-yii2-alpine

WORKDIR /app

RUN set -ex \
    && apk --no-cache add postgresql-dev \
    && docker-php-ext-install pdo pdo_pgsql \
    && composer self-update --2 \
    && apk --no-cache add supervisor

RUN mkdir /var/log/supervisor
COPY docker/php-fpm/php.ini /usr/local/etc/php
COPY docker/php-fpm/www.conf /usr/local/etc/php-fpm.d/www.conf
COPY docker/supervisor/conf/queue /etc/supervisor/conf.d
COPY docker/supervisor/conf/supervisord_no_demon.conf /etc/supervisor/supervisord_no_demon.conf

CMD ["supervisord", "-c", "/etc/supervisor/supervisord_no_demon.conf"]