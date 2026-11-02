# Commerce CMS — Orders & Customers

Original Laravel ecommerce foundation. Read PROJECT_SPEC.md, PROGRESS.md and DECISIONS.md before work. Phases 0–3 provide catalog browsing, transactional COD/manual checkout, staff order/fulfillment/return workflows and verified customer accounts. Online payment/courier integrations are the next phase.

## Requirements
PHP **8.3**, Composer 2, Node 22+ / npm, MySQL 8 or MariaDB 10.6+ normally. PHP extensions: bcmath, ctype, curl, dom, exif, fileinfo, gd (WebP/AVIF), intl, mbstring, openssl, PDO MySQL/SQLite, tokenizer, xml, zip. SQLite is supported for quick local use. Bangla PDFs also require local Pango/Fontconfig and process execution; the Docker image includes these.

## Install and run
1. Copy `.env.example` to `.env`. Configure your local MySQL database and user. For SQLite, set `DB_CONNECTION=sqlite` and remove `DB_DATABASE` (Laravel uses `database/database.sqlite`).
2. Run `composer setup`. It installs dependencies, preserves existing app keys, migrates, seeds roles, links storage, creates the first **local-only** admin and builds assets. The random admin password is printed once; store it privately. Repeating setup does not reset existing credentials. Use Filament's `php artisan make:filament-user` with the admin guard model for manually managed non-local installs, then assign an authorized role through a trusted deployment process.
3. Run `composer dev` for server, Vite, queue listener and log tail. Alternatively use `php artisan serve` and `npm run dev` in separate terminals. After `npm run build`, the server can run without Node.
4. Open `/` and `/admin`. Staff email is `admin@example.com` after initial local setup. Customer registration/login starts at `/register` and `/login`.

Windows: use Laragon with PHP 8.3 and MySQL, or configure XAMPP to use PHP 8.3; install Composer and Node, open the project terminal and follow the same commands. Set the web root to `public/`, never the repository root.

Phone on the same Wi-Fi: `php artisan serve --host=0.0.0.0`, set APP_URL to your PC's LAN IP with port 8000, build assets (avoids Vite networking), and allow the port only on your trusted network.

## Optional Docker
Set a local `DB_PASSWORD` in `.env`. `docker compose up -d --build` starts PHP-FPM, Nginx, MySQL 8 and Mailpit. Run `docker compose exec php composer install` then `docker compose exec php php scripts/setup.php`; run `npm ci && npm run build` on the host. Server port is 8000; Mailpit UI port is 8025. For Mailpit set MAIL_MAILER=smtp, MAIL_HOST=mailpit, MAIL_PORT=1025. On machines with a corporate CA, set DEV_CA_BUNDLE to the combined trusted CA file for the build secret. No verification is disabled. Containers needing outbound TLS also need the CA mounted/configured for the relevant client. The cloud session uses a local PHP container helper documented in PROGRESS.md.

## Quality
`composer test` runs Pest. `composer lint` checks Pint. `composer analyse` runs Larastan level 6. `composer ci` runs all three and `npm run build`. Run `vendor/bin/pint` to apply formatting. No GitHub Actions or deployment is needed for local validation.

SQLite tests do not prove MySQL row-locking behavior. Later commerce phases must add production-compatible concurrency tests.

## Local defaults and backups
Queue is sync, cache file, mail log, storage public. SMS/courier/payment config values are log/fake placeholders for future phases; checkout/order/customer workflows run locally; external provider adapters remain Phase 4. Secrets stay in ignored `.env` files. Never use development credentials in production.

`php artisan project:backup` makes a private source + database archive under ignored `backups/` outside public. SQLite uses a consistent VACUUM snapshot; MySQL uses mysqldump (must be installed). Environment secrets, vendor, node_modules, uploaded media, logs and Git metadata are excluded; back up those separately where needed. Copy archives securely to external storage regularly. Restore drills and production backup automation belong to later operational work.

## Deployment boundary
VPS and cPanel are target platforms; production deployment/release packaging and full deployment guides are Phase 6 work. Production deployment and the later security/performance phases are not yet verified.

Managed cloud without native PHP: build the image with `docker build --secret id=proxy_ca,src=/etc/ssl/certs/ca-certificates.crt -t commerce-php83 -f docker/php/Dockerfile .`, then use `scripts/cloud composer install --prefer-source --no-interaction` and `scripts/cloud composer setup`. `scripts/cloud composer ci` runs the complete gate; `scripts/cloud serve` starts the server on port 8000. This helper assumes Linux and `/workspace`; use native tools or Compose on other machines. Git source fallback is slower but works when api.github.com distribution archives are unavailable.

## Phase 1 catalog
`/shop`, `/search`, `/category/{slug}`, `/brand/{slug}` and `/product/{slug}` now browse published catalog data. Staff catalog tools live under `/admin`. Run migrations after pulling updates: `php artisan migrate` (or `scripts/cloud php artisan migrate`).

Optional original local sample data: `php artisan db:seed --class=CatalogSeeder` (cloud: prefix with `scripts/cloud`). It creates six bilingual products and original generated sample images, without real brand assets. Sample seeding is opt-in; replace sample images before launch. Catalog CRUD, variants, tabs, badges and CSV instructions are in `docs/admin-guide.md`; addon scheduling is in `docs/addon-phase-map.md`.

Catalog browsing and options connect to the Phase 2 cart/checkout described below. No online gateway verification is simulated. The test suite includes committed-data integration tests and must use a disposable database. `composer ci` runs all current checks.

## Phase 2 cart & checkout
Apply new migrations and seed base configuration with `composer setup` (cloud: `scripts/cloud composer setup`). Existing data, credentials and keys are preserved. `/cart` and `/checkout` now place real local database orders with COD, configured bank transfer or configured manual mobile payment. Every such order remains unpaid until the later staff-verification workflow; no live gateway is called.

Configure shipping, tax, currencies, coupons and Checkout Preferences in `/admin`. Default local shipping prices are examples only. Address entry currently targets Bangladesh; international address/country workflows remain later platform work. See the admin guide for minor-unit prices, gifts, account setup and manual rates.

For background retries, run `php artisan schedule:work` locally (cloud prefix: `scripts/cloud`), or schedule `php artisan schedule:run` each minute on a server. Sync is the local queue default; database queue additionally requires a worker. Mail and SMS use log delivery locally. Private logs can contain test setup links and must never be published.

`composer ci` runs SQLite tests and explicitly skips nine MySQL concurrency checks. Run the same suite against a disposable MySQL 8 test database to execute them. Phase 3 order/customer management is described below.

## Phase 3 orders & customers

Apply migrations and refresh roles with `composer setup`. Managed cloud upgrades must rebuild the PHP image (`scripts/cloud-bootstrap`) to include Pango/Fontconfig for correctly shaped Bangla PDFs. Native hosts need `pango1.0-tools`, Fontconfig and GD; restricted shared hosting must allow the local shaping process. PDF Bangla runs are embedded images; English remains text.

Use `/register`, `/login`, `/email-code`, `/account` and `/track-order` for customer workflows. Email OTP verifies ownership before linking guest orders. First verification revokes pre-verification passwords/sessions and allows setting a password in the verified account session. Local codes/reset links are delivered through private log mail. No default customer password is published.

Staff use `/admin/orders`, `/admin/customers` and Phone Blacklists. Manual payments/refunds record checked receipts; they never send money or invoke a gateway. See [order-management.md](docs/order-management.md) for stock policies, partial shipments, RMA/refund/gift rules, private documents/downloads, permissions and limitations. Run `composer ci`; execute the same tests with disposable MySQL 8 credentials for actual race checks. Phase 4 is next and has not started.

Content and marketing setup: [docs/marketing.md](docs/marketing.md). This includes newsletter/recovery consent, social sign-in, SEO/feeds and optional analytics. Read [PROGRESS.md](PROGRESS.md) for exact phase evidence and limitations.
