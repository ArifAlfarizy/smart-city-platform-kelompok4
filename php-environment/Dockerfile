FROM php:8.2-fpm-alpine

RUN apk add --no-cache \
    curl \
    libzip-dev \
    oniguruma-dev \
    icu-dev \
    linux-headers \
    nginx \
    supervisor \
    && rm -rf /var/cache/apk/*

RUN docker-php-ext-install \
    pdo \
    pdo_mysql \
    mbstring \
    zip \
    intl \
    sockets

COPY --from=composer:2.7 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY composer.json composer.lock* ./
RUN composer install \
        --no-dev \
        --optimize-autoloader \
        --no-scripts \
        --no-interaction \
    && rm -rf /root/.composer/cache

COPY . .

COPY docker/nginx-slim.conf /etc/nginx/http.d/default.conf
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

RUN if [ -f .env.example ] && [ ! -f .env ]; then cp .env.example .env; fi

EXPOSE 8002

HEALTHCHECK --interval=30s --timeout=10s --start-period=15s --retries=3 \
    CMD curl -f http://localhost:8002/health || exit 1

CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]