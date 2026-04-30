FROM php:8.4-fpm-alpine

RUN apk add --no-cache \
    postgresql-dev \
    libzip-dev \
    zip \
    unzip \
    nodejs \
    npm \
    git \
    && docker-php-ext-install pdo pdo_pgsql zip pcntl

COPY --from=composer:latest /usr/bin/composer /usr/local/bin/composer

WORKDIR /var/www/html

EXPOSE 9000
CMD ["php-fpm"]
