# Hesap360 — Production Deployment Guide

This guide covers moving Hesap360 from local XAMPP development to a real,
production server. It assumes an Apache + PHP 8 + MySQL environment.

## 1. One-time environment setup

1. Copy the environment template and fill in **production** values:

   ```bash
   cp .env.example .env
   ```

2. Generate a strong `APP_KEY` and put it in `.env`:

   ```bash
   php bin/muh key:generate
   ```

   Paste the printed `APP_KEY=...` value into `.env`.

3. Set production flags in `.env`:

   ```
   APP_ENV=production
   APP_DEBUG=false          # hide error detail; logged to storage/logs instead
   APP_URL=https://your-domain.example
   APP_HTTPS=true           # enables HSTS + automatic HTTPS redirect
   SESSION_SECURE=true      # session cookie only over HTTPS
   APP_MAINTENANCE=false
   ```

   A ready reference is in `.env.production.example`.

4. Ensure the database user is a **least-privilege** account (not `root`),
   and use a strong password.

## 2. Web server (Apache) configuration

Point the virtual host document root at the project `public/` folder so that only
the web-root is exposed — never the whole project directory.

Recommended vhost:

```apache
<VirtualHost *:443>
    ServerName your-domain.example
    DocumentRoot "C:/path/to/muh/public"
    SSLEngine on
    SSLCertificateFile ".../cert.pem"
    SSLCertificateKeyFile ".../key.pem"

    <Directory "C:/path/to/muh/public">
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>

# Redirect all HTTP to HTTPS
<VirtualHost *:80>
    ServerName your-domain.example
    Redirect permanent / https://your-domain.example/
</VirtualHost>
```

The `.htaccess` files already route all requests through `public/index.php`.

## 3. Security hardening already built in

- bcrypt password hashing (cost 12) + rehash-on-login
- CSRF tokens on every state-changing form
- Session fixation protection (ID rotation) + HttpOnly + SameSite cookies
- Tenant isolation at the query layer (every module is tenant-scoped)
- RBAC (view/create/edit/delete/approve/export) via roles → permissions
- Prepared statements everywhere (SQL injection safe)
- Per-IP rate limiting + login attempt lockout
- Optional TOTP 2FA (`/app/settings/security`)
- Full audit log of critical operations
- Security headers: `X-Content-Type-Options`, `X-Frame-Options`,
  `Referrer-Policy`, `Content-Security-Policy`, `Permissions-Policy`,
  and (over HTTPS) `Strict-Transport-Security`.
- Maintenance mode via `APP_MAINTENANCE=true` (returns HTTP 503 while logged out).

## 4. Backups

Create automated SQL backups with the built-in CLI:

```bash
php bin/muh backup      # writes storage/backups/muh-<timestamp>.sql
```

Add a scheduled job (cron on Linux):

```cron
0 2 * * *  cd /var/www/muh && php bin/muh backup >> /var/log/muh-backup.log 2>&1
```

Store backups off-box (separate storage/bucket). Restore by importing the `.sql`
dump into MySQL and re-running `php bin/muh migrate` if needed.

## 5. Migrations & seed data

```bash
php bin/muh migrate     # apply schema (idempotent)
php bin/muh seed        # reference data + optional demo company
```

For a fresh install run:

```bash
php bin/muh migrate --fresh && php bin/muh seed
```

> `--fresh` drops **all** tables first — never run this against production data.

## 6. Health check

The app exposes a lightweight health endpoint for load balancers / uptime checks:

```
GET /api/health   →  {"ok":true,"time":"..."}
```

## 7. TLS / HTTPS notes

- `APP_HTTPS=true` enables host-based HTTP→HTTPS 301 redirect and HSTS.
- It honors the `X-Forwarded-Proto` header, so it works behind an Nginx/CDN TLS
  terminator. Make sure your proxy sets that header (and do not trust it from
  the client — strip it at the edge).
- Do **not** set `APP_HTTPS=true` while still serving plain HTTP locally.

## 8. Common production checklist

- [x] `APP_ENV=production`, `APP_DEBUG=false`
- [x] Unique `APP_KEY` (from `php bin/muh key:generate`)
- [x] TLS enabled; HTTP → HTTPS redirect verified
- [x] `SESSION_SECURE=true`
- [x] Least-privilege DB user + strong password
- [x] Backups scheduled & stored off-box
- [x] File permissions: `storage/` writable by web server, project root not
      directly web-accessible
- [x] `APP_MAINTENANCE` toggled off
