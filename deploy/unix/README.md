# Unix Deployment

This folder contains the Linux/server-side deployment setup for the Gray Mentality landing page.

## Assumptions

- Ubuntu/Debian-style Unix host
- Apache or Nginx
- PHP-FPM or mod_php
- Project deployed at `/var/www/graymentality`
- Web document root set to `/var/www/graymentality/public`

## Files

- `setup.sh` - bootstrap the local deployment tree and `.env`
- `apache-vhost.conf` - Apache virtual host example
- `nginx-site.conf` - Nginx server block example

Both server configs route requests through `public/index.php`, which acts as the front controller.

## Quick Start

1. Copy the project to `/var/www/graymentality`.
2. Make the installer executable: `chmod +x deploy/unix/setup.sh`.
3. Run `deploy/unix/setup.sh`.
4. Copy `.env.staging.example` to `.env.staging` and edit it with the live domain and database credentials.
5. Configure the server environment with `APP_ENV_FILE=.env.staging`, or copy `.env.staging` to `.env` if your host cannot set environment variables.
6. Install the Apache or Nginx config from `deploy/unix/` into your web server.
7. Reload the web server and PHP-FPM.

## Notes

- This project does not require a database to render the landing page, but the bootstrap will connect if credentials are present.
- Use `127.0.0.1` or the local socket host if MySQL is on the same Unix server.
- Only `/public` should be exposed to the web server.
- The web server should send `.php` requests through `public/index.php`; the front controller will resolve the target page or endpoint.
- For remote staging, keep credentials in `.env.staging`; do not commit that file.
- To process password reset mail, add a cron entry that runs `php /var/www/graymentality/scripts/cron/process_mail_queue.php` every few minutes. The sample crontab logs to `/var/www/graymentality/runtime/logs/cron/mail-runner.log`.
- To create database backups, add a cron entry that runs `sh /var/www/graymentality/scripts/cron/run_backup_database.sh` daily. The sample Docker cron schedule writes to `/var/www/graymentality/runtime/logs/cron/db-backup/log`.
- `scripts/cron/process_mail_queue.php` and `scripts/cron/backup_database.php` use the same env-file selection as the web app, including `APP_ENV_FILE=.env.staging`.
- Configure `MAIL_SMTP_HOST`, `MAIL_SMTP_PORT`, `MAIL_SMTP_ENCRYPTION`, `MAIL_SMTP_USERNAME`, and `MAIL_SMTP_PASSWORD` in your staging env file.
- Point SMTP at your real mail server when running the cron runner.
- Install `mariadb-client` or make sure `mariadb-dump` is available on the host if you run the backup cron outside Docker.
- Database backups are named with database name plus timestamp. The newest backup stays in `runtime/backups/db`; older backups move to `runtime/backups/db/archive`, which keeps the newest 25 by default.
