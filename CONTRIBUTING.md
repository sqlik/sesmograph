# Contributing

Thanks for considering it. sesmograph is a small, deliberately focused tool
maintained in spare time - issues and PRs are welcome, patience is
appreciated.

## Development setup

```bash
git clone https://github.com/sqlik/sesmograph.git
cd sesmograph
composer install
npm ci
cp .env.example .env && php artisan key:generate
php artisan migrate                      # SQLite out of the box
php artisan app:create-admin
php artisan db:seed --class=DemoSeeder   # optional demo data
npm run build                            # or: npm run dev
php artisan serve
```

## Before opening a PR

```bash
php artisan test     # the whole suite must pass
vendor/bin/pint      # code style
npm run build        # assets must compile
```

- New behavior needs a feature test; bug fixes need a regression test.
- Keep the scope of one PR to one change.
- UI work must respect both themes (build against the CSS tokens - see
  `docs/architecture.md`, *Frontend*) and the project's design rules: no
  blue/violet accents, terse sentence-case copy without trailing periods,
  no gradients or decorative noise.

## Project boundaries (please read before proposing features)

Some decisions are settled and PRs against them will be declined:

- **Single admin, single instance.** No organizations, roles, registration
  or multi-tenancy.
- **No AWS credentials.** The app only receives events pushed by SNS; it
  never calls AWS APIs on your behalf.
- **Boring stack on purpose**: PHP + MySQL/SQLite, no Docker requirement,
  no Redis, no build-time services - it must keep running on shared
  hosting.

## License of contributions

By submitting a contribution you agree that it is licensed under the same
[O'Saasy license](LICENSE.md) as the project.
