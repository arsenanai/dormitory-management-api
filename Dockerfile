FROM php:8.2-fpm

RUN apt-get update \
    && apt-get install -y libpq-dev git unzip zip \
    && docker-php-ext-install pdo_pgsql pgsql

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer