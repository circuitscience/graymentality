# Gray Mentality Landing

Standalone landing page source for `www.graymentality.ca`.

This project is container-first for local development and Unix-first for hosting. For deployment on a non-container Linux host, point the web server document root at `/var/www/graymentality/public`.

The public folder is now wired as a front controller:

- `public/index.php` resolves every request into the correct page or endpoint
- `public/router.php` lets the same routing work with `php -S`
- Apache and Nginx are configured to send `.php` requests through the controller

## What this folder contains

- `public/` - web document root
- `public/index.php` - front controller
- `public/home.php` - landing page view
- `public/login.php`, `public/register.php`, `public/reset_password.php` - auth entry points
- `bootstrap.php` - env loading and optional DB connection
- `config.php` - env-driven URL and copy settings
- `data/modules.php` - example module content used on the page
- `assets/styles.css` - page styling
- `compose.yml` - local PHP + MariaDB 10.6 container stack
- `docker/php/` - PHP/Apache image definition
- `docker/cron/` - PHP CLI cron runner image definition
- `cron/crontabs/root` - cron schedule copied into the Docker cron image
- `scripts/mariadb/` - import/export/dump helpers for local database snapshots
- `deploy/unix/` - server config and install helpers

## Environment Variables

Recommended local `.env` values for this Docker-based dev setup:

- `APP_ROOT=/var/www/graymentality`
- `MAIN_DOMAIN_URL=https://www.graymentality.ca`
- `LOGIN_URL=https://www.graymentality.ca/login`
- `XFIT_URL=https://xfit.graymentality.ca`
- `DB_HOST=127.0.0.1`
- `DB_PORT=3307`
- `DB_NAME=jerrybil_graymentality`
- `DB_USER=your_database_user`
- `DB_PASS=your_database_password`
- `DB_ROOT_PASSWORD=your_local_root_password`
- `DB_CHARSET=utf8mb4`

Inside the Docker network, the `web` and `cron` containers connect to MariaDB at `db:3306`. The `3307` value above is the host-side mapped port so this project does not collide with xFit on `3306`.

Auth tables:

- `users` is the main account table used by login and registration
- `auth_sessions` stores active login sessions
- `password_resets` stores reset tokens until they expire or are consumed
- `mail_queue` stores outbound emails until a cron runner sends them

Auth routes:

- `public/reset_password.php` handles anonymous reset-token requests and password changes
- `public/change_password.php` handles logged-in password changes

Password reset requests generate a token row in `password_resets`; in development the reset link is shown on the page, and production mail delivery can be wired in later with SMTP.

Outbound mail:

- password reset requests are queued in `mail_queue`
- run `php scripts/cron/process_mail_queue.php` from cron to send queued mail; the sample crontab writes to `runtime/logs/cron/mail-runner.log`
- the Docker cron container runs the mail runner every 5 minutes
- run `sh scripts/cron/run_backup_database.sh` from cron to create database backups in `runtime/backups/db`
- the Docker cron container runs the backup job daily at `02:30` America/Toronto and logs to `runtime/logs/cron/db-backup/log`
- the sample cron entries add timestamped `START` and `END` markers with exit codes around each run
- the cron scripts load the same env file selection as the web bootstrap, including `APP_ENV_FILE=.env.staging`
- set `MAIL_SMTP_HOST`, `MAIL_SMTP_PORT`, and `MAIL_SMTP_ENCRYPTION` in `.env`
- `MAIL_FROM` and `MAIL_FROM_NAME` control the sender identity

Database backups:

- backups are written to `runtime/backups/db`
- backup filenames use only the database name and timestamp, for example `jerrybil_graymentality_2026-05-06_16-05-07.sql.gz`
- the current backup remains in `runtime/backups/db`
- older matching backups are moved to `runtime/backups/db/archive`
- the archive keeps the newest `25` backups by default
- backups are gzip-compressed by default
- set `DB_BACKUP_DIR` to change the output directory
- set `DB_BACKUP_ARCHIVE_LIMIT` to control archive retention count
- set `DB_BACKUP_COMPRESS=false` to write plain `.sql` files instead of `.sql.gz`

Runtime config:

- use `.env.local` for local development and `.env.staging` for remote staging
- safe templates are committed as `.env.local.example` and `.env.staging.example`
- `.env.local`, `.env.staging`, `.env.remote`, and `.env.docker` are ignored so credentials stay out of git
- run `pwsh ./dev-up.ps1` for Docker or `pwsh ./serve-local.ps1` for local PHP
- web preview is on `http://localhost:8088`
- mail cron runs in the `cron` container and writes to `runtime/logs/cron/mail-runner.log`
- db backup cron runs in the `cron` container and writes to `runtime/logs/cron/db-backup/log`
- MariaDB is exposed on `127.0.0.1:3307`
- phpMyAdmin is on `http://localhost:8090`
- the bootstrap and cron scripts still load `.env` by default unless the server sets `GM_ENV_FILE` or `APP_ENV_FILE`, for example `APP_ENV_FILE=.env.staging`

Supported fallback names:

- `SUBDOMAIN_URL`
- `SUBDOMIAIN_URL`

If `LOGIN_URL` is not set, the page falls back to `MAIN_DOMAIN_URL/login`.

## Local PHP Stack

You can also run only the local PHP preview server:

```powershell
pwsh ./serve-local.ps1
```

The local PHP server uses `public/router.php`, so route-based requests work the same way they do under Apache or Nginx.

Optional overrides:

```powershell
pwsh ./serve-local.ps1 -ListenHost 127.0.0.1 -Port 8088
```

## Docker Dev

Start everything with one command:

```powershell
pwsh ./dev-up.ps1
```

Stop it with:

```powershell
pwsh ./dev-down.ps1
```

Dump the local database:

```powershell
pwsh ./scripts/mariadb/dump-db.ps1
```

This will:

- start MariaDB 10.6.24, PHP/Apache, and the cron container in Docker
- start the cron container that processes queued mail
- start phpMyAdmin in Docker
- keep the local preview isolated from the remote host database

## Notes

- The landing page is intentionally separate from the xFit app source.
- The module preview content is a compact example of the module language already used in xFit.
- `SUBDOMIAIN_URL` is accepted for compatibility, but `SUBDOMAIN_URL` is the preferred spelling.
- The local container uses MariaDB 10.6.24-compatible settings and conservative SQL modes.
