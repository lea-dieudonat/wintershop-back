# Production Dockerfile for WinterShop Backend (Symfony + API Platform)
FROM php:8.3-cli-alpine

# Install system dependencies
RUN apk --no-cache add \
    curl \
    git \
    bash \
    mysql-client \
    unzip

# Install PHP extensions
ADD https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/
RUN chmod +x /usr/local/bin/install-php-extensions && \
    install-php-extensions \
        opcache \
        pdo_mysql \
        intl \
        zip \
        apcu

# Configure PHP for production
RUN echo "memory_limit=256M" > /usr/local/etc/php/conf.d/memory.ini && \
    echo "opcache.enable=1" > /usr/local/etc/php/conf.d/opcache.ini && \
    echo "opcache.memory_consumption=128" >> /usr/local/etc/php/conf.d/opcache.ini && \
    echo "opcache.validate_timestamps=0" >> /usr/local/etc/php/conf.d/opcache.ini

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www

# Copy composer files
COPY composer.json composer.lock symfony.lock ./

# Install dependencies (with dev for fixtures support)
RUN composer install --optimize-autoloader --no-scripts --no-interaction

# Copy application code
COPY . .

# Run post-install scripts
RUN composer dump-autoload --optimize

# Set permissions
RUN chmod +x bin/console && \
    mkdir -p var/cache var/log && \
    chmod -R 777 var

# Copy production entrypoint
COPY deploy/backend/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# Railway uses PORT environment variable
ENV PORT=8080

# Expose port
EXPOSE 8080

# Use entrypoint
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
