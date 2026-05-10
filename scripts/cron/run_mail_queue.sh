#!/bin/sh

log="/home/jerrybil/graymentality.ca/runtime/logs/cron/mail-runner.log"

printf "[%s] START process_mail_queue\n" "$(date "+%Y-%m-%d %H:%M:%S %Z")" >> "$log"
/usr/local/bin/php /home/jerrybil/graymentlity.ca/scripts/cron/process_mail_queue.php >> "$log" 2>&1
status=$?
printf "[%s] END process_mail_queue exit=%s\n" "$(date "+%Y-%m-%d %H:%M:%S %Z")" "$status" >> "$log"
exit "$status"