# Commerce CMS — Phase 0

Original Laravel ecommerce foundation. Read PROJECT_SPEC.md, PROGRESS.md and DECISIONS.md before work. Phase 0 supplies a storefront shell and staff dashboard; it does not yet sell products.

## Requirements
PHP **8.3**, Composer 2, Node 22+ / npm, MySQL 8 or MariaDB 10.6+ normally. PHP extensions: bcmath, ctype, curl, dom, exif, fileinfo, gd (WebP/AVIF), intl, mbstring, openssl, PDO MySQL/SQLite, tokenizer, xml, zip. SQLite is supported for quick local use.

## Install and run
1. Copy `.env.example` to `.env`. Configure your local MySQL database and user. For SQLite, set `DB_CONNECTION=sqlite` and remove `DB_DATABASE` (Laravel uses `database/database.sqlite`).
2. Run `composer setup`. It installs dependencies, preserves existing app keys, migrates, seeds roles, links storage, creates the first **local-only** admin and builds assets. The random admin password is printed once; store it privately. Repeating setup does not reset existing credentials. Use Filament's `php artisan make:filament-user` with the admin guard model for manually managed non-local installs, then assign an authorized role through a trusted deployment process.
3. Run `composer dev` for server, Vite, queue listener and log tail. Alternatively use `php artisan serve` and `npm run dev` in separate terminals. After `npm run build`, the server can run without Node.
4. Open `/` and `/admin`. Staff email is `admin@example.com` after initial local setup. Customer auth belongs to Phase 3.

Windows: use Laragon with PHP 8.3 and MySQL, or configure XAMPP to use PHP 8.3; install Composer and Node, open the project terminal and follow the same commands. Set the web root to `public/`, never the repository root.

Phone on the same Wi-Fi: `php artisan serve --host=0.0.0.0`, set APP_URL to your PC's LAN IP with port 8000, build assets (avoids Vite networking), and allow the port only on your trusted network.

## Optional Docker
Set a local `DB_PASSWORD` in `.env`. `docker compose up -d --build` starts PHP-FPM, Nginx, MySQL 8 and Mailpit. Run `docker compose exec php composer install` then `docker compose exec php php scripts/setup.php`; run `npm ci && npm run build` on the host. Server port is 8000; Mailpit UI port is 8025. For Mailpit set MAIL_MAILER=smtp, MAIL_HOST=mailpit, MAIL_PORT=1025. On machines with a corporate CA, set DEV_CA_BUNDLE to the combined trusted CA file for the build secret. No verification is disabled. Containers needing outbound TLS also need the CA mounted/configured for the relevant client. The cloud session uses a local PHP container helper documented in PROGRESS.md.

## Quality
`composer test` runs Pest. `composer lint` checks Pint. `composer analyse` runs Larastan level 6. `composer ci` runs all three and `npm run build`. Run `vendor/bin/pint` to apply formatting. No GitHub Actions or deployment is needed for local validation.

SQLite tests do not prove MySQL row-locking behavior. Later commerce phases must add production-compatible concurrency tests.

## Local defaults and backups
Queue is sync, cache file, mail log, storage public. SMS/courier/payment config values are log/fake placeholders for future phases; no external adapters or checkout exist yet. Secrets stay in ignored `.env` files. Never use development credentials in production.

`php artisan project:backup` makes a private source + database archive under ignored `backups/` outside public. SQLite uses a consistent VACUUM snapshot; MySQL uses mysqldump (must be installed). Environment secrets, vendor, node_modules, uploaded media, logs and Git metadata are excluded; back up those separately where needed. Copy archives securely to external storage regularly. Restore drills and production backup automation belong to later operational work.

## Deployment boundary
VPS and cPanel are target platforms; production deployment/release packaging and full deployment guides are Phase 6 work. This Phase 0 app is not a production-ready store.

Managed cloud without native PHP: build the image with `docker build --secret id=proxy_ca,src=/etc/ssl/certs/ca-certificates.crt -t commerce-php83 -f docker/php/Dockerfile .`, then use `scripts/cloud composer install --prefer-source --no-interaction` and `scripts/cloud composer setup`. `scripts/cloud composer ci` runs the complete gate; `scripts/cloud serve` starts the server on port 8000. This helper assumes Linux and `/workspace`; use native tools or Compose on other machines. Git source fallback is slower but works when api.github.com distribution archives are unavailable.
