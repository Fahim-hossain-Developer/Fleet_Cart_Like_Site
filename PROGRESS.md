# Progress

## Current scope
**Phase 3 — Orders & Customers implemented.** Phase 4 has not started. Baseline: pushed Phase 2 `a3603ef`; branch `phase-3-orders-customers`. Phase 2 history is preserved in docs/history/phase-2-progress.md. The current user explicitly authorized this phase and the existing push authorization persists.

Read PROJECT_SPEC.md, ADDON_01_NEW_FEATURES.md, DECISIONS.md, docs/addon-phase-map.md and docs/order-management.md before continuing. All later addon work retains its Section 12 mapping.

## Delivered
- Permission-scoped staff order list, search/status/payment/customer filters, detail, public/internal timeline/notes, quantity edits before payment/shipment, resend and private proof access. Document/management action menus fit the header.
- Guarded lifecycle, independent payment/fulfillment states, UUID-idempotent manual payment/refund receipts, partial shipment bounds and carrier/tracking records. No direct shipped-status toggle fabricates a shipment.
- Configurable snapshotted stock timing: placement, confirmation or first payment; availability rechecks, shipping-time deduction safety, per-item deduction/restock counters and exactly-once cancellation release. Ordered variants cannot be deleted out of their history.
- Account return requests, approval/rejection, manual partial/full refunds and bounded optional restocking. Full unshipped refunds include delivery/gifts; fulfilled returns refund approved merchandise and retain delivery/gift/gift-tax fees. Refund receipts do not initiate external transfers.
- Staff phone/COD order creation through the real checkout engine, explicit customer selection, server prices/shipping and stable-cart idempotency under MySQL repeatable-read.
- Real risk warnings: phone blacklist, repeat cancellations, same keyed-IP burst and configurable high-value COD. Customer notes/tags/blocking, detail/history and bounded safe customer/order CSV exports.
- Customer registration, email/phone password login, reset/logout/profile/password changes and blocked-session enforcement. Separate customer/admin guards remain in use.
- Addon C3 customer email login/verification: hashed six-digit challenges, ten-minute expiry, five attempts, per-email/IP send limits, atomic single use, generic send responses and verified-email guest-order linking.
- First verification revokes pre-verification passwords/sessions and permits setting a new password in the verified session. Authentication versions also invalidate stale sessions on password changes/reset; private setup tokens are revoked when email ownership is established.
- Customer address CRUD/defaults with actual location hierarchy and saved-address checkout selection; submission stays disabled until selected address hydration finishes.
- Owned order history/detail, current-price transactional reorder, pending cancellation and throttled number+phone tracking without exposing addresses or staff notes.
- Signed owner/permission-protected invoice, packing-slip and shipping-label PDFs, gift hide-price behavior and bundled OFL Noto Sans Bengali static fonts. Pango/HarfBuzz shapes Bangla locally for Dompdf.
- Paid digital-download snapshots, payment-time expiry, signed ownership checks and atomic usage limits. Refund/cancellation revokes access; download definitions can be retired without deleting entitlement snapshots.
- Customer JSON data export and idempotent erasure request with staff visibility. Erasure is reviewed through the retention/support process, never automatic accounting-record deletion.
- Durable public order update delivery/retries, after-commit OrderChanged events, private-note exclusions, model policies/factories, audits and English/Bangla strings.

## Validation — 2026-11-02
- `scripts/cloud composer ci`: passed — Pint, Larastan level 6, **107 SQLite tests / 595 assertions**, Vite production build. **Nine MySQL-only race tests explicitly skipped on SQLite**.
- Isolated MySQL 8: migrations/rollback/seeding and **115 tests / 631 assertions passed**. Independent PHP workers verify last-unit checkout, coupon limits, duplicate checkout, cancellation restock, payment balance, refund replay, shipment quantity, OTP consumption and duplicate manual-order submission.
- The final included-tax presentation regression additionally passed on MySQL (**1 focused test / 6 assertions**); final source has 116 tests including the nine MySQL races.
- Chromium confirmed real local email-code mail, customer password/address/account flow, saved-address checkout, gift PDFs, pending cancellation/reorder, staff states/shipment and number+phone tracking. Gift PDF was rasterized and inspected: shaped Bangla and no monetary values.
- Final repeated Chromium checkout/cancel/reorder/staff shipment/tracking flow passed after the route limiter fix. Admin/customer layouts passed at 360/800/1440px, including action menus, forced RTL and Bangla mobile/PDF output. Gift and Bangla invoices were rendered and visually inspected.
- Repeat `composer setup` passed and preserved the exact environment file, order count and staff credential hashes. Scheduler tasks are registered and were exercised with schedule:run.
- `git diff --check`: clean. Development databases, setup logs, credentials, uploads/proofs, font/text caches and browser sessions remain ignored/private.

## Diagnosed and corrected
- Repeated browser checkout exposed shared Laravel numeric-throttle buckets across routes. Each public route now has an explicit prefix, with regressions proving quote/location traffic cannot consume submission capacity while password throttling still applies.
- MySQL repeatable-read caused concurrent firstOrCreate recovery to miss a just-committed manual cart. A stable cart upsert and current locking reads now serialize replay; the actual worker race passes.
- OTP failure counters commit even when verification fails; first-verification credential/session revocation prevents pre-account takeover.
- SQLite rollback explicitly removes the IP-hash index before dropping its column. All new migration rollbacks are exercised by the disposable database suites.
- Order details/invoices display added tax separately from the included-tax note so their totals reconcile; a regression covers inclusive pricing on SQLite and MySQL.
- Pango needs a UTF-8 process locale and a writable private font cache. Generated Bangla images are trimmed to avoid excessive inline whitespace.
- Address hydration and the quote run asynchronously; checkout now waits for the address to finish and flush its DOM values. Browser automation also waits for a valid form/finished quote.
- Staff actions are grouped into menus to avoid a long row overflowing the admin page. Staff totals use explicit grid layout inside Filament's independently compiled styles.

## Run / reuse
Repository: `/workspace/Fleet_Cart_Like_Site`.
- Upgrade from Phase 2: `scripts/cloud-bootstrap` rebuilds PHP with Pango/Fontconfig and preserves existing app data/key/credentials through setup. Native Linux: install pango1.0-tools and fontconfig alongside PHP/GD; see docs/order-management.md.
- Refresh: `scripts/cloud composer setup`; start `scripts/cloud serve` if port 8000 is not already owned by this app.
- Scheduler: `scripts/cloud php artisan schedule:work`, or server cron schedule:run each minute. Sync queue sends immediately; database queue also needs queue:work.
- Quality: `scripts/cloud composer ci`. Run MySQL tests only with a disposable database; integration tests migrate/rollback schema. Keep builds and browser/MySQL tests sequential against stable assets.
- Existing instance: SQLite, file cache, sync queue, log mail/SMS; example BD delivery rates only. Private mail logs contain local OTP/setup/reset links and must not be published.
- Customer routes: /register, /login, /email-code, /account and /track-order. Staff: /admin/orders, /admin/customers and Phone Blacklists. There is no published default customer password.

## Boundaries / later phases
- Phase 4: actual gateways/original-gateway refunds, COD advance, courier bookings/webhooks, live SMS/phone OTP and role-configured admin email OTP. TOTP and broader security/settings operations remain Phase 6/7. No external payment/refund/courier/SMS operation was tested or claimed.
- Phase 5: styled/social auth (B8), customer notification center/reviews and marketing. Phase 6: FX providers, large queued reports/exports, customer/order import tooling, arbitrary custom order status names, advanced Kanban/bulk order UX and operational cleanup. No full addon acceptance is claimed.
- Current address/phone workflows target Bangladesh. Optional checkout-start stock holds are not enabled; confirmation/payment timing can reject stock that sold in the meantime. Coupon redemptions are not credited back automatically on cancellation.
- Staff item edits are quantity-only, preserve original absolute discount/shipping/gift amounts, and do not requalify coupons. Legacy taxed orders without exact tax-rate snapshots are protected from recalculation; use replacement orders.
- One accepted RMA may include multiple lines/quantities; after-shipment refund caps exclude delivery/gift charges. Manual records never transfer money. There is no wallet or store credit.
- Bangla PDF runs are embedded raster images, not selectable/searchable text. Native/shared hosts must provide Pango/Fontconfig and permit Process execution; restricted cPanel support is not claimed verified. Private download files must be retained when retiring product definitions.
- Erasure requests need a documented staff retention/review process; no irreversible automated deletion occurs. CSV export is bounded to 5,000 records; large queued export work remains Phase 6.
- Risk flags are warnings snapshotted from real data, not automatic fraud decisions. At-least-once mail may repeat after ambiguous transport failures. No live mail deliverability is claimed from a log transport.
- MariaDB, Windows, full Compose deployment, cPanel deployment, Lighthouse targets and restoration in a brand-new published environment remain unverified.

## Exact next step
After this phase is pushed and the saved environment draft is reviewed/saved/published, continue **Phase 4 — Payments & Couriers (BD)** only on the user's next authorization. Preserve order/stock/payment/identity invariants and the addon's phase map. Do not rebuild completed checkout/order/customer features.
