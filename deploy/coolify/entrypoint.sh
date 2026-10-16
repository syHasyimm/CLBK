#!/bin/sh
set -eu
umask 0077
cd /var/www/html

if [ "${APP_ENV:-}" != production ]; then
    echo 'Dockerfile Coolify ini memerlukan APP_ENV=production.' >&2
    exit 1
fi
if [ "${DB_DATABASE:-}" != /data/database/database.sqlite ] \
    || [ "${CLBK_PRIVATE_STORAGE:-}" != /data/proofs ] \
    || [ "${BACKUP_PATH:-}" != /backups ]; then
    echo 'Gunakan path container DB_DATABASE=/data/database/database.sqlite, CLBK_PRIVATE_STORAGE=/data/proofs, BACKUP_PATH=/backups.' >&2
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
    echo 'Backup harus menggunakan perangkat disk berbeda dari database.' >&2
    exit 1
fi

for directory in \
    /data/database /data/proofs /data/storage /data/storage/app \
    /data/storage/app/config \
    /data/storage/framework /data/storage/framework/cache \
    /data/storage/framework/cache/data /data/storage/framework/sessions \
    /data/storage/framework/views /data/storage/logs; do
    install -d -o www-data -g www-data -m 750 "$directory"
done
chown www-data:www-data /backups
chmod 700 /backups
if [ -f /data/database/database.sqlite ]; then
    chown www-data:www-data /data/database/database.sqlite
    chmod 600 /data/database/database.sqlite
fi

gosu www-data php artisan package:discover --ansi
gosu www-data php artisan config:cache
gosu www-data php artisan route:cache
gosu www-data php artisan view:cache

# An empty installation stays closed until an operator initializes or restores it.
# Never auto-migrate, auto-seed, regenerate the key, or undo existing maintenance.
if [ ! -s /data/database/database.sqlite ]; then
    gosu www-data php artisan down
    echo 'Database kosong: selesaikan inisialisasi/restore melalui Terminal Coolify.'
fi

exec "$@"
