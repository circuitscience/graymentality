# Gray Mentality Landing

Standalone landing page source for `www.graymentality.ca`.

This project is container-first for local development and Unix-first for hosting. For deployment on a non-container Linux host, point the web server document root at `/var/www/graymentality/public`.

## What this folder contains

- `public/` - web document root
- `public/login.php`, `public/register.php`, `public/reset_password.php` - auth entry points
- `bootstrap.php` - env loading and optional DB connection
- `config.php` - env-driven URL and copy settings
- `data/modules.php` - example module content used on the page
- `assets/styles.css` - page styling
- `docker-compose.dev.yml` - local PHP + MariaDB 10.6 container stack
- `docker/php/` - PHP/Apache image definition
- `scripts/mariadb/` - import/export/dump helpers for local database snapshots
- `deploy/unix/` - server config and install helpers

## Environment Variables

Recommended for production-style hosting:

- `APP_ROOT=/var/www/graymentality`
- `MAIN_DOMAIN_URL=https://www.graymentality.ca`
- `LOGIN_URL=https://www.graymentality.ca/login`
- `XFIT_URL=https://xfit.graymentality.ca`
- `DB_HOST=127.0.0.1`
- `DB_PORT=3306`
- `DB_NAME=jerry_bil_graymentality`
- `DB_USER=jerry_bil_gm`
- `DB_PASS=!GM263e11`
- `DB_CHARSET=utf8mb4`

Auth tables:

- `users` is the main account table used by login and registration
- `auth_sessions` stores active login sessions
- `password_resets` stores reset tokens until they expire or are consumed

Password reset requests generate a token row in `password_resets`; in development the reset link is shown on the page, and production mail delivery can be wired in later with SMTP.

Local Docker stack:

- copy `.env.docker.example` to `.env.docker`
- run `pwsh ./dev-up.ps1`
- web preview is on `http://localhost:8088`
- MariaDB is exposed on `http://localhost:3307`
- phpMyAdmin is on `http://localhost:8090`

Supported fallback names:

- `SUBDOMAIN_URL`
- `SUBDOMIAIN_URL`

If `LOGIN_URL` is not set, the page falls back to `MAIN_DOMAIN_URL/login`.

## Local PHP Stack

You can also run only the local PHP preview server:

```powershell
pwsh ./serve-local.ps1
```

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

- start MariaDB 10.6.24 and PHP/Apache in Docker
- start phpMyAdmin in Docker
- keep the local preview isolated from the remote host database

## Notes

- The landing page is intentionally separate from the xFit app source.
- The module preview content is a compact example of the module language already used in xFit.
- `SUBDOMIAIN_URL` is accepted for compatibility, but `SUBDOMAIN_URL` is the preferred spelling.
- The local container uses MariaDB 10.6.24-compatible settings and conservative SQL modes.
