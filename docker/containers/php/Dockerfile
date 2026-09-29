# Development image: PHP-FPM with the extensions the app needs plus Composer for CLI use.
ARG PHP_VERSION=8.4
FROM php:${PHP_VERSION}-fpm-alpine

COPY --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions soap intl pdo_mysql zip opcache \
    && apk add --no-cache bash git unzip

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY ini/php.ini /usr/local/etc/php/conf.d/zz-app.ini
COPY ini/www.conf /usr/local/etc/php-fpm.d/zz-app.conf

WORKDIR /app
