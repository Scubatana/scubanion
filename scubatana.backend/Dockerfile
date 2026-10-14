FROM php:8.4-cli-alpine AS vendor

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-progress --prefer-dist --no-scripts --no-autoloader

COPY . .
RUN composer dump-autoload --no-dev --optimize \
	&& mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache

FROM php:8.4-fpm-alpine

RUN apk add --no-cache fcgi \
	&& docker-php-ext-install pdo_mysql opcache \
	&& mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

COPY docker/php-fpm-ping.conf /usr/local/etc/php-fpm.d/zz-ping.conf
COPY --chmod=755 docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh

WORKDIR /var/www/html

COPY --from=vendor --chown=www-data:www-data /app /var/www/html

HEALTHCHECK --interval=10s --timeout=3s --start-period=30s --retries=3 \
	CMD SCRIPT_NAME=/ping SCRIPT_FILENAME=/ping REQUEST_METHOD=GET cgi-fcgi -bind -connect 127.0.0.1:9000 | grep -q pong

ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["php-fpm"]
