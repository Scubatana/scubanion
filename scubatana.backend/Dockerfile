FROM php:8.4-fpm-alpine

RUN apk add --no-cache \
	git \
	unzip \
	libpng-dev \
	libzip-dev \
	oniguruma-dev \
	&& docker-php-ext-install \
	pdo_mysql \
	mbstring \
	bcmath \
	exif \
	gd \
	zip

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY . .

COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["php-fpm"]
