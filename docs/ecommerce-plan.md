# Ecommerce implementation plan

PROJECT_SPEC.md is the baseline. ADDON_01_NEW_FEATURES.md supplies mapped additions; docs/addon-phase-map.md records their actual delivery and dependencies.

- Phase 0: complete foundation; encrypted credential baseline backfilled under the addon.
- Phase 1: catalog CRUD, media/variants/options/digital definitions, CSV, storefront/search, cloning/tabs/badges, catalog-side B1–B5.
- Phase 2 next: cart, coupons, addresses/shipping/tax, checkout/order creation and addon gift/account-at-checkout/basic-currency work. Complete quick-view cart submission and cart-line badges here.
- Phases 3–8 remain in their original order with addon's Section 12 mapping. No production deployment or complete store claim yet.

Architecture: original Laravel 12 modular monolith; fixed PHP 8.3 / Filament 3 / Livewire 3 / Tailwind 4; MySQL normal use, SQLite quick start. Catalog schema is in the new migrations. Integer money is authoritative. External integrations remain fake/unimplemented until their explicit phases; no provider credentials are required now.
