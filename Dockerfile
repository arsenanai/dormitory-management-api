# Laravel backend with PHP 8.2 and Nginx
FROM php:8.2-fpm-alpine

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
    nginx \
    postgresql-dev \
    postgresql-client

# Install PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo_pgsql \
        pgsql \
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

# Copy application code first
COPY . .

# Make artisan executable before composer install runs its scripts
RUN chmod +x artisan

# Install dependencies
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Set permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html/storage \
    && chmod -R 755 /var/www/html/bootstrap/cache

# Configure PHP
RUN echo "opcache.enable=1" >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.memory_consumption=128" >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.interned_strings_buffer=8" >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.max_accelerated_files=4000" >> /usr/local/etc/php/conf.d/opcache.ini

# Configure Nginx with proper CORS for local development
RUN mkdir -p /etc/nginx/conf.d && \
    echo 'events {' > /etc/nginx/nginx.conf && \
    echo '    worker_connections 1024;' >> /etc/nginx/nginx.conf && \
    echo '}' >> /etc/nginx/nginx.conf && \
    echo '' >> /etc/nginx/nginx.conf && \
    echo 'http {' >> /etc/nginx/nginx.conf && \
    echo '    include /etc/nginx/mime.types;' >> /etc/nginx/nginx.conf && \
    echo '    default_type application/octet-stream;' >> /etc/nginx/nginx.conf && \
    echo '    sendfile on;' >> /etc/nginx/nginx.conf && \
    echo '    keepalive_timeout 65;' >> /etc/nginx/nginx.conf && \
    echo '    include /etc/nginx/conf.d/*.conf;' >> /etc/nginx/nginx.conf && \
    echo '}' >> /etc/nginx/nginx.conf && \
    echo 'server {' > /etc/nginx/conf.d/default.conf && \
    echo '    listen 80;' >> /etc/nginx/conf.d/default.conf && \
    echo '    server_name localhost;' >> /etc/nginx/conf.d/default.conf && \
    echo '    root /var/www/html/public;' >> /etc/nginx/conf.d/default.conf && \
    echo '    index index.php index.html;' >> /etc/nginx/conf.d/default.conf && \
    echo '' >> /etc/nginx/conf.d/default.conf && \
    echo '    # Handle API routes' >> /etc/nginx/conf.d/default.conf && \
    echo '    location /api {' >> /etc/nginx/conf.d/default.conf && \
    echo '        try_files $uri $uri/ /index.php?$query_string;' >> /etc/nginx/conf.d/default.conf && \
    echo '    }' >> /etc/nginx/conf.d/default.conf && \
    echo '' >> /etc/nginx/conf.d/default.conf && \
    echo '    location / {' >> /etc/nginx/conf.d/default.conf && \
    echo '        try_files $uri $uri/ /index.php?$query_string;' >> /etc/nginx/conf.d/default.conf && \
    echo '    }' >> /etc/nginx/conf.d/default.conf && \
    echo '' >> /etc/nginx/conf.d/default.conf && \
    echo '    location ~ \.php$ {' >> /etc/nginx/conf.d/default.conf && \
    echo '        fastcgi_pass 127.0.0.1:9000;' >> /etc/nginx/conf.d/default.conf && \
    echo '        fastcgi_index index.php;' >> /etc/nginx/conf.d/default.conf && \
    echo '        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;' >> /etc/nginx/conf.d/default.conf && \
    echo '        include fastcgi_params;' >> /etc/nginx/conf.d/default.conf && \
    echo '        # Let Laravel handle CORS headers' >> /etc/nginx/conf.d/default.conf && \
    echo '    }' >> /etc/nginx/conf.d/default.conf && \
    echo '' >> /etc/nginx/conf.d/default.conf && \
    echo '    location ~ /\.(?!well-known).* {' >> /etc/nginx/conf.d/default.conf && \
    echo '        deny all;' >> /etc/nginx/conf.d/default.conf && \
    echo '    }' >> /etc/nginx/conf.d/default.conf && \
    echo '}' >> /etc/nginx/conf.d/default.conf

# Create startup script
RUN echo '#!/bin/sh' > /start.sh && \
    echo 'php-fpm -D' >> /start.sh && \
    echo 'nginx -g "daemon off;"' >> /start.sh && \
    chmod +x /start.sh

# Expose port
EXPOSE 80

# Start services
CMD ["/start.sh"] 