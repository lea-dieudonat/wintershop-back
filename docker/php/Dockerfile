FROM php:8.3-fpm-alpine

RUN apk --no-cache add curl git wget bash dpkg

ADD https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/
RUN chmod +x /usr/local/bin/install-php-extensions

RUN install-php-extensions opcache iconv soap
RUN install-php-extensions bcmath
RUN install-php-extensions zip intl fileinfo
RUN install-php-extensions pdo redis mysqli pdo_mysql
RUN install-php-extensions gd

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/bin/ --filename=composer

# Create Composer cache directory with proper permissions
RUN mkdir -p /.composer/cache && chmod 777 /.composer/cache

WORKDIR /var/www

# RUN apt-get update && apt-get install -y \
#     git \
#     unzip \
#     libzip-dev \
#     libicu-dev \
#     libpng-dev \
#     libonig-dev \
#     libxml2-dev \
#     && docker-php-ext-install \
#         pdo \
#         pdo_mysql \
#         intl \
#         zip \
#         gd \
#         opcache \
#         mbstring \
#         xml \
#     && apt-get clean \
#     && rm -rf /var/lib/apt/lists/*

# # Installer Composer
# COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# # Configuration PHP
# RUN echo "memory_limit=512M" >> /usr/local/etc/php/conf.d/custom.ini \
#     && echo "opcache.enable=1" >> /usr/local/etc/php/conf.d/opcache.ini \
#     && echo "opcache.validate_timestamps=1" >> /usr/local/etc/php/conf.d/opcache.ini

# WORKDIR /var/www/html

# CMD ["php-fpm"]