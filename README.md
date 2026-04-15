<<<<<<< HEAD
# Gray Mentality Landing

Standalone landing page source for `www.graymentality.ca`.

This project is Unix-first for hosting, but it also includes a local Docker setup for development. On a non-container Linux host, point the web server document root at `/var/www/graymentality/public`.

## What this folder contains

- `public/` - web document root
- `bootstrap.php` - env loading and optional DB connection
- `config.php` - env-driven URL and copy settings
- `data/modules.php` - example module content used on the page
- `assets/styles.css` - page styling
- `docker-compose.dev.yml` - local MariaDB 10.6 container stack
- `scripts/mariadb/` - import/export helpers for local database snapshots
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

Local Docker preview:

- copy `.env.local.example` to `.env.local`
- the local MariaDB container binds to `127.0.0.1:3307`
- phpMyAdmin binds to `127.0.0.1:8090`

Supported fallback names:

- `SUBDOMAIN_URL`
- `SUBDOMIAIN_URL`

If `LOGIN_URL` is not set, the page falls back to `MAIN_DOMAIN_URL/login`.

## Local MariaDB 10.6

1. Copy `.env.local.example` to `.env.local`.
2. Start the stack:
   ```powershell
   docker compose --env-file .env.local -f docker-compose.dev.yml up -d
   ```
3. Import a dump into the local container:
   ```powershell
   pwsh ./scripts/mariadb/import-dump.ps1 -DumpPath .\backups\latest.sql.gz
   ```
4. Export the local database back out:
   ```powershell
   pwsh ./scripts/mariadb/export-dump.ps1 -OutputPath .\backups\graymentality.sql
   ```

## Notes

- The landing page is intentionally separate from the xFit app source.
- The module preview content is a compact example of the module language already used in xFit.
- `SUBDOMIAIN_URL` is accepted for compatibility, but `SUBDOMAIN_URL` is the preferred spelling.
- The local container uses MariaDB 10.6.24-compatible settings and conservative SQL modes.
## Local Web Server

Start the app on a local PHP server:

```powershell
pwsh ./serve-local.ps1
```

Optional overrides:

```powershell
pwsh ./serve-local.ps1 -ListenHost 127.0.0.1 -Port 8088
```
## Dev Stack

Start everything with one command:

```powershell
pwsh ./dev-up.ps1
```

Stop it with:

```powershell
pwsh ./dev-down.ps1
```

This will:

- start MariaDB 10.6.24 and phpMyAdmin in Docker
- start the PHP preview server on `http://127.0.0.1:8088`
- keep the local preview isolated from the remote host database
=======
# graymentality
Portal for xfit
>>>>>>> 5371159662b7975ff51aa11791314735c70b8d8a
