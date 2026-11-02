# Orders and customers (Phase 3)

## Run and upgrade

Run `composer setup` (managed cloud: `scripts/cloud composer setup`). Setup preserves the database, app key and existing staff credentials, applies migrations and refreshes role permissions. Rebuild the PHP image with `scripts/cloud-bootstrap` when upgrading from Phase 2: Bangla PDF rendering now requires `pango-view`, Fontconfig and GD. The Dockerfile installs these. Native Debian/Ubuntu hosts need `pango1.0-tools` and `fontconfig` alongside the PHP extensions already documented. No Node process is required at runtime.

Start `scripts/cloud serve` and `scripts/cloud php artisan schedule:work`; reuse an existing server/worker instead of starting duplicates. Log mail/SMS are private development transports. No payment, courier or live SMS provider is called in this phase.

## Customer screens

`/register`, `/login`, `/email-code`, `/forgot-password`, `/account`, `/account/orders`, `/account/addresses` and `/account/downloads` are working routes. Password login accepts email or normalized Bangladesh phone. Registration requires email; checkout-created phone-only accounts retain their private setup-link/password flow. SMS verification and social login remain in their assigned later phases.

Email codes contain six digits, last ten minutes, are hashed, allow five verification attempts and are consumed under a row lock. Send limits apply per normalized email and per IP. Unknown/blocked accounts receive the same send response. Only a consumed email challenge verifies email and links previously unowned guest orders with that email. Password login/reset, registration, matching phone and manual customer assignment do not verify identity or claim other guest orders.

On **first email verification**, passwords and remembered sessions selected before proof of ownership are revoked. Existing sessions carry an authentication version and are invalidated when it changes. The verified session has ten minutes to set a password without the previous password; otherwise use the email password-reset flow. This prevents pre-registration of somebody else's email from remaining a back door. Subsequent email-code logins preserve the verified account's password. Password changes/reset rotate authentication state. Blocking also terminates existing customer access on the next request.

Addresses validate division → district → upazila → optional area against stored locations. Default address changes lock the customer row. Address selection at checkout populates the normal form and still undergoes all server-side checkout validation. Historical order addresses never change when a saved address changes.

Customers see only their own orders, request cancellation only while pending, reorder using current catalog prices/options/availability, and request a return after delivery/completion. File options must be selected again rather than copying private uploads. Reorders are transactional: one invalid line leaves no partially added cart.

Tracking accepts order number plus normalized phone, is throttled and reveals only status, shipment tracking and public timeline events. It does not reveal address, email, item prices, proofs or staff notes.

Customers can download an ownership-scoped JSON data export and submit an idempotent account-erasure request. Staff review requests on customer details. Erasure is a support process subject to legal record retention; submitting a request does **not** automatically delete accounting/order data.

## Staff operations and permissions

Orders supports searching, status/payment/customer filters and a detail page with snapshots, public/internal timeline, payment/refund receipts, private payment proof, PDF links and actions. Default `orders.manage`: owner, manager and order processor. `payments.manage` and `refunds.manage`: owner and manager. Support can view customer details/history but cannot edit, process orders or export. Customer management/blocking/notes/tags and customer/order exports require their own permissions, assigned to owner/manager. Actions recheck authorization in domain services.

The default sequence is pending → confirmed → processing → packed → shipped → delivered → completed. Confirmation/processing/packed may be put on hold and reconfirmed; unshipped active orders may be cancelled. Failed/cancelled/refunded/returned orders cannot be reopened. A shipment action advances to shipped; there is no direct status toggle that fabricates a shipment. Delivery requires every unit to have shipped. Completion requires paid status. These guarded workflow states are fixed in Phase 3; arbitrary configurable status names are not supported.

Record received payment only after staff has checked the actual bank/mobile/COD receipt. Amounts are minor units (100 = 1 BDT); amounts cannot exceed the remaining balance. Request UUIDs are unique and replay safe. This records a manual receipt and never claims a gateway verification or initiates a charge. Payment, order and fulfillment statuses remain separate.

Shipments record manual carrier/tracking and selected line quantities. Their cumulative quantities cannot exceed ordered quantities. Partial shipments remain partial until all items ship; none of this books a courier. Courier adapters are Phase 4.

Before shipment or any payment, staff may edit **quantities** against immutable item price/tax snapshots. Delivery/gift charges and the original absolute discount are retained; the discount cannot exceed merchandise subtotal. No catalog repricing or coupon requalification is silently applied. Original quantity/subtotal ratios prevent rounding drift across repeated edits. Historical pre-Phase-3 taxed lines lack their exact tax-rate snapshot and are deliberately protected from recalculation; retain their invoice and create a replacement order. Product changes do not rewrite invoices. Ordered variants can be disabled, but cannot be deleted while order references exist.

Manual phone orders use the same cart validation, current prices, address/shipping rules, stock engine and idempotency protection as storefront checkout. They are COD and unpaid. Staff can explicitly select an existing active customer; simultaneous web-guard login cannot attach somebody else's account. Required custom options use storefront checkout in this phase. There is no arbitrary staff price override.

CSV exports stream the first 5,000 customers/orders, use a UTF-8 BOM, neutralize spreadsheet formulas, and omit credentials/private bearer values. Larger queued reporting exports and customer/order imports are not implemented here; Phase 6 owns reporting/operations. Historical catalog import/export remains Phase 1's separate contract.

## Inventory and refunds

Checkout Preferences selects placement (default), confirmation, or first received payment. The choice is snapshotted per order. Confirmation/payment policies are deferred deduction, **not** checkout holds: another order may consume stock before that trigger, so staff get an availability error rather than negative stock. Shipping always ensures deduction, including unpaid COD orders. No checkout-start reservation is enabled. Cancellation/failure restores only deducted units, once. An order/item lock plus deducted/restocked counters prevents duplicate releases. Backorders/unmanaged stock are not artificially restocked. The Phase 2 migration backfill accounts for its existing placement deductions.

For a paid unshipped cancellation/failure, staff may record partial/full manual refunds up to money received, including delivery/gift/tax on a full refund. Cancellation already restocks inventory; a refund must not restock it again.

After shipping, an account RMA must be approved before refunding. One RMA may cover selected quantities of multiple lines; rejected requests can be resubmitted. Refund cap is the approved returned merchandise's discounted, tax-inclusive line amount, prorated in integer minor units. Delivery, gift wrapping and gift tax are retained after fulfillment. Optional restocking is capped by both the approved quantities and the original stock deduction, including across multiple partial refunds. Refunds are UUID-idempotent and cannot exceed payments. Full unshipped refunds use order status refunded; completed merchandise returns use returned. A partial refund has its own payment status.

Refunds in this phase record **money already returned manually**. Original-gateway refunds are Phase 4; do not click a manual receipt expecting an external transfer. The interface explains this before submission.

Risk flags are factual warnings: blacklisted normalized phone, at least three historical cancellations, three orders from the same keyed IP hash within ten minutes, and configurable high-value COD. The blacklist is a review list, not an unverifiable identity ban. Flags are saved at placement and shown to staff. No fake fraud scoring or outside lookup is used.

## Documents, files and notifications

Invoices, packing slips and shipping labels use Dompdf, local assets and bundled OFL Noto Sans Bengali TTFs. Dompdf cannot reliably shape complex Bangla by itself. `PdfText` shapes Bangla runs locally through Pango/HarfBuzz using the bundled font, trims the resulting PNGs and embeds them into Dompdf. English remains selectable text; Bangla runs are rasterized, not searchable/selectable PDF text. Private generated text/font caches stay outside public storage. Remote assets, PDF PHP and JavaScript are disabled. Restricted shared hosting must permit this binary/Process execution; this is an explicit hosting prerequisite, not a claimed verified cPanel deployment. Font licenses and sources are under public/fonts/licenses.

Both customer and admin PDF URLs have short-lived signatures **and** ownership/permission checks. Signed URLs alone never grant another customer's access. Gift hide-price snapshots suppress all monetary values on invoice and packing documents; packing slips/labels otherwise never show prices. Purchaser account totals and private staff accounting remain available.

Digital download definitions are snapshotted per order line and survive definition deletion. Full verified staff payment enables entitlements, sets expiry from payment time, and provides signed owner-only links with atomic download limits. Missing files fail without consuming a download. Cancellation/failure or any refund revokes access. Keep purchased private files when retiring product definitions; no copy of the original file is fabricated.

Public order changes have durable delivery fields and retry jobs; internal notes never trigger customer mail. OrderChanged events dispatch after commit. Scheduler tasks retry unsent updates/confirmation/setup delivery, up to three attempts. Delivery is at-least-once under ambiguous transport failures. Notification errors do not undo a committed order change. Editable templates/channel preferences and operational cleanup are still assigned to later phases.

## Evidence

See PROGRESS.md for exact latest commands/results. SQLite intentionally skips MySQL row-lock races. Run integration tests only against a disposable database: they migrate/rollback schema. Never reset development data to run a suite. Phase 3 adds independent-worker tests for cancellation, payment balance, refund replay, shipment bounds, single-use email codes and manual-order replay, alongside the existing last-unit/coupon/checkout races.
