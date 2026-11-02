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
