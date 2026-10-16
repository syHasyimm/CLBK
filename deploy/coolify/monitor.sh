#!/bin/sh
set -eu
umask 0077
cd /var/www/html
: "${CLBK_MONITOR_PING_URL:?Isi ping URL privat Healthchecks pada runtime environment}"
if php artisan clbk:doctor --json > /data/storage/logs/doctor-latest.json 2>&1; then
    curl -fsS -m 10 --retry 2 -o /dev/null "$CLBK_MONITOR_PING_URL"
else
    curl -fsS -m 10 --retry 2 -o /dev/null "$CLBK_MONITOR_PING_URL/fail"
    exit 1
fi
