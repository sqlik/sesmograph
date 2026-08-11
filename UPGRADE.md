# Upgrading sesmograph

Check the release notes on
[GitHub Releases](https://github.com/sqlik/sesmograph/releases) before
upgrading - breaking changes and new `.env` options are always listed there.

## From a release archive

```bash
cd /path/to/app
php artisan down

# unpack the new release over the current tree
tar -xzf sesmograph-x.y.z.tar.gz --strip-components=1

composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan optimize
php artisan up
```

The archive ships with `public/build` compiled, so no Node.js is needed.
Your `.env`, `storage/` and the database are never touched by the unpack.

## From git

```bash
cd /path/to/app
php artisan down
git pull
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force
php artisan optimize
php artisan up
```

## After every upgrade

- Open the dashboard and check that events keep arriving (or hit
  `GET /api/v1/health`).
- If something looks wrong with the numbers, the aggregates and the
  suppression list can always be rebuilt from raw events:
  `php artisan app:rebuild-aggregates` and `php artisan app:rebuild-suppressed`.

## Downgrading

Restore the database dump from before the upgrade, then deploy the matching
older release. Migrations are not designed to run backwards in production.
