FROM php:8.2-apache

RUN apt-get update && apt-get install -y \
    libpq-dev libzip-dev libpng-dev unzip git \
 && docker-php-ext-install pgsql pdo_pgsql zip gd \
 && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html
