#!/bin/sh

log="/var/www/html/runtime/logs/cron/db-backup/log"

mkdir -p "$(dirname "$log")"

printf "[%s] START backup_database\n" "$(date "+%Y-%m-%d %H:%M:%S %Z")" >> "$log"

/usr/local/bin/php /var/www/html/scripts/cron/backup_database.php >> "$log" 2>&1

status=$?

printf "[%s] END backup_database exit=%s\n" "$(date "+%Y-%m-%d %H:%M:%S %Z")" "$status" >> "$log"

exit "$status"
