# syntax=docker/dockerfile:1

FROM php:8.4-fpm-bookworm AS php-base

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        ca-certificates curl gosu nginx supervisor util-linux \
        libicu72 libonig5 libzip4 libicu-dev libonig-dev libzip-dev \
    && docker-php-ext-install -j"$(nproc)" \
        bcmath intl mbstring opcache pcntl pdo_mysql zip \
    && apt-mark manual libicu72 libonig5 libzip4 \
    && apt-get purge -y --auto-remove libicu-dev libonig-dev libzip-dev \
    && rm -rf /var/lib/apt/lists/* \
    && mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
    && sed -i '/^fastcgi_param[[:space:]]\+HTTP_HOST[[:space:]]/d' /etc/nginx/fastcgi_params \
    && printf 'fastcgi_param HTTP_HOST $host:$clbk_external_port;\n' >> /etc/nginx/fastcgi_params

WORKDIR /var/www/html

FROM php-base AS dependencies

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader \
    --no-interaction --no-progress --prefer-dist
COPY . .
RUN composer dump-autoload --no-dev --classmap-authoritative --no-scripts \
    && composer check-platform-reqs --no-dev

FROM node:24-bookworm-slim AS assets

WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci --include=dev
COPY tsconfig.json vite.config.ts ./
COPY resources ./resources
COPY public ./public
# TypeScript resolves ziggy-js through the Composer package in tsconfig.json.
COPY --from=dependencies /var/www/html/vendor/tightenco/ziggy ./vendor/tightenco/ziggy
ARG VITE_APP_NAME="CLBK TOKE"
RUN VITE_APP_NAME="$VITE_APP_NAME" npm run build

FROM php-base AS production

ENV APP_ENV=production \
    APP_DEBUG=false \
    APP_MAINTENANCE_DRIVER=file \
    DB_CONNECTION=mysql \
    DB_PORT=3306 \
    DB_CHARSET=utf8mb4 \
    DB_COLLATION=utf8mb4_0900_bin \
    LARAVEL_STORAGE_PATH=/data/storage \
    CLBK_PRIVATE_STORAGE=/data/proofs \
    BACKUP_PATH=/backups \
    BACKUP_REQUIRE_SEPARATE_DISK=false \
    SESSION_DRIVER=database \
    SESSION_ENCRYPT=true \
    SESSION_SECURE_COOKIE=true \
    SESSION_HTTP_ONLY=true \
    SESSION_SAME_SITE=lax \
    SESSION_PATH=/ \
    CACHE_STORE=database \
    QUEUE_CONNECTION=sync \
    MAIL_ENABLED=false \
    MAIL_MAILER=array \
    LOG_CHANNEL=stderr \
    LOG_LEVEL=warning

COPY --from=dependencies /var/www/html /var/www/html
COPY --from=assets /app/public/build /var/www/html/public/build
COPY docker/nginx.conf /etc/nginx/nginx.conf
COPY docker/origin.conf /etc/nginx/clbk-origin.conf
COPY docker/php.ini /usr/local/etc/php/conf.d/zz-clbk.ini
COPY docker/php-fpm.conf /usr/local/etc/php-fpm.d/zz-clbk.conf
COPY docker/supervisord.conf /etc/supervisor/supervisord.conf
COPY --chmod=755 docker/entrypoint.sh /usr/local/bin/clbk-entrypoint

RUN mkdir -p bootstrap/cache /data /backups \
    && chown www-data:www-data bootstrap/cache \
    && chmod 750 bootstrap/cache \
    && rm -f public/hot

EXPOSE 8080
STOPSIGNAL SIGTERM

HEALTHCHECK --interval=30s --timeout=5s --start-period=120s --retries=5 \
    CMD curl --fail --silent --show-error --max-time 4 http://127.0.0.1:8080/healthz || exit 1

ENTRYPOINT ["clbk-entrypoint"]
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/supervisord.conf"]
