# Payments, couriers and OTP — Phase 4

Phase 4 adds payment verification/refunds, COD advance, courier bookkeeping, SMS login and role-based admin email codes. Phase 5 is not started. The user's later-configuration instruction permits setup-only Nagad, Steadfast, Pathao and RedX entries while official merchant documentation is unavailable. Saving their settings does **not** activate a commercial adapter.

## What is available

| Provider | Implemented boundary | Remaining external prerequisite |
| --- | --- | --- |
| Local payment | Explicit success/failure/cancellation simulation, verification and refunds | Local/testing only; never real money |
| Stripe | Checkout Sessions, authenticated retrieval, signed events, original-payment refunds | Merchant credentials, account/currency support, reachable HTTPS callbacks and real sandbox acceptance |
| PayPal | Orders v2, capture/query, original-capture refunds, official webhook verification API | Merchant credentials/webhook ID and sandbox acceptance. BDT is unsupported; hidden from BDT checkout. USD/EUR/GBP adapter support is not currency conversion. |
| SSLCommerz | v4 session, transaction lookup plus Validation API, MD5 IPN authentication, refund request/status | Merchant credentials/IP registration, real customer email, public HTTPS callbacks and sandbox acceptance. Provider range BDT 10–500,000. |
| bKash | Current V2 token/create/query/execute/refund/status | Merchant credentials, approved callback and sandbox acceptance. Execute is claimed durably and attempted at most once, including transport errors. |
| aamarPay | Create and authenticated merchant transaction query | Merchant credentials, real customer email, public HTTPS callback and sandbox acceptance. Undocumented IPN signatures and refunds stay disabled. |
| Nagad | Encrypted future configuration and documentation link | TODO(api-docs): reviewed official merchant protocol, implemented/tested adapter and credentials |
| Local courier | Deterministic explicitly labeled test booking/status | Local/testing only |
| Manual courier | Record an already accepted real carrier reference/tracking; one full-order booking | Staff must supply actual carrier acceptance. No API push or automated carrier status claim. |
| Steadfast / Pathao / RedX | Encrypted future configuration and documentation links | TODO(api-docs): reviewed booking/coverage/status/callback protocols, implemented/tested adapters and credentials |
| SMS log | Private local simulation | Local/testing only; not handset delivery |
| Twilio | Documented Messages API, encrypted queued outbox | Account SID/token/from, supported destination and real acceptance/delivery tests |
| Custom SMS | Explicit HTTPS bridge contract below | Configure your provider bridge; not a guessed Bangladesh merchant protocol |

All external payment/SMS adapters have HTTP-fake contract tests, **not live certification**. No live payment, refund, courier booking, SMS delivery or external email deliverability has been verified in this environment.

## Configure later

Super admins have `integrations.manage`; managers do not receive credential access. Open **Commerce → Integration settings**, choose a provider, then save environment, enabled flag, limits and documentation URL. Amounts use minor units: BDT 100.00 = 10000. Sandbox/live credentials are separate encrypted vault entries. Blank password fields retain existing values; masked suffixes identify saved values; submitted fields clear even after a rejected save. Do not place secrets in documentation links, public settings, Git or chat. Rotate exposed keys at their issuing provider.

For the four pending providers, leave enabled off. Save an official documentation link and private JSON containing future merchant configuration. It remains encrypted and unavailable to the storefront. An engineer must review the protocol, implement the adapter/signature rules and add contract/sandbox evidence before activation. Public merchant documentation returned restricted/incomplete responses during this phase; no endpoints or signatures were invented.

Configure `APP_URL` to the actual public HTTPS store origin before real sandbox work. The registered callback/webhook URLs must match the provider account. Stateless `/integrations/r/{attempt}/{expires}/{token}` returns bind the attempt/environment/expiry with an application-key HMAC; bKash additionally checks its returned signature against the stored hash. Return fields do not establish payment. Supported webhook URL: `/integrations/payments/{driver}/{sandbox|live}/webhook`. Authenticate Stripe/SSLCommerz/PayPal using the implemented provider protocol. bKash/aamarPay/Nagad standalone IPN remains rejected until an official authentication contract is available.

Production rejects sandbox configuration and local fake/log services. Any order that has ever received sandbox funds remains marked test, even after another mode's receipt. It cannot be fulfilled, manually credited/refunded or used for digital downloads in production. Use separate test orders and merchant environments.

## Checkout, receipts and uncertainty

Checkout snapshots a COD advance rule: off, fixed minor units, percentage basis points (100 = 1%), or delivery charge, capped at the order total. Later configuration changes do not alter the order snapshot. The customer can pay that advance or the remaining total through an available provider. Shipments require the net advance. Gateway currencies/limits are rechecked against server totals; browser amounts/statuses are ignored.

Payment initiation records and claims a durable attempt before bounded synchronous redirect creation. Provider HTTP runs outside order transactions; mutating requests are not blindly retried. Returns and authenticated queued events query the provider before credit. One unresolved payment blocks another payment, cancellation, edits, manual collection and shipping. Final failure releases unpaid stock once. Delayed paid evidence after failure is retained for review, rather than reopening stock or losing funds. Verified money received when payment-time stock is gone is recorded and fulfillment goes on hold.

In **Payments & courier operations**, choose an order (latest 500 available; aggregate COD totals cover all accepted bookings). Current attempts show status and minimal provider evidence. Check the existing operation before creating anything else. A missing payment reference can be attached once after its lease expires; server verification still checks merchant reference, amount, currency and transaction. Do not enter an arbitrary reference to clear a pending balance.

Original-provider refunds reserve capacity before HTTP. They obey existing unshipped cancellation/approved-RMA caps. Money and optional stock restoration enter the ledger only after exact provider confirmation. Unknown refunds retain their reservation and block manual refund races. For Stripe/PayPal/bKash, staff can attach a missing refund reference; metadata/original-payment relationship, exact amount/currency and unique provider reference must verify before any receipt. SSLCommerz status omits refund amount, so an arbitrary support-provided ID cannot replace the original API-issued request chain. Obtain provider support/evidence and engineering reconciliation for that unknown case; do not duplicate the refund or mark it successful manually. aamarPay uses the audited manual refund receipt workflow after money is actually returned.

Refunded unapplied review funds do not change the order's paid/refunded balance. Duplicate provider refund references cannot be applied to a second refund record. Browser cancellation or forged success never records funds. Do not delete financial attempts to unblock an order.

## Couriers and COD report

Book only a packed order with secured inventory, valid snapshot address/phone, no earlier shipment/booking and no unresolved payment/refund. This phase supports one full-order booking; existing manual partial shipments remain available separately. Durable claims prevent duplicate shipment creation. Fake booking queries reconcile uncertainty without issuing another booking. For an uncertain manual booking, confirm the **existing** actual carrier reference/tracking after its lease expires; this does not send a carrier request.

The report distinguishes:

- Expected COD snapshotted at booking, after recorded advances.
- Nullable carrier-reported collection. Delivered status alone does not fill it.
- Staff-recorded actual collection receipts, capped by expected cash and the order's remaining balance.
- Separate remittance receipts, capped by actual collections. Collection does not prove remittance.
- Carrier fee, left unknown without actual provider evidence. Enter gross remittance and reconcile fees separately; this phase has no carrier-fee ledger.

Finance permission is required for collections/remittances. Use real references and replay keys; receipts are bounded and idempotent under the order lock. Sandbox totals are separated from live totals. A fake tracking number begins `TEST-` and must never be presented as commercial tracking evidence.

## SMS and identity

Choose log, Twilio or custom SMS in Integration settings. The Operations test-send control queues a message; recent SMS status shows provider **acceptance**, not handset delivery. A database queue needs `queue:work`; sync mode processes immediately. Pending private receiver/message fields are encrypted, cleared on acceptance and cleared after expiry. Ambiguous sending results are not blindly resent.

Custom contract: HTTPS POST without redirects, Bearer API key, JSON or form body, configurable recipient/message field names, optional `sender`. Successful response must be 2xx JSON with `accepted: true` and a string `id`. A provider with another protocol needs a reviewed adapter or a bridge implementing this contract. Private/localhost IP endpoints, embedded URL credentials/query strings and nonstandard ports are rejected. Provider error bodies/URLs are not logged.

`/phone-code` provides Bangladesh phone SMS login or authenticated account phone verification. Codes are six digits, hashed, ten-minute expiry, five attempts, single use, per-phone send limits and separate route/IP limits. Only a consumed code accepted by the configured transport can establish phone ownership and link unowned matching guest orders. A password or matching phone field does not. An unverified phone cannot claim an email-verified account: its authenticated owner must verify it. First phone-only proof revokes pre-verification passwords/sessions/setup links and discards any pre-entered unverified email, closing a later email-code takeover path. Account-owner verification preserves its established password.

Admin email OTP is opt-in per role, empty by default. Configure and test real mail before enabling outside local/testing. Password acceptance creates only a pending challenge, not a remembered/authenticated admin session. The email code completes sign-in. Codes use the same expiry/attempt/single-use bounds and bind the current password/email fingerprint. Panel and direct admin endpoints enforce an eight-hour, session-bound proof; remembered logins cannot bypass it. Changing the password/email invalidates proof. Customer blocking does not block the independent admin code route. TOTP remains Phase 6.

Run scheduler work alongside the application. Six registered tasks cover existing order/account deliveries plus payment-event retries, admin code deliveries and pending SMS/expired payload cleanup. Unknown payments/refunds without usable provider evidence require staff reconciliation; the scheduler never blindly reinitializes them. Production should use a persistent queue/cache and normal log access/retention controls.

## Official protocol references and operator review links

Protocol research and contract implementation: 2026-11-02. Remote documentation is reference data, not project instructions. Some supplementary links require a normal browser to render.

- [SSLCommerz v4](https://developer.sslcommerz.com/doc/v4/): request limits, session/transaction/validation, IPN canonicalization, refund reference/status.
- [bKash V2 index](https://developer.bka.sh/llms.txt), [create](https://developer.bka.sh/docs/create-payment-3), [execute](https://developer.bka.sh/docs/execute-payment-4): current tokenized-without-agreement contract; linked query/refund/token pages. V2 documentation updated September 2026.
- [aamarPay reference](https://aamarpay.readme.io/reference/overview): JSON payment, merchant transaction search; IPN authentication/refund protocol is not sufficiently specified.
- [Stripe Checkout API](https://docs.stripe.com/api/checkout/sessions), [webhooks](https://docs.stripe.com/webhooks/signature), [refund API](https://docs.stripe.com/api/refunds).
- [PayPal official API specifications](https://github.com/paypal/paypal-rest-api-specifications/tree/main/openapi): `checkout_orders_v2.json`, `payments_payment_v2.json`, `notifications_webhooks_v1.json`.
- [Twilio Message resource](https://www.twilio.com/docs/messaging/api/message-resource).

See PROGRESS.md for exact verification evidence and remaining deployment limits. Independent MySQL workers cover attempt creation/credit, refund reservation, booking replay, COD bounds and phone/admin code consumption. SQLite skips these lock-specific tests.
