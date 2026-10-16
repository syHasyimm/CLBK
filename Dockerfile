# syntax=docker/dockerfile:1
FROM php:8.4-fpm-bookworm AS php-base

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        ca-certificates curl default-mysql-client gosu nginx supervisor unzip util-linux \
        libicu-dev libzip-dev \
    && docker-php-ext-install -j"$(nproc)" \
        intl pcntl pdo_mysql zip \
    && php -r 'foreach (["curl", "fileinfo", "intl", "mbstring", "openssl", "pcntl", "pdo_mysql", "pdo_sqlite", "sqlite3", "zip"] as $extension) { if (!extension_loaded($extension)) { fwrite(STDERR, "Missing extension: ".$extension.PHP_EOL); exit(1); } }' \
    && cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html

FROM php-base AS php-dependencies
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
COPY . .
RUN composer install --no-dev --no-scripts --prefer-dist \
        --optimize-autoloader --no-interaction \
    && composer check-platform-reqs --no-dev

FROM node:24-bookworm-slim AS frontend
WORKDIR /build
COPY package.json package-lock.json ./
RUN npm ci
COPY . .
# TypeScript resolves ziggy-js from the Composer package in this project.
COPY --from=php-dependencies /var/www/html/vendor ./vendor
ENV VITE_APP_NAME="CLBK TOKE"
RUN npm run build

FROM php-base AS runtime
ENV APP_ENV=production \
    APP_DEBUG=false \
    DB_CONNECTION=mysql \
    DB_PORT=3306 \
    CLBK_PRIVATE_STORAGE=/data/proofs \
    BACKUP_PATH=/backups \
    XDG_CONFIG_HOME=/data/storage/app/config \
    XDG_CACHE_HOME=/data/storage/framework/cache
COPY --from=php-dependencies /var/www/html /var/www/html
COPY --from=frontend /build/public/build /var/www/html/public/build
COPY deploy/coolify/nginx.conf /etc/nginx/nginx.conf
COPY deploy/coolify/php.ini /usr/local/etc/php/conf.d/zz-clbk.ini
COPY deploy/coolify/php-fpm.conf /usr/local/etc/php-fpm.d/zzz-clbk.conf
COPY deploy/coolify/supervisord.conf /etc/supervisor/clbk.conf
COPY --chmod=755 deploy/coolify/entrypoint.sh /usr/local/bin/clbk-entrypoint
COPY --chmod=755 deploy/coolify/monitor.sh /usr/local/bin/clbk-monitor

RUN mkdir -p bootstrap/cache /data /backups \
    && ln -s /data/storage storage \
    && chown root:root /var/www/html \
    && chmod 755 /var/www/html \
    && chown www-data:www-data bootstrap/cache \
    && chmod 770 bootstrap/cache

EXPOSE 8080
HEALTHCHECK --interval=30s --timeout=5s --start-period=120s --retries=5 \
    CMD curl --fail --silent http://127.0.0.1:8080/healthz || exit 1
ENTRYPOINT ["/usr/local/bin/clbk-entrypoint"]
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/clbk.conf"]
