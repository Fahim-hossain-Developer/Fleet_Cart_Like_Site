# Developer guide
Use app/Domain/{Catalog,Cart,Checkout,Orders,Customers,Marketing,Content,Settings,Shipping,Payments,Reports}/{Models,Actions,DTOs,Events,Policies}. Add concrete code only in its authorized phase. Action classes own business behavior; UI classes validate/authorize and delegate.

Money is immutable, nonnegative, integer minor units, with exact addition/subtraction/multiplication and overflow checks. Current two-decimal currencies are BDT/USD/EUR/GBP; future currencies require explicit exponent support rather than relabeling values.

Settings uses an allowlisted validation schema, persistent JSON values and per-key file-cache invalidation. Call its Action rather than updating settings directly. Provider secrets and arbitrary scripts are outside this general settings store.

Staff uses admins table/admin session guard; customers use users/web. Filament checks active status and admin.access. Role permissions are guard-scoped and seeded idempotently. Activity logging excludes password fields. Filament assets are independent of the Tailwind 4 storefront. Livewire provides Alpine and focus support; avoid loading a second Alpine instance.

UI translations live in resources/lang/{en,bn}. Theme Blade files live in resources/views/themes/default. Fonts come from OFL-licensed Fontsource packages and are bundled by Vite; no runtime font CDN.
