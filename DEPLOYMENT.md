# Hesap360 — Production Deployment Guide

> **⚠️ Deployment ertelemesi:** Bu kılavuz hazırdır; ancak kurulum **henüz**
> yapılmayacaktır. Sunucuya yerleştirme, projenin tamamı bitince (tüm phase'ler
> ve canlı entegrasyonlar onaylandıktan sonra) gerçekleştirilecektir. Şimdilik
> bu belge yalnızca o gün için rehber olarak saklanır.

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

Store backups off-box (separate storage/bucket) by setting `BACKUP_REMOTE_DIR` and
running `php bin/muh backup --upload`. Restore with `php bin/muh backup:restore <file.sql>`
(imports the dump back into MySQL), or import the `.sql` manually and re-run
`php bin/muh migrate` if the schema changed.

**DR rehearsal (prova):** periodically verify the off-box dump really restores.
Restore into a throwaway database, check integrity, then drop it:

```bash
BACKUP_REMOTE_DIR=/mnt/backups php bin/muh backup --upload
mysql -uroot -e 'CREATE DATABASE muh_dr'   # throwaway scratch DB
DB_DATABASE=muh_dr php bin/muh backup:restore $(ls -1t /mnt/backups/*.sql | head -1)
mysql -uroot muh_dr -e 'SELECT COUNT(*) FROM companies; SELECT COUNT(*) FROM audit_logs;'
mysql -uroot -e 'DROP DATABASE muh_dr'
```

If `mysqldump`/`mysql` client binaries are absent PHP falls back to a pure-PDO
dump / statement-by-statement import automatically.

Other daily ops (notifications + daily digest) — requires SMTP for real e-mail:

```cron
0 8 * * *  cd /var/www/muh && php bin/muh notifications >> /var/log/muh-notify.log 2>&1
10 8 * * * cd /var/www/muh && php bin/muh notify:summary >> /var/log/muh-notify.log 2>&1
```

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

---

## 9. Live Deployment Runbook (sıralı kurulum adımları)

> Bu runbook, canlıya geçiş hazırlığını tek sırayla tarif eder. **Kurulum
> yalnızca proje tamamen bitince yapılacaktır.**

1. **Ortam** — `.env.production.example` → sunucuda gerçek `.env`'e kopyala,
   `APP_ENV=production`, `APP_DEBUG=false`, `APP_HTTPS=true`, `SESSION_SECURE=true`,
   gerçek `APP_KEY` (`php bin/muh key:generate`) ve güçlü DB parolası.
2. **Veritabanı** — `php bin/muh migrate && php bin/muh seed` çalıştır.
3. **Hazırlık gate** — `APP_ENV=production php bin/muh doctor` → tüm kontroller
   PASS olmalı (maintenance/security indeksleri/storage).
4. **Apache/Nginx vhost** — proje kökünü docroot yapma, `public/` docroot; TLS +
   HTTP→HTTPS (bkz. bölüm 7).
5. **Zamanlanmış işler (cron)**:
   ```cron
   # her gün 03:00 — veritabanı yedeği
   0 3 * * *  cd /srv/hesap360 && php bin/muh backup >> storage/logs/cron.log 2>&1
   # her gün 06:00 — vade/ödenmemiş fatura e-posta hatırlatmaları
   0 6 * * *  cd /srv/hesap360 && php bin/muh reminders >> storage/logs/cron.log 2>&1
   # her 15 dk — bildirim üretimi + e-Fatura durum sorgulama
   */15 * * * * cd /srv/hesap360 && php bin/muh notifications && php bin/muh efatura:poll >> storage/logs/cron.log 2>&1
   ```
6. **Stripe (ödeme)** — Stripe dashboard'da webhook uç noktasını
   `https://{domain}/api/billing/webhook` olarak ekle, imza anahtarını `BILLING_WEBHOOK_SECRET`'e
   yaz (bkz. bölüm 5). Güvenlik ayarı açıksa webhook IP-bazlı rate-limit ile korunur.
7. **e-Fatura entegratörü** — Ofis ayarları → e-Fatura: sağlayıcı (Logo/Foriba/İzibiz/Genel),
   test/üretim URL'i, kullanıcı adı/şifre. Gerçek gönderim UBL-TR XML ile yapılır;
   `php bin/muh efatura:poll` entegratörden durumları toplar.
8. **Güvenlik politikaları** — `/admin/settings/security`: e-posta doğrulama/2FA zorunluluğu,
   webhook rate-limit gereken şekilde aç.
9. **Doğrulama** — demo akış (kayıt→onboarding→firma→fatura→e-Fatura→abonelik) + `php bin/muh test`
   production ortamında PASS olmalı.
10. **Gözetime alma** — `/api/health` 200, yedekler off-box'a aktarılıyor, `/admin/analytics`
    ofis metrikleri izlenir.
