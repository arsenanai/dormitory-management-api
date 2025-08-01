# Multi-stage build for Laravel backend
FROM php:8.2-fpm-alpine AS base

# Install system dependencies
RUN apk add --no-cache \
    git \
    curl \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    libzip-dev \
    oniguruma-dev \
    libxml2-dev \
    zip \
    unzip \
    supervisor \
    nginx

# Install PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo_mysql \
        mbstring \
        exif \
        pcntl \
        bcmath \
        gd \
        zip \
        opcache

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy composer files
COPY composer.json composer.lock ./

# Install dependencies (without post-install scripts)
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-scripts

# Copy application code
COPY . .

# Run post-install scripts after code is copied
RUN composer run-script post-autoload-dump

# Set permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html/storage \
    && chmod -R 755 /var/www/html/bootstrap/cache

# Development stage
FROM base AS development

# Install development dependencies
RUN composer install --optimize-autoloader --no-interaction

# Copy development configuration (if exists)
RUN if [ -f docker/php/php.ini ]; then cp docker/php/php.ini /usr/local/etc/php/conf.d/custom.ini; fi
RUN if [ -f docker/nginx/nginx.conf ]; then cp docker/nginx/nginx.conf /etc/nginx/nginx.conf; fi

# Expose port
EXPOSE 8000

# Start development server
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]

# Production stage
FROM base AS production

# Copy production configuration (if exists)
RUN if [ -f docker/php/php.ini ]; then cp docker/php/php.ini /usr/local/etc/php/conf.d/custom.ini; fi
RUN if [ -f docker/nginx/nginx.conf ]; then cp docker/nginx/nginx.conf /etc/nginx/nginx.conf; fi
RUN if [ -f docker/supervisor/supervisord.conf ]; then cp docker/supervisor/supervisord.conf /etc/supervisor/conf.d/supervisord.conf; fi

# Expose port
EXPOSE 80

# Start production server
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"] 