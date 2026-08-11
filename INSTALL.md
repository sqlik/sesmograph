# Installing sesmograph

sesmograph runs on plain PHP hosting: a VPS with any control panel
(CloudPanel, cPanel, Plesk, ISPConfig...) or a decent shared host. No Docker,
no Redis, no queue daemon - a web server, PHP and a database are enough.

Every step below has a concrete command and an expected result. Two values
appear throughout - replace them with your own:

- `ses.example.com` - your domain
- `/path/to/app` - the directory the code lives in (on panel hosts this is
  usually something like `/home/<site-user>/htdocs/ses.example.com`)

## Requirements

- PHP **8.3+** with the standard extensions (`pdo_mysql` or `pdo_sqlite`,
  `openssl`, `mbstring`, `curl`, `gd` or `imagick` not required)
- MySQL 8 / MariaDB 10.6+ (SQLite works too and is fine for small volumes)
- HTTPS with a valid certificate - **required**, Amazon SNS only delivers to
  HTTPS endpoints and the app forces `https://` in production
- Ability to point the web server's document root at the app's `public/`
  subdirectory
- Cron (two entries, both once per minute)
- Composer on the server, or the ability to upload files
- Node.js 20+ **only** if you install from git - release archives ship with
  the frontend already built

## 1. Get the code

Pick one of the two:

**A. Release archive (recommended, no Node.js needed)**

Download the latest `sesmograph-x.y.z.tar.gz` from
[GitHub Releases](https://github.com/sqlik/sesmograph/releases), upload and
unpack it into your app directory, then install PHP dependencies:

```bash
cd /path/to/app
tar -xzf sesmograph-x.y.z.tar.gz --strip-components=1
composer install --no-dev --optimize-autoloader
```

The archive includes `public/build` (compiled CSS/JS), so `npm` is never
needed on the server.

**B. Git clone (build the assets yourself)**

```bash
cd /path/to/app   # must be empty
git clone https://github.com/sqlik/sesmograph.git .
composer install --no-dev --optimize-autoloader
npm ci && npm run build
```

## 2. Configure

```bash
cp .env.example .env
php artisan key:generate
```

Open `.env` in an editor and set (leave the other lines alone). The `DB_`
lines are commented out - uncomment and fill them, and remove the existing
`DB_CONNECTION=sqlite` line:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://ses.example.com
APP_TIMEZONE=Europe/Warsaw

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=sesmograph
DB_USERNAME=sesmograph
DB_PASSWORD=<your database password>

SESSION_SECURE_COOKIE=true
```

(To use SQLite instead: keep `DB_CONNECTION=sqlite`, remove the other `DB_`
lines and run `touch database/database.sqlite`.)

`APP_TIMEZONE` controls how event times are stored and displayed - set it
to your local timezone ([full list](https://www.php.net/manual/en/timezones.php))
or leave it out to stay on UTC.

Then create the tables and build the config cache:

```bash
php artisan migrate --force
php artisan optimize
```

`migrate --force` should print a list of migrations ending in `DONE`.

## 3. Point the web server at `public/`

The document root must be the app's `public/` subdirectory - never the app
root (that would expose `.env`).

- **CloudPanel**: site -> Settings -> Root Directory -> append `/public`.
- **cPanel**: Domains -> Manage -> Document Root -> `.../public` (on some plans
  only addon domains allow this).
- **Plesk**: Hosting Settings -> Document root -> `.../public`.
- **Bare nginx**: `root /path/to/app/public;` and the standard Laravel
  `try_files $uri $uri/ /index.php?$query_string;` location.
- **Bare Apache**: point the vhost at `public/`; the bundled
  `public/.htaccess` handles the rewrites.

Visit `https://ses.example.com` - you should see the sesmograph sign-in page.

## 4. Create the admin account

There is no registration - the single admin account is created from the
terminal only:

```bash
php artisan app:create-admin
```

The command asks for a name, email and password. Then open the site, sign in,
and the app immediately walks you through mandatory two-factor setup: scan
the QR code with Google Authenticator / 1Password / Aegis and confirm with a
6-digit code. **Store the recovery codes outside the browser** (Download
button).

Password reset is also terminal-only: `php artisan app:reset-password`
(add `--disable-2fa` if you lost the authenticator too).

## 5. Cron

Two entries, both every minute (your panel's Cron UI or `crontab -e`):

```cron
* * * * * php /path/to/app/artisan schedule:run >> /dev/null 2>&1
* * * * * php /path/to/app/artisan queue:work --stop-when-empty --max-time=50 >> /dev/null 2>&1
```

The scheduler runs: `app:evaluate-alerts` (every 5 min - threshold and
silence alert rules), `app:prune-content` (03:15) and `app:prune-events`
(03:30 - event retention, 30 days by default; daily aggregates are never
pruned).

## 6. First topic and AWS

1. In the panel: **Topics -> Add topic** - one topic per app or mail stream.
2. Open the topic's **AWS setup** page. It walks you through the AWS console
   (or AWS CLI - expand "Prefer CLI?" under each step): SES configuration
   set -> SNS topic -> HTTPS subscription pointing at the topic's webhook URL.
3. The subscription confirms itself (the app fetches the `SubscribeURL`
   after verifying the SNS signature). Events appear in the panel seconds
   after the next email you send through the configuration set.

sesmograph **never stores AWS credentials** - it only receives signed SNS
notifications on a secret per-topic webhook URL.

## 7. API tokens and MCP (optional)

**Settings -> API tokens** - one token per consuming service. That page also
contains the full REST endpoint reference, `curl` examples, and ready-made
MCP client configuration (Claude Code / Cursor) for the
`https://ses.example.com/mcp` endpoint.

`GET /api/v1/health` (Bearer token) is made for uptime monitors: it returns
`ok` plus the age of the newest event.

## 8. Security checklist

- `APP_DEBUG=false`, `APP_ENV=production`, `SESSION_SECURE_COOKIE=true`
- HTTPS with a valid certificate (production forces `https://`)
- 2FA is mandatory - the setup screen cannot be skipped
- Sign-in and the 2FA challenge are rate-limited to 5 attempts/min;
  the API to 120 requests/min
- Webhooks: SNS signature verification + a secret token in the URL;
  an inactive topic answers 410
- API tokens are stored as SHA-256 hashes only; alert channel secrets are
  encrypted at rest

## 9. Updating

See [UPGRADE.md](UPGRADE.md).

## 10. Backup

A database dump is all that matters (daily aggregates are kept forever and
are the only data you cannot reproduce after retention), plus `.env`:

```bash
mysqldump sesmograph | gzip > ~/sesmograph-$(date +%F).sql.gz
cp /path/to/app/.env ~/env-backup-$(date +%F)
```

Move the dumps off the server (e.g. `scp` them to your machine).

---

## Appendix: CloudPanel walkthrough

A concrete end-to-end example on a CloudPanel VPS.

1. **Site**: Sites -> Add Site -> Create a PHP Site. Domain
   `ses.example.com`, PHP 8.3+, note the Site User and its password (that is
   your SSH account). After creation: Settings -> Root Directory -> append
   `/public`. Then SSL/TLS -> Actions -> New Let's Encrypt Certificate.
2. **Database**: Databases -> Add Database - name `sesmograph`, user
   `sesmograph`, generate and save the password.
3. **Shell**: `ssh root@<server>`, then `su - <site-user>`,
   `cd ~/htdocs/ses.example.com`.
4. **Code**: the directory must be empty before installing -
   `ls -A` and remove what it lists. If
   `rm: cannot remove 'public/.well-known': Permission denied` appears, that
   directory belongs to root (Let's Encrypt renewals): `exit` to root,
   `rm -rf /home/<site-user>/htdocs/ses.example.com/public`, `su - <site-user>`
   again - CloudPanel recreates `.well-known` at the next renewal. Then
   follow step 1 above (release archive or git clone).
5. **Cron**: site -> Cron Jobs -> Add Cron Job - the two entries from step 5,
   with `/home/<site-user>/htdocs/ses.example.com/artisan` as the path.

Everything else is identical to the generic steps.
