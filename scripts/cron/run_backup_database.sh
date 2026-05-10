#!/bin/sh

log="/home/jerrybil/graymentality.ca/runtime/logs/cron/db-backup.log"
printf "[%s] START backup_database\n" "$(date "+%Y-%m-%d %H:%M:%S %Z")" >> "$log"
/usr/local/bin/php /home/jerrybil/graymentality.ca/scripts/cron/backup_database.php >> "$log" 2>&1

status=$?
printf "[%s] END backup_database exit=%s\n" "$(date "+%Y-%m-%d %H:%M:%S %Z")" "$status" >> "$log"

exit "$status"
