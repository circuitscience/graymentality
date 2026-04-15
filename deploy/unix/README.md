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

## Quick Start

1. Copy the project to `/var/www/graymentality`.
2. Make the installer executable: `chmod +x deploy/unix/setup.sh`.
3. Run `deploy/unix/setup.sh`.
4. Edit `.env` with the live domain and database credentials.
5. Install the Apache or Nginx config from `deploy/unix/` into your web server.
6. Reload the web server and PHP-FPM.

## Notes

- This project does not require a database to render the landing page, but the bootstrap will connect if credentials are present.
- Use `127.0.0.1` or the local socket host if MySQL is on the same Unix server.
- Only `/public` should be exposed to the web server.