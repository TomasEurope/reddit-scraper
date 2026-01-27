FROM php:8.4-fpm-alpine

# Runtime deps
RUN apk add --no-cache \
    git \
    unzip \
    icu-dev \
    libzip-dev \
    postgresql-dev \
    rabbitmq-c-dev

# Build deps only for pecl/phpize
RUN apk add --no-cache --virtual .build-deps \
    autoconf \
    g++ \
    make \
    linux-headers

# Core PHP extensions
RUN docker-php-ext-install \
    intl \
    zip \
    pdo_pgsql \
    opcache

# PECL extensions
RUN pecl install redis amqp apcu \
 && docker-php-ext-enable redis amqp apcu

RUN apk add --no-cache \
        autoconf \
        gcc \
        make \
        libtool \
        pkgconfig \
        imagemagick \
        imagemagick-dev \
        linux-headers \
        zlib-dev

 RUN pecl install imagick \
 && docker-php-ext-enable imagick

 RUN docker-php-ext-install sockets
 RUN docker-php-ext-enable sockets

 RUN apk add --no-cache \
    freetype-dev \
    libjpeg-turbo-dev \
    libpng-dev \
 && docker-php-ext-configure gd \
    --with-freetype \
    --with-jpeg \
 && docker-php-ext-install gd

# Cleanup build deps
RUN apk del .build-deps

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Opcache tuning
RUN echo "opcache.enable=1" >> /usr/local/etc/php/conf.d/opcache.ini \
 && echo "opcache.memory_consumption=256" >> /usr/local/etc/php/conf.d/opcache.ini \
 && echo "opcache.max_accelerated_files=20000" >> /usr/local/etc/php/conf.d/opcache.ini

 ENV PHP_MEMORY_LIMIT=512M
