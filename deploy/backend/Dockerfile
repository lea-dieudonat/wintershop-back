# Production Dockerfile for Symfony Backend
FROM php:8.3-fpm-alpine AS base

# Install system dependencies and PHP extensions
RUN apk --no-cache add \
    nginx \
    supervisor \
    curl \
    git \
    bash \
    mysql-client \
    sed \
    && apk --no-cache add --virtual .build-deps \
        autoconf \
        build-base \
        linux-headers \
        zlib-dev \
        libzip-dev \
        icu-dev \
        libpng-dev \
        libjpeg-turbo-dev \
        freetype-dev

# Install PHP extensions
ADD https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/
RUN chmod +x /usr/local/bin/install-php-extensions && \
    install-php-extensions \
        opcache \
        pdo_mysql \
        intl \
        zip \
        gd \
        redis \
        apcu

# Remove build dependencies
RUN apk del .build-deps

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Configure PHP for production
RUN echo "memory_limit=256M" > /usr/local/etc/php/conf.d/memory.ini && \
    echo "opcache.enable=1" > /usr/local/etc/php/conf.d/opcache.ini && \
    echo "opcache.memory_consumption=128" >> /usr/local/etc/php/conf.d/opcache.ini && \
    echo "opcache.interned_strings_buffer=8" >> /usr/local/etc/php/conf.d/opcache.ini && \
    echo "opcache.max_accelerated_files=10000" >> /usr/local/etc/php/conf.d/opcache.ini && \
    echo "opcache.validate_timestamps=0" >> /usr/local/etc/php/conf.d/opcache.ini && \
    echo "realpath_cache_size=4096K" >> /usr/local/etc/php/conf.d/opcache.ini && \
    echo "realpath_cache_ttl=600" >> /usr/local/etc/php/conf.d/opcache.ini

# Set working directory
WORKDIR /var/www

# Copy composer files
COPY composer.json composer.lock ./

# Install dependencies (no dev dependencies in production)
RUN composer install --no-dev --optimize-autoloader --no-scripts --no-interaction

# Copy application code
COPY . .

# Set permissions
RUN chown -R www-data:www-data /var/www && \
    chmod -R 755 /var/www

# Copy Nginx configuration
COPY deploy/backend/nginx.conf /etc/nginx/http.d/default.conf

# Copy Supervisor configuration
COPY deploy/backend/supervisord.conf /etc/supervisord.conf

# Copy entrypoint script
COPY deploy/backend/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# Expose port (Railway will use $PORT env var)
EXPOSE 8000

# Use entrypoint script
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
