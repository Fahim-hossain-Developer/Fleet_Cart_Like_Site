# Progress

## Current scope
**Phase 2 — Cart & Checkout complete for this phase's scope.** Phase 3 has not started. Baseline: pushed Phase 1 commit `ab9769a`; current branch `phase-2-checkout`. The user authorized the next phase after publication. Phase 1 history is retained in docs/history/phase-1-progress.md.

Read PROJECT_SPEC.md, ADDON_01_NEW_FEATURES.md and docs/addon-phase-map.md before continuing. All later addon items remain in their mapped phases.

## Implemented
- Persistent guest carts using unguessable session tokens; authenticated cart lookup and guest merge; cart/item ownership policies; add/update/remove, save-for-later, mini-cart and cart page.
- Product/variant/option validation, private file options, aggregate stock checks across option lines, current server prices and readable option snapshots. Product form/Buy Now/quick view submit to the actual cart; required custom options use the full product page. Mobile purchase controls stay accessible.
- Fixed/percent/free-shipping coupons with date/minimum/cap/total/per-phone limits; coupon and inventory checks are re-run under transaction/row locks on placement.
- Integer PriceCalculator, exact coupon apportionment, line tax rounding, inclusive/exclusive tax snapshots and a reconciled checkout breakdown. No client totals are trusted.
- Licensed Bangladesh location snapshot: 8 divisions, 64 districts, 494 upazilas. Cascading selectors validate the actual hierarchy. Configured areas render as a dropdown; absent area data has an honest free-text fallback. Source/license retained in database/data.
- District shipping zones; flat/weight/pickup/free-threshold methods; no uncovered-district free-rate fallback. Tax classes/rates and product class/weight/gift inputs.
- One-page or three-step phone-first checkout, terms, note, COD, configured bank/manual-mobile methods with references/private proofs. Every such order starts unpaid; no payment verification is fabricated.
- Transactional order/item/address/option/currency/gift snapshots; inventory deduction on placement; collision-checked configurable order prefix; idempotency keys; session/customer-owned receipts; read-only admin order intake.
- Addon C1: gift toggle, fee overrides, message/recipient/hide-price snapshots and optional gift tax. Invoice/packing-slip/refund behavior is for Phase 3.
- Addon C2: optional/auto new account without auto-login, contact deduplication, single-use one-hour password setup. Matching contacts do not attach guest orders to existing accounts. Full customer login/identity verification remains Phase 3.
- Durable order/account setup delivery retries after commit. Setup validation token is hashed; pending delivery payload is encrypted and cleared after delivery. Mail outages do not fail a committed order response. Local mail/SMS use private logs, not claimed provider delivery.
- Manual currencies/rate history/ppm conversion, formatting/rounding/markup, cookies/session/authenticated profile fields and order rate snapshots. Base-currency invariants are enforced; automatic rate adapters remain Phase 6. Catalog price filters explicitly show base-currency units.
- Permission-scoped admin shipping/location/tax/currency/coupon/checkout screens, factories, audit records, schema migrations and English/Bangla strings.
- Credential-leak HTML coverage extended to cart, checkout and protected receipts.

## Validation — 2026-11-02
- `scripts/cloud composer ci`: **passed** — Pint, Larastan level 6, **74 SQLite tests / 342 assertions**, Vite production build. **3 MySQL-only concurrency tests explicitly skipped on SQLite**.
- Isolated MySQL 8: migrations/seeding and **77 tests / 358 assertions passed**, with independent PHP workers proving last-unit exclusion, one-use coupon atomicity and simultaneous duplicate-submission idempotency. These are real row-lock tests, not mocked concurrency.
- Chromium: mobile variant → cart → cascading address/shipping → gift → COD → protected unpaid receipt; both one-page and three-step flows; no browser/asset errors or horizontal overflow. Screenshots inspected. Default mode restored to one-page afterwards.
- `composer setup`: repeated successfully, preserving existing data/key/admin credentials; scheduler retry tasks executed successfully with `artisan schedule:run`.
- `git diff --check`: clean. Local credentials, uploaded files/proofs, databases, dependencies and runtime logs remain ignored.

## Diagnosed and corrected
- Database defaults were added to in-memory order state; SQLite rollback removes the phone unique index before the column.
- Local CLI config now uses a writable container directory; Tinker can perform supported local setup checks.
- Three-step component quoting/accessibility was verified in Chromium.
- Notification errors are isolated from committed orders using durable outboxes.
- Inclusive tax and optional gift fields retain their correct meaning in snapshots and totals.
- A validation run overlapped a Vite rebuild and briefly lost its manifest; the final quality/MySQL/browser checks were rerun sequentially against stable assets.

## Run / reuse
Directory: `/workspace/Fleet_Cart_Like_Site`.
- Install/refresh: `scripts/cloud-bootstrap` or `scripts/cloud composer setup`.
- Start: `scripts/cloud serve` (reuse an existing port-8000 app rather than starting duplicates).
- Retry scheduler: `scripts/cloud php artisan schedule:work`; production cron runs schedule:run each minute. Sync queue delivers immediately; database queue additionally needs queue:work.
- Quality: `scripts/cloud composer ci`.
- Native/Windows/Compose instructions are in README.md. The current instance uses SQLite, file cache, sync queue and log mail/SMS. Logs can contain local setup links and must stay private.
- Only run tests with disposable databases; integration tests migrate/rollback schema. MySQL test service used for this work is temporary and separate from development data.

## Limitations / later phases
- Checkout currently targets Bangladesh addresses/phone normalization. International country-specific address workflows require later settings/platform work.
- Local development delivery rates are examples (BDT 80/150), not courier quotes. Production seeding does not activate those sample rates. The bundled location snapshot may lag current boundaries; the admin can maintain it.
- Phase 3: full order status/payment verification/fulfillment, cancellation/restocking, invoices/packing slips, returns/refunds, customer login/accounts/address books and verified guest-order linking. Do not equate checkout account creation with verified identity.
- Inventory currently deducts on placement; optional checkout-start holds and alternate confirmation/payment-time stock policies are not enabled.
- Online gateways/couriers/live SMS/OTP remain Phase 4. No real external payment, courier booking or SMS provider operation was tested.
- Advanced coupon/cart rules remain Phase 5; automatic exchange rates remain Phase 6; full security scans/built-JS/future landing-page crawler coverage and production hardening remain Phase 7.
- Gift invoice/refund rules are specified, but those future workflows are not claimed implemented. Order/account emails are at-least-once under ambiguous transport failures.
- MariaDB, Windows, the full Compose stack, deployment/Lighthouse targets and restoration in a brand-new published task are not claimed verified.

## Exact next step
On “Continue with the next phase,” verify this baseline, then implement **Phase 3 — Orders & Customers** and its addon mapping. Do not rebuild the cart/checkout.
