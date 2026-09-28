# Nexumi ERP Production Deployment

This runbook is the minimum operational baseline for deploying Nexumi ERP. It
does not contain secrets. Store production values in the server environment or
an approved secret manager, never in Git.

## Runtime

- PHP version must satisfy `composer.json` (`^8.2`); use the same PHP minor
  version for web, queue, scheduler, and CLI processes.
- PostgreSQL must be reachable from the application host.
- The web server document root must be the project's `public/` directory. Do
  not expose the repository root, `.env`, `storage/`, or `vendor/` directly.
- Node.js is needed only during release builds. The running application serves
  the compiled assets from `public/build`.

## Required Environment

Set these before caching configuration:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_KEY=base64:GENERATE_A_REAL_KEY
APP_URL=https://erp.example.com
TRUSTED_PROXIES=127.0.0.1

DB_CONNECTION=pgsql
DB_HOST=private-postgres-host
DB_PORT=5432
DB_DATABASE=nexumi
DB_USERNAME=nexumi_app
DB_PASSWORD=use-a-secret-manager

SESSION_DRIVER=database
SESSION_SECURE_COOKIE=true
SESSION_HTTP_ONLY=true
SESSION_SAME_SITE=lax
CACHE_STORE=database
QUEUE_CONNECTION=database
FILESYSTEM_DISK=local

MAIL_MAILER=smtp
MAIL_HOST=smtp.example.com
MAIL_PORT=587
MAIL_USERNAME=use-a-secret-manager
MAIL_PASSWORD=use-a-secret-manager
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@example.com
MAIL_FROM_NAME="Nexumi ERP"
```

Use the actual reverse-proxy IP or CIDR list for `TRUSTED_PROXIES`. Never use
`*` unless the application is completely isolated behind a controlled proxy.
For multiple proxies, separate values with commas.

Generate the key only on the target environment:

```bash
php artisan key:generate --show
```

Copy the result into the secret store. Changing `APP_KEY` after users have
logged in invalidates encrypted cookies and any encrypted application data.

AI production requirements are separate: Gemini free-tier OCR is for
development/demo only. Configure a private/paid provider and review its data
processing terms before enabling production OCR.

## Release Procedure

Run from the release directory, with the web server temporarily in maintenance
mode if the deployment is not atomic:

```bash
composer install --no-dev --prefer-dist --optimize-autoloader
npm ci
npm run build
rm -f public/hot
php artisan storage:link
php artisan migrate --force
php artisan optimize
php artisan queue:restart
```

Do not run `migrate:fresh` in production. The migrations are designed to be
forward-applied; take a verified backup before schema changes.

After `config:cache`/`optimize`, do not call `env()` from application code
outside configuration files. Restart PHP-FPM and queue workers so they load the
new release and configuration.

## Processes

Run a long-lived queue worker under Supervisor, systemd, or an equivalent
process manager. A sample command is:

```bash
php artisan queue:work --sleep=3 --tries=3 --timeout=120 --max-time=3600
```

Run the scheduler every minute with cron:

```cron
* * * * * cd /var/www/nexumi && php artisan schedule:run >> /dev/null 2>&1
```

The scheduler also runs `tenants:process-lifecycle` daily at 01:00 WIB: trial
reminders (3 days and 1 day before), locking expired trials and lapsed paid
periods (`past_due`), and permanently purging trials that were never paid 7
days after they ended (members' emails are kept in `marketing_leads`). Without
cron nothing is purged and no reminder is sent. Preview with
`php artisan tenants:process-lifecycle --dry-run` before enabling it.

The scheduler includes backup execution and cleanup. Configure the backup disk
and notification email before enabling it, then run a backup manually and
verify the archive can be restored. A backup that has never been restore-tested
is not considered a recovery plan.

## Health and Monitoring

- `GET /up` confirms the Laravel application can boot.
- `GET /ready` confirms the application can reach PostgreSQL and returns HTTP
  503 without exposing exception details when the database is unavailable.
- Monitor HTTP 5xx, queue failed jobs, database connections, disk space, backup
  age, and application logs.
- Alert on failed backups and on `/ready` returning non-2xx.
- Keep logs outside the browser and rotate them. Do not enable `APP_DEBUG` in
  production.

## Storage and Permissions

Keep private uploads on the private local disk or a private object-storage
bucket. Only deliberately public assets belong on the public disk. The web and
queue users need write access to `storage/` and `bootstrap/cache/`, but the
repository root and `.env` must not be web-readable.

The `public/hot` marker is development-only. It must not exist in a production
release, otherwise Laravel may point browsers at a non-existent Vite server.

## Pre-Go-Live Checklist

- [ ] `APP_ENV=production`, `APP_DEBUG=false`, real `APP_KEY`, HTTPS `APP_URL`.
- [ ] PostgreSQL credentials use a restricted application user.
- [ ] SMTP sends a real verification email and the OTP flow is tested.
- [ ] Queue worker is supervised and a failed-job alert is configured.
- [ ] Scheduler is running and backup/cleanup jobs have completed once.
- [ ] Backup archive was restored to a separate database successfully.
- [ ] `/up` and `/ready` are monitored by the load balancer.
- [ ] `composer install --no-dev` and `npm run build` completed successfully.
- [ ] No `public/hot`, debug toolbar, test credentials, or demo data is exposed.
- [ ] Company isolation, role permissions, export permissions, and audit logs
      were tested with at least two companies and two roles.
- [ ] PDF/Excel exports and private document downloads were tested over HTTPS.
- [ ] Rollback owner, database migration policy, and incident contact are known.
