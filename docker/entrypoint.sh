#!/bin/sh
set -eu
umask 027

# Allow image inspection commands (for example php --version) without booting Laravel.
if [ "${1:-}" != '/usr/bin/supervisord' ]; then
    exec "$@"
fi

if [ "${APP_ENV:-}" != production ]; then
    printf '%s\n' 'Gunakan APP_ENV=production pada environment runtime Coolify.' >&2
    exit 1
fi
if [ "${DB_CONNECTION:-}" != mysql ]; then
    printf '%s\n' 'Gunakan DB_CONNECTION=mysql dan resource MySQL 8.4 terpisah.' >&2
    exit 1
fi
for name in APP_KEY APP_URL DB_HOST DB_DATABASE DB_USERNAME DB_PASSWORD TRUSTED_PROXIES; do
    if ! printenv "$name" | grep -q .; then
        printf 'Environment runtime %s wajib diisi. Lihat DEPLOY.md.\n' "$name" >&2
        exit 1
    fi
done
if [ "${MAIL_MAILER:-}" = smtp ]; then
    for name in MAIL_HOST MAIL_FROM_ADDRESS; do
        if ! printenv "$name" | grep -q .; then
            printf 'Environment runtime %s wajib diisi untuk SMTP produksi.\n' "$name" >&2
            exit 1
        fi
    done
fi

for directory in /data /backups; do
    if ! mountpoint -q "$directory"; then
        printf 'Tambahkan Persistent Storage Coolify dengan Destination Path %s, lalu redeploy.\n' "$directory" >&2
        exit 1
    fi
done

install -d -o www-data -g www-data -m 750 \
    /data/storage /data/storage/app \
    /data/storage/framework /data/storage/framework/cache \
    /data/storage/framework/cache/data /data/storage/framework/sessions \
    /data/storage/framework/views /data/storage/logs
case "${CLBK_PRIVATE_STORAGE:-}" in
    /data/*) ;;
    *) printf '%s\n' 'CLBK_PRIVATE_STORAGE harus berada di dalam volume /data.' >&2; exit 1 ;;
esac
private_directory=$(realpath -m "$CLBK_PRIVATE_STORAGE")
case "$private_directory" in
    /data/*) ;;
    *) printf '%s\n' 'CLBK_PRIVATE_STORAGE harus berada di dalam volume /data.' >&2; exit 1 ;;
esac
install -d -o www-data -g www-data -m 700 /data/storage/app/private "$private_directory" /backups

# Caches belong to this release and are regenerated using runtime environment values.
php /var/www/html/docker/configure-origin.php
gosu www-data php artisan package:discover --ansi
gosu www-data php artisan config:cache
gosu www-data php artisan route:cache
gosu www-data php artisan view:cache

# A new/incomplete database can still serve maintenance and accept terminal commands.
# Never migrate, seed, regenerate APP_KEY or leave maintenance automatically here.
gosu www-data php artisan clbk:database-ready --wait=30 --serve-maintenance

exec "$@"
