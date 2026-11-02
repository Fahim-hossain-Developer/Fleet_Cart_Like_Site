# Developer guide
Use app/Domain/{Catalog,Cart,Checkout,Orders,Customers,Marketing,Content,Settings,Shipping,Payments,Reports}/{Models,Actions,DTOs,Events,Policies}. Add concrete code only in its authorized phase. Action classes own business behavior; UI classes validate/authorize and delegate.

Money is immutable, nonnegative, integer minor units, with exact addition/subtraction/multiplication and overflow checks. Current two-decimal currencies are BDT/USD/EUR/GBP; future currencies require explicit exponent support rather than relabeling values.

Settings uses an allowlisted validation schema, persistent JSON values and per-key file-cache invalidation. Call its Action rather than updating settings directly. Provider secrets and arbitrary scripts are outside this general settings store.

Staff uses admins table/admin session guard; customers use users/web. Filament checks active status and admin.access. Role permissions are guard-scoped and seeded idempotently. Activity logging excludes password fields. Filament assets are independent of the Tailwind 4 storefront. Livewire provides Alpine and focus support; avoid loading a second Alpine instance.

UI translations live in resources/lang/{en,bn}. Theme Blade files live in resources/views/themes/default. Fonts come from OFL-licensed Fontsource packages and are bundled by Vite; no runtime font CDN.

## Catalog (Phase 1)
Catalog models extend CatalogModel for translated data, model events and activity records; each concrete model has a factory and shares the catalog-management policy. New migrations leave Phase 0 schema history intact. Product media uses the installed Spatie library, with explicit non-executable image MIME allowlists. Digital files stay on the private local disk; customer entitlement/download delivery comes with orders/accounts.

Actions: SaveProduct validates writes; DuplicateProduct transactionally copies the graph while locking the source; GenerateVariants is capped/idempotent; CatalogCsv validates rows and supplies a queued import job/report; CatalogSearch uses MySQL FULLTEXT on denormalized translated text and LIKE on SQLite. CatalogPrice supplies a server-generated expression so filters/sort match displayed active sale/minimum-variant prices. No request strings enter raw price SQL.

Livewire CatalogBrowser owns URL filter/view state and locked quick-view/route IDs. ProductDetails scopes variant choices to the public product. Blade output sanitizes rich descriptions/tabs through Filament's installed HTML sanitizer. Locale persists in encrypted cookies and session. Base currency stays authoritative until Phase 2 conversion/rate snapshots.

MySQL InnoDB FULLTEXT indexes only committed records. Search integration tests therefore use DatabaseMigrations in tests/Integration, separately from transaction-isolated feature tests. Run these only against a disposable test database; do not point the test suite at development or production data.

The credential vault stores encrypted_value separately from ordinary setting values; it is hidden from serialization and excluded from audit fields. No storefront component receives the vault. Current leak tests cover existing storefront routes with a sentinel credential; add cart/checkout/landing and built-asset scans as those workflows arrive. Security Scan/alerts are still later-phase work.

## Phase 2 checkout invariants
CartSession uses an unpredictable session token for guest carts and a locked user row for authenticated cart lookup/merge. CartManager scopes mutations to that cart. Cart and item policies describe ownership; no public cart-ID lookup exists. File options and payment proofs stay private. Product/variant IDs and option values are validated server-side; no client prices are used.

QuoteCart refreshes published products, active variants and options, aggregates stock across option lines, enforces product/order limits, and snapshots readable option labels. Percentage options round once per line. PriceCalculator distributes coupon discount exactly, then calculates shipping, gift and line taxes. All authoritative values are integer minor units; bcmath prevents intermediate allocation/FX overflow. The current platform cap is 100 lines, 100 units per line/product stock group and 100000000000 minor-unit cart subtotal.

PlaceOrder locks the cart, reads live inventory in stable order, locks coupons, revalidates address hierarchy/methods and writes order/items/usages/events atomically. Inventory is deducted on placement in this phase. Unique request keys plus the cart lock make retries return the same order. The database unique order-number constraint backs random collision-checked suffixes and a configurable prefix. Order success requires session receipt access or matching authenticated owner.

Emails/setup delivery happen after commit through durable retry records. Scheduler tasks retry pending records (maximum three attempts); run schedule:run every minute or schedule:work locally. With sync queue, normal delivery is immediate and no worker is needed. If switching to database queue, start queue:work too. Email delivery is at-least-once under ambiguous transport failures; partial delivery may be repeated on retry. Local SMS log is a development driver only.

Setup tokens are SHA-256 hashed for validation; encrypted delivery payloads are cleared after sending. Matching a contact never proves identity. Phase 3 email verification now provides guest-order linking. Its confirmation/status and gift invoice policies are documented in order-management.md.

The bundled location dataset's source/license/counts are in database/data/README.md. Operational configuration is audited, policies are permission-scoped and defaults are non-destructive. Test factories cover new entities; common fixtures live in tests/Support so file-filtered tests can run independently.

Concurrency tests launch independent PHP processes on MySQL and verify last-unit competition, one-use coupons and simultaneous duplicate submissions. SQLite deliberately skips those three tests; it does not establish row-lock behavior. Never point integration tests at non-disposable data.

## Phase 3 order/customer invariants

See [order-management.md](order-management.md) for the complete contract, restrictions and hosting dependencies. ManageOrder locks the parent before stock/payment/shipment/refund changes; stable cart upsert plus current reads protects manual-order replay under MySQL repeatable-read. Inventory counters make cancellation/release and refund restocking bounded. Immutable quantity/subtotal/discount and tax-rate snapshots preserve edit arithmetic. Model policies complement domain authorization.

EmailCode commits failed-attempt counters, hashes challenges, atomically consumes successful challenges, and links only verified-email guest orders. First verification revokes pre-verification passwords/sessions. CustomerActive checks authentication version/block state on existing sessions. Public order events dispatch OrderChanged after commit and enqueue a durable delivery job; private notes remain staff-only.

Pango/HarfBuzz shapes Bangla into private cached images; Dompdf composes the PDFs. Rebuild the PHP image when upgrading. No external font/CDN/remote PDF fetch is used. Native hosts require Pango, Fontconfig, GD and process execution. Customer endpoints always recheck ownership even with a valid URL signature.

There are now nine MySQL-only race tests; SQLite explicitly skips them. Do not rebuild Vite assets concurrently with HTTP tests because the manifest is replaced during build. Keep MySQL suites sequential and isolated from development data.

## Phase 4 integration boundary

Read [integrations.md](integrations.md), phase-4-design.md and phase-4-plan.md before changing gateway/courier/OTP code. Never perform external HTTP inside order locks or blindly retry unknown mutations. Keep credentials and delivery outboxes encrypted, callbacks stateless/authenticated, environment snapshots immutable, and financial receipts idempotent. Six scheduler tasks and a queue worker (for asynchronous queue drivers) support recovery. The isolated MySQL suite exercises real concurrent workers and migration rollback; never point it at application data.
