FROM php:8.2-cli-alpine

RUN apk add --no-cache \
    git \
    curl \
    libzip-dev \
    oniguruma-dev \
    icu-dev \
    linux-headers \
    && rm -rf /var/cache/apk/*

RUN docker-php-ext-install \
    pdo_mysql \
    mbstring \
    zip \
    pcntl \
    intl \
    sockets

COPY --from=composer:2.7 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY . .

ENV COMPOSER_ALLOW_SUPERUSER=1

RUN composer install --no-dev --optimize-autoloader --no-interaction

RUN if [ -f .env.example ] && [ ! -f .env ]; then cp .env.example .env; fi

EXPOSE 8001

HEALTHCHECK --interval=30s --timeout=10s --start-period=20s --retries=3 \
    CMD curl -f http://localhost:8001/api/traffic/health || exit 1

CMD ["php", "-S", "0.0.0.0:8001", "-t", "public"]