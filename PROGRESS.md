# Progress

## Current scope
**Phase 4 — Payments & Couriers complete within the user-approved scope. Stop before Phase 5.** Branch: `phase-4-payments-couriers`; baseline Phase 3 `2bb083b`. Missing Nagad/Steadfast/Pathao/RedX merchant protocols are explicitly approved for later setup, with disabled private configuration/documentation fields now available. No commercial provider is claimed live-certified.

Read PROJECT_SPEC.md fully, ADDON_01_NEW_FEATURES.md fully, DECISIONS.md, docs/addon-phase-map.md, docs/order-management.md and docs/integrations.md. Phase 3 evidence is archived in docs/history/phase-3-progress.md.

## Delivered
- Durable, bounded payment/refund attempts and unique provider events/refunds; transaction/lease claims before HTTP; exact merchant/currency/amount verification; stateless authenticated callbacks and safe reconciliation of uncertain results. Existing stock/RMA/ownership rules remain shared.
- Stripe, PayPal, SSLCommerz, bKash V2 and aamarPay adapters against official documentation, plus an explicitly local fake gateway. Unsupported currencies/protocols fail closed. bKash execute is attempted at most once; SSLCommerz requires server validation after lookup.
- Checkout/payment/status screens, COD advance snapshots, finance reconciliation/refund controls, private encrypted environment-specific credentials and masked replace fields that clear after either success or rejection. Pending providers accept future documentation/private JSON without activation.
- Fake/manual courier bookings, single-shipment replay protection, unknown-booking reconciliation, separate bounded collection/remittance receipts and aggregate/order COD reports. Unknown collection/fees remain unknown. Delivered status never creates money.
- Queued encrypted SMS outbox with local log/Twilio/custom HTTPS contracts, test-send/status screens, rate-limited single-use phone login/verification, verified guest-order linking and pre-account takeover defenses.
- Opt-in per-role admin email OTP with no authenticated/remembered staff session before code consumption, password/email binding, expiry, retry limits and panel/direct-endpoint enforcement. Customer/admin guards remain independent.
- English/Bangla UI and explicit sandbox labels; test classification cannot be removed by later live-mode receipts, and production fulfillment/accounting/downloads reject test orders.

## Verification
- `scripts/cloud composer ci` passed: Pint (346 files), Larastan level 6 (229 source files), **147 SQLite tests / 879 assertions**, **16 MySQL-only tests explicitly skipped**, and Vite production build.
- Disposable MySQL 8 full migration/seed/Pest run passed **161 tests / 941 assertions**, including 16 concurrent-worker tests. A final sandbox-receipt persistence regression subsequently passed on MySQL separately: **1 test / 3 assertions**. The final independent-guard regression is included in the SQLite CI run. Do not sum these as one full-suite run.
- New MySQL workers verify one unresolved attempt, single credit, refund reservation, booking replay, COD collection caps and single-use phone/admin codes. Existing last-unit/coupon/checkout/order races remain green. Migration rollback exposed and fixed an InnoDB foreign-key/index dependency without disabling constraints.
- Adapter HTTP-fake tests cover initiation/query/signatures, amount mismatch, forged returns/events, timeout/at-most-once execution, original-payment refund binding, unknown refund recovery and credential/error redaction. They do not establish live merchant operation.
- Chromium passed actual local SMS-code account login → checkout → COD advance → fake payment → admin password/email code → pending-provider configuration → packed order → fake courier → delivered transition → separate collection/remittance report updates. No browser script errors. Screens fit 360/800/1440px; forced RTL and inspected Bangla payment screenshots passed.
- Locked Composer audit reported no security advisories; npm audit reported zero vulnerabilities.
- Public payment/OTP route credential crawler and built-JavaScript sentinel scan passed. All six scheduler tasks ran successfully. Original settings were restored, temporary reviewer disabled/credentials removed, browser sessions deleted, and disposable MySQL service/network/password removed. Auditable sandbox order/receipts remain explicitly labeled test.

## Environment and external limits
The application keeps its existing local data/key/admin credentials. PHP 8.3 runs through `scripts/cloud` in `commerce-php83`; Node 24, SQLite, file cache, sync queue and private log mail/SMS are configured. Existing application/scheduler processes may run now but must restart after restoration. `scripts/cloud-bootstrap` and `composer setup` remain the reusable install flow; do not reset data for tests. Pango/Fontconfig are required for Bangla PDFs.

No merchant/SMS credentials are present. Real provider sandbox acceptance, commercial courier APIs, actual SMS/SMTP deliverability, production deployment, fresh published-task restoration, Lighthouse targets and live refunds remain unverified. See docs/integrations.md for exact setup, unsupported callback/refund protocols, reconciliation limits and source links.

The final pushed revision and startup instructions are saved in the cloud environment draft after publication checks. Draft persistence is separate from publishing the environment; review/save the environment settings and use the product's Publish action. Phase 5 requires a new explicit continuation.
