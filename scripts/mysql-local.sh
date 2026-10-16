#!/bin/sh
set -eu
umask 077

# Local WSL MySQL 8.4; keep data outside the application checkout.
mysql_root="${XDG_DATA_HOME:-$HOME/.local/share}/clbk/mysql"
mkdir -p "$mysql_root"
case "${1:-start}" in
    start)
        if [ ! -d "$mysql_root/data" ]; then
            mysqld --no-defaults --initialize-insecure --datadir="$mysql_root/data" >"$mysql_root/initialize.log" 2>&1
        fi
        if [ -f "$mysql_root/mysql.pid" ] && kill -0 "$(cat "$mysql_root/mysql.pid")" 2>/dev/null; then
            printf 'MySQL CLBK sudah berjalan pada 127.0.0.1:3306.\n'
            exit 0
        fi
        mysqld --no-defaults --datadir="$mysql_root/data" --socket="$mysql_root/mysql.sock" \
            --pid-file="$mysql_root/mysql.pid" --port=3306 --bind-address=127.0.0.1 \
            --mysqlx=OFF --secure-file-priv=NULL --log-error="$mysql_root/mysql.log" --daemonize
        printf 'MySQL CLBK berjalan pada 127.0.0.1:3306.\n'
        ;;
    stop)
        if [ -f "$mysql_root/mysql.pid" ]; then
            kill -TERM "$(cat "$mysql_root/mysql.pid")"
        fi
        ;;
    *)
        printf 'Pemakaian: sh scripts/mysql-local.sh [start|stop]\n' >&2
        exit 1
        ;;
esac
