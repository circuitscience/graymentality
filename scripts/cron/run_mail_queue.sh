#!/bin/sh

log="/var/www/html/runtime/logs/cron/mail-runner.log"

mkdir -p "$(dirname "$log")"

printf "[%s] START process_mail_queue\n" "$(date "+%Y-%m-%d %H:%M:%S %Z")" >> "$log"

/usr/local/bin/php /var/www/html/scripts/cron/process_mail_queue.php >> "$log" 2>&1

status=$?

printf "[%s] END process_mail_queue exit=%s\n" "$(date "+%Y-%m-%d %H:%M:%S %Z")" "$status" >> "$log"

exit "$status"