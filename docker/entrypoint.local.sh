#!/bin/sh
set -eu
umask 027

if [ "${APP_ENV:-}" != local ] || [ "${DB_HOST:-}" != mysql ] || [ "${DB_DATABASE:-}" != clbk_local ]; then
    printf '%s\n' 'Entrypoint lokal hanya untuk compose.local.yaml dan database clbk_local.' >&2
    exit 1
fi
for directory in /data /backups; do
    if ! mountpoint -q "$directory"; then
        printf 'Volume lokal %s belum terpasang.\n' "$directory" >&2
        exit 1
    fi
done

install -d -o www-data -g www-data -m 750 \
    /data/storage /data/storage/app /data/storage/framework \
    /data/storage/framework/cache /data/storage/framework/cache/data \
    /data/storage/framework/sessions /data/storage/framework/views /data/storage/logs
install -d -o www-data -g www-data -m 700 /data/proofs /data/storage/app/private /backups

php /var/www/html/docker/configure-origin.php
gosu www-data php artisan package:discover --ansi
gosu www-data php artisan config:cache
gosu www-data php artisan route:cache
gosu www-data php artisan view:cache
gosu www-data php artisan clbk:database-ready --wait=30 --serve-maintenance

exec /usr/bin/supervisord -c /etc/supervisor/supervisord.conf
