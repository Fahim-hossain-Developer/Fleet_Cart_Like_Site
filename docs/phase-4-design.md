# Phase 4 implementation notes

These are implementation decisions, not claims that the code exists yet. Read phase-4-plan.md and the preserved Phase 3 contracts.

## Payment boundary

Use typed provider interfaces/results, durable gateway attempts/refunds/events and integer amounts. A provider response is normalized into status, currency, minor-unit amount, merchant verification, our attempt reference and provider transaction reference. Only server-verified exact matches can credit an order. Account-scoped authenticated Stripe/PayPal retrieval plus our reference/currency/amount establishes merchant ownership; other providers must also validate their merchant fields where documented. Never use a browser status as payment proof.

Create an attempt under the order lock, then commit before network access. An atomic created→initializing claim prevents two requests from initiating the same attempt twice. One unresolved attempt per order prevents concurrent payments against the same balance. Unknown timeouts/crashes remain unknown for reconciliation; do not automatically reissue non-idempotent provider mutations. Use provider-supported idempotency keys only where officially documented. Verification also needs a lease because bKash execution is a mutation; query after an ambiguous execute rather than blindly executing again.

While a payment is unresolved, block manual payment, cancellation, quantity edits and courier booking. Do not silently expire an order/stock merely on a browser cancellation. A final verified failure on an unpaid order may fail/release it once. A real verified payment must still be recorded if stock is unavailable: flag/hold fulfillment instead of rolling money back out of the ledger. If verification discovers an unexpected amount, currency, invalid order state or overpayment, retain a redacted verified-received record for staff review/refund; never silently apply an excess or discard evidence of real funds.

Share internal receipt/refund recording rules with Phase 3 rather than impersonating staff in jobs. Keep user-facing staff authorization at the manual action boundary and validate provider authority at the verified action boundary. Order payment keys/provider references and event IDs need database uniqueness. Existing order_payments.admin_id will become nullable for verified system receipts; manual receipts still require an authorized actor. Refunds reserve capacity under the order lock before calling a provider; manual refunds must not race an unresolved provider refund. Confirm receipt/restock only after verified provider refund success. Preserve the Phase 3 gift/RMA caps and per-item release counters.

## Callback/session boundary

Register provider return/webhook endpoints under a separate stateless API middleware route group, e.g. routes/integrations.php. Cross-site POST returns must not create a fresh Laravel session cookie and overwrite a guest's existing receipt session (SameSite=Lax). Return URLs bind a signed/opaque attempt nonce; documented appended bKash/PayPal query keys may be ignored for URL-signature calculation, but never trusted for amounts/status. Verify through the provider, then redirect to a normal web result page where the original cookie is restored on GET. Webhooks require documented authenticity and durable deduplication before processing. Unknown signature protocols remain disabled/TODO(api-docs).

Customer initiate/status/fake actions require actual order ownership or the existing placed_orders receipt session; no phone-only payment lookup. Fake simulation is explicit local/test UI with CSRF, a signed attempt URL and ownership, and cannot operate in production. Store real provider redirect URLs privately and validate their HTTPS host before redirecting. Never serialize whole attempts/configuration into the storefront.

## Couriers/COD

Use durable booking records with an immutable merchant reference, selected item quantities and expected COD. Create/claim before network calls. Do not blindly retry an uncertain booking; reconcile using an official lookup/merchant reference where supported. One API booking per order is sufficient for this phase; existing manual partial shipments remain supported. Booking starts only with validated address/coverage, remaining shipment quantities, required COD advance, no unresolved payment, and secured stock. Finalize an actual shipment only after accepted provider evidence. Do not invent tracking numbers, webhook headers or collection data.

COD advance is an order snapshot (off/fixed/percentage/delivery amount, bounded by total). Fulfillment/courier dispatch requires the net received advance. Track expected COD, nullable provider-reported collected amount and actual remittance separately. Delivered status alone is not evidence of cash collection/remittance. Manual collection/remittance receipts require finance permission, amount bounds, a real reference and replay keys. Reports must clearly distinguish unknown, reported and manually reconciled values.

## Credentials/UI

Keep credentials in the existing encrypted settings vault, not public JSON. Provider configuration stores only allowlisted non-secret options (enabled, mode, limits, etc.). Mask existing credentials and use blank replace inputs; clear submitted secret state before a Livewire response so stored plaintext never reappears in browser state. Never log raw HTTP exceptions/request URLs/responses; payment APIs may place credentials in query strings. Normalize transport failures to safe driver/error codes without the original exception as a logged cause. Avoid retries of mutating HTTP calls.

Default live providers disabled, sandbox mode selected. Explicit local fake gateway/courier and log SMS support full no-credential testing; do not disguise a fake result as a real provider. PayPal does not support the current BDT base currency: hide it from BDT checkout while testing/documenting the actual adapter with supported USD/EUR/GBP amounts; never relabel BDT as USD or invent a conversion.

## OTP/admin

Preserve Phase 3 email codes unchanged where possible. Separate phone/admin challenges avoid purpose collisions. All codes: hashed six digits, ten minutes, max five attempts, atomic single use, send limits per identity/IP, generic responses and private encrypted delivery outboxes cleared after transport acceptance. Log SMS is local/testing only for phone verification; it is not a real delivery claim.

Phone ownership can link unowned guest orders only after a consumed SMS code. Important: an unverified phone attached to an already email-verified account cannot claim that account by SMS login alone; require its authenticated owner to verify/add the phone or a support dispute process. This prevents somebody pre-entering another person's phone from compromising a verified email account. First phone-only verification revokes pre-verification passwords/sessions/setup links as Phase 3 email verification does. Account phone verification binds the challenge to that authenticated user; never transfer somebody else's established phone account automatically.

Admin email OTP is opt-in by role (default empty), with five attempts/ten-minute expiry and a pending password-authenticated session. Custom Filament login must not leave an authenticated/remembered staff session before the second factor. Panel middleware rechecks required roles and the verified admin ID/expiry on every request, including remembered logins. Keep customer and admin guards independent (customer blocking must not prevent the administrator's separate OTP route). TOTP is Phase 6, not a pretend option now.

## Evidence

Add actual replay/concurrency tests on disposable MySQL, not SQLite lock claims. Preserve all Phase 3 tests. Use HTTP fakes for documented wire contracts/failed verification/timeouts; label them adapter contract tests. Test fake checkout success/failure/forged redirect, COD advance gates, duplicate/out-of-order callbacks, unknown refund/booking recovery and phone/admin OTP bypass/races. Browser and secret-leak checks must cover the new pages. Run Vite builds and HTTP/database suites sequentially. No live integrations are verified without real evidence/credentials.
