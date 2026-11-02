# Progress

## Current scope
**Phase 1 — Catalog complete for this phase's scope.** Phase 2 has not started. Read PROJECT_SPEC.md and ADDON_01_NEW_FEATURES.md; the addon's Section 12 mapping and staged dependencies are recorded in docs/addon-phase-map.md.

Phase 0 remains the verified foundation. The current user authorized the next phase and addon-mapped work; earlier Phase 0-only instructions are historical. Work is on `phase-1-catalog`, based on pushed commit `8917dcf`.

## Phase 1 implemented
- New portable migrations for categories, brands, tags, attribute sets/values, products, variants, options/values, private digital download definitions, relations, tabs, badges, search logs, redirects and import reports; Spatie media migration. Factories, optional deterministic bilingual CatalogSeeder and original generated placeholder images.
- Separate staff-only Filament catalog CRUD with shared active-staff/catalog permission policy. Product forms cover translated content, minor-unit prices/sale windows, inventory, visibility, taxonomy, variant generation, options, private files, media/alt/order/removal, video URLs/uploads, SEO and related links.
- Soft deletion, slug-history redirects, synchronized search text, model events after commit and activity records. Category ancestry cycles rejected. Bulk publication, draft, deletion, duplication and price/stock/category edits.
- A1: clone actions on row/edit/bulk; source lock and unique slug/SKU suffixes; draft copies; reset/retain stock; copy nested graph and physically copy images; reset views/ratings; audit source ID. Shared-file references are deliberately not offered.
- A2: translated global/category/per-product tabs, ordering/active flags, per-product suppression of global tabs, sanitized rich output.
- A3: new/sale/out-of-stock/low-stock/free-shipping/pre-order rules; scheduled, prioritized custom product/category badges; configurable limit; product-card and detail rendering. Cart/flash-sale/real best-seller prerequisites remain below.
- Storefront `/shop`, `/category/{slug}`, `/brand/{slug}`, `/product/{slug}`, `/search`; public-product scoping, price/stock/taxonomy/attribute filters, sort, page sizes, grid/list/columns, URL state and pagination. Filters/sort use the displayed active sale/minimum-active-variant price.
- B1–B5 catalog portions: category-scoped live product/category suggestions, recent/popular searches; toggleable top bar and cookie/session locale persistence; lazy quick view; URL toolbar; configurable nested-category/attribute/latest/sale/top-rated/tag sidebars. Real review aggregates are required for top-rated items.
- Product gallery/zoom, responsive WebP and AVIF conversions, variant price/stock/image, option controls, related/recently viewed products, en/bn scaffolding and original mobile-first templates. Purchase controls arrive with the real cart in Phase 2.
- CSV import/export contract: new simple products, integer prices, max 500 rows / 2 MB, row validation report, no overwrite, queue-compatible processing, private uploads, staff authorization and spreadsheet formula neutralization on export.
- Addon security baseline: encrypted credential column/vault with masked accessor; hidden serialization and audit exclusions; executable sentinel leak tests on existing storefront routes. Public image MIME types explicitly exclude executable/SVG content.

## Validation — 2026-11-02
- Full `scripts/cloud composer ci`: Pint, Larastan level 6, Pest and Vite build. **45 tests / 212 assertions passed**.
- Isolated MySQL 8: new schema/migrations, role seeding and **45 tests / 212 assertions passed**, including committed-data FULLTEXT and effective-price tests.
- SQLite uses fast transaction-isolated feature tests; search integration tests use DatabaseMigrations so MySQL records are committed before FULLTEXT queries. Integration tests must run only against a disposable database.
- Chromium: desktop catalog and lazy quick-view open/close; 360px variable-product interaction without horizontal overflow; actual staff login; catalog resources/preferences/import screen/create form; no browser or HTTP asset errors. Screenshots inspected.
- Filament regression tests exercise actual create/edit, row/edit/bulk cloning and bulk price/stock/category actions; unauthorized staff and guests are rejected.
- Leak tests verify encryption at rest, masking, hidden serialization, audit exclusion and current-route output.
- `git diff --check`: clean. No local credentials, databases, uploaded media, dependencies or logs are committed.

## Diagnosed during implementation
- Corrected Livewire property naming so filter state does not collide with its internal attribute collection.
- Optional Filament repeaters now start empty instead of requiring nonexistent child records.
- Staff-role switching tests clear the previous user's session before switching identities.
- InnoDB FULLTEXT cannot see rollback-isolated uncommitted fixtures; moved search tests to committed-data integration coverage.
- Price filters now match displayed sale/variant prices rather than regular prices.

## Run / reuse
Working directory: `/workspace/Fleet_Cart_Like_Site`.
- Install/refresh: `scripts/cloud-bootstrap` (preserves local config/key/admin credentials).
- Apply updates: `scripts/cloud php artisan migrate`.
- Optional local sample catalog: `scripts/cloud php artisan db:seed --class=CatalogSeeder`.
- Start: `scripts/cloud serve`. An existing development server may already own port 8000; reuse it rather than starting a duplicate.
- Check: `scripts/cloud composer ci`.
- Private initial local admin credentials remain in ignored `storage/logs/local-setup.log`; never commit or publish its contents. Native/Windows/Compose instructions remain in README.md.

## Still deferred by mapping / limitations
- Phase 2: cart/checkout/orders, quick-view add-to-cart, cart-line badges, gifts, account-at-checkout, real currency rates/conversion and additional switcher choices. No mislabeled conversion or fake cart success.
- Customer profile preference persistence waits for real accounts; contact/tracking/help links wait for real routes. Blog sidebar/marketing banner widgets and review-driven data arrive with content/reviews.
- Flash-sale badges and real sales-based best-seller badges wait for marketing/order statistics. No fabricated social proof.
- Full credential-leak crawling of future cart/checkout/landing pages and built JS, Security Scan UI and alerts remain Phase 7. Current test coverage is explicitly limited to existing routes.
- XLSX, full-content/variant/media CSV interchange and shared-file media references are not provided by this phase's simple-product CSV/copy contract.
- Digital entitlements/download delivery require orders/accounts. Private definition paths are not exposed on product pages.
- MariaDB, Windows and full Compose stack are not newly validated. No production deployment, Lighthouse target, full security-hardening or cloud-publication claim.
- All remaining addon features stay in their mapped phases (see docs/addon-phase-map.md).

## Exact next step
On “Continue with the next phase,” verify this baseline and implement **Phase 2 — Cart & Checkout**, including its addon items, without rebuilding the catalog.
