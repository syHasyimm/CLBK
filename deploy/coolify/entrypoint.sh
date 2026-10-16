#!/bin/sh
set -eu
umask 0077
cd /var/www/html

if [ "${APP_ENV:-}" != production ]; then
    echo 'Dockerfile Coolify ini memerlukan APP_ENV=production.' >&2
    exit 1
fi
if [ "${DB_CONNECTION:-}" != mysql ] \
    || [ -z "${DB_HOST:-}" ] || [ -z "${DB_DATABASE:-}" ] \
    || [ -z "${DB_USERNAME:-}" ] || [ -z "${DB_PASSWORD:-}" ]; then
    echo 'Isi DB_CONNECTION=mysql, DB_HOST, DB_DATABASE, DB_USERNAME, dan DB_PASSWORD melalui runtime environment Coolify.' >&2
    exit 1
fi
if [ -n "${DB_URL:-}" ]; then
    echo 'Hapus DB_URL; gunakan DB_HOST/DB_PORT/DB_DATABASE/DB_USERNAME/DB_PASSWORD agar konfigurasi MySQL konsisten.' >&2
    exit 1
fi
if [ "${CLBK_PRIVATE_STORAGE:-}" != /data/proofs ] \
    || [ "${BACKUP_PATH:-}" != /backups ]; then
    echo 'Gunakan path container CLBK_PRIVATE_STORAGE=/data/proofs dan BACKUP_PATH=/backups.' >&2
    exit 1
fi

if [ -z "${APP_KEY:-}" ]; then
    echo 'APP_KEY wajib diisi melalui runtime environment Coolify.' >&2
    exit 1
fi

for target in /data /backups; do
    if ! mountpoint -q "$target"; then
        echo "Persistent directory mount $target belum terpasang." >&2
        exit 1
    fi
done

if [ "$(stat -c %d /data)" = "$(stat -c %d /backups)" ]; then
    echo 'Backup harus menggunakan perangkat disk berbeda dari penyimpanan privat aplikasi.' >&2
    exit 1
fi

for directory in \
    /data/proofs /data/storage /data/storage/app \
    /data/storage/app/config \
    /data/storage/framework /data/storage/framework/cache \
    /data/storage/framework/cache/data /data/storage/framework/sessions \
    /data/storage/framework/views /data/storage/logs; do
    install -d -o www-data -g www-data -m 750 "$directory"
done
chown www-data:www-data /backups
chmod 700 /backups
gosu www-data php artisan package:discover --ansi
gosu www-data php artisan config:cache
gosu www-data php artisan route:cache
gosu www-data php artisan view:cache

# Wait for MySQL and keep an uninitialized database closed. Never auto-migrate,
# auto-seed, regenerate the key, or undo existing maintenance.
gosu www-data php artisan clbk:database-ready --wait=60

exec "$@"
