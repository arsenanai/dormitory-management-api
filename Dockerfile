FROM php:8.2-fpm

# Install system dependencies and PHP extensions (including GD and mbstring dependencies)
RUN apt-get update \
    && apt-get install -y libpng-dev libjpeg-dev libfreetype6-dev libpq-dev git unzip zip libonig-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo_pgsql pgsql pdo mbstring exif pcntl bcmath gd \
    && rm -rf /var/lib/apt/lists/*

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer