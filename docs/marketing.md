# Content and marketing operations

Phase 5 adds original CMS and marketing tools. Theme variants, landing pages and demo packs remain Phase 5B; email template editing and full settings/reports remain Phase 6.

## Content
Staff with `content.manage` edit pages, blog categories/posts, menus, sliders/slides, scheduled banners, FAQs, popups and ordered home sections. Draft, inactive and out-of-window content stays private. Blog posts also require a published category. Signed staff previews require authentication/permission and are noindex. Legal starter pages remain drafts; publish reviewed store policies yourself. Starter seeding runs once and preserves later deletions.

Home blocks support the 21 types listed in `SectionCatalog::TYPES`. Links accept relative paths or HTTPS; rich text is sanitized. Media must use the public content upload paths, with original or licensed assets supplied by the owner. Videos accept vetted YouTube/Vimeo embeds. Testimonials/comparison claims are authored content; the store must supply truthful material. Purchase notifications use actual eligible orders only, without names/phones/order numbers. Shop/blog banner positions are wired to their respective sidebars.

## Promotions and customer content
Flash campaigns have active time windows and per-SKU promotional quotas. Listings, variants, cart and checkout use the same effective prices. Allocation locks serialize the last unit; cancellation/verified unpaid failure releases it once. Flash order quantities are immutable: cancel/recreate for changes. The product page shows actual promotional units and expiry, with a refresh notice after expiry. Best-seller badges rank recent fulfilled, fully received, non-refunded live orders; empty history creates no badge. The rank caches for one minute.

Automatic rules apply in priority order before coupon calculation, with stacking and original order evidence. Genuine free orders have zero funds received, without invented receipts. Public offers show only explicitly public coupons and eligible flash products. A customer may separately save a known private code for an expiry reminder; this does not publish it.

Marketing staff moderate reviews/questions, reply and configure purchase requirements, minimum text, photo limits, blocked wording and optional delivered-order review requests. Photos are bounded and reencoded without source metadata. Sandbox orders do not qualify as verified purchases. Product watches publish only real stock/price transitions. Customers control channel preferences and read state in Account → Notifications. Marketing email/SMS defaults off and requires verified contact. In-app marketing defaults on; all channels can be disabled.

## Newsletter and cart recovery
Newsletter uses double opt-in with encrypted contacts and payloads, expiring single-use confirmation and scoped unsubscribe links. It never verifies a customer account. Campaigns require an explicit queue action, freeze the prepared audience and recheck membership/generation at delivery. Pause prevents future sends; an already in-flight transport request cannot be recalled. `sent` means transport acceptance, not inbox delivery. Ambiguous sends need review instead of blind retry.

Cart Recovery settings default off. Checkout capture requires a separate explicit checkbox and selected channels; customers may still order without opting in or verifying a reminder contact. Guest email confirmation and SMS codes prove only that cart contact. They cannot sign in, attach accounts or claim orders. Authenticated matching verified contacts may be reused with explicit consent.

Reminders are prepared after 1, 24 and 72 idle hours, within seven days. Activity, withdrawal, generation changes and checkout conversion invalidate stale work. A late worker prepares only the latest due stage instead of sending a burst. Email/SMS are queued; WhatsApp becomes a staff-reviewed draft. Opening the draft never marks it delivered. SMS outboxes distinguish pending provider acceptance from accepted work. Existing local log transports are simulations and cannot establish production contact proof.

Restoration links contain random hashed capabilities and expire. GET shows a confirmation; CSRF-protected POST restores published product/variant quantities into the visitor's current cart at current prices. It does not expose the original cart token, private uploads or account/order details. Required custom choices must be selected again. Unavailable products are skipped. Replays cannot duplicate the same restoration, and placing a restored cart converts the original recovery generation.

## Floating controls and popups
Storefront Marketing configures per-button WhatsApp/Messenger/call/top, placement and public path include/exclude lists. Valid international phone or provider page IDs are required. Product WhatsApp drafts include the public name/URL; these are links, not automatic messages. Controls are offset above mobile purchase controls. Tawk.to accepts only property/widget identifiers and loads after the visitor's separate click; real widget placement/provider behavior still requires checking with the owner's actual configuration. Arbitrary injected scripts are not accepted here.

Published popups support delayed or desktop exit intent, path matching and local frequency caps, one eligible popup per page, Escape/focus trapping and safe links/content. Storage failures suppress the popup. Sensitive account/payment/verification/recovery paths show neither popups nor these controls.

## Social sign-in
Root-only Social Login settings hold encrypted, masked client IDs/secrets. Blank values retain existing credentials; disable a provider to stop new sign-ins. Register the displayed exact callback URL with Google/Facebook and use HTTPS in production. No buttons appear until enabled with both values. Real app review/configuration is still needed; development tests use fake provider responses.

Google uses authorization code, state, S256 PKCE and the official userinfo endpoint. Facebook verifies token app/subject/expiry before reading profile data; its email is not treated as verified. Provider tokens are never persisted. Existing email matches require the customer to sign in and explicitly link after fresh email/phone proof. Linking binds the customer/auth version into one-use expiring state. This avoids joining accounts solely because a provider returns the same email.

## SEO and feeds
`/sitemap.xml` links type/page sitemaps, with 1,000 records per page and English/Bangla alternate links. `?lang=en|bn` selects public page language without changing the customer's saved preference. `/robots.txt` excludes private commerce/auth paths; private HTML is noindex. Robots is not an authorization mechanism.

Root SEO settings supply translated defaults and structured-data toggle. Product/Offer/AggregateRating/Breadcrumb/Organization/FAQ schemas contain real public data and escaped JSON. Redirect management accepts validated relative/HTTPS targets and bounded, cycle-free chains; old page/post/product slugs resolve only to published destination content.

`/feeds/google.xml` and `/feeds/meta.xml` stream public products with real images, current base-currency prices and availability. Active variants have stable IDs/group IDs and links selecting that variant. These endpoints do not invent GTIN/MPN identifiers; merchant approval and additional account/product policy fields may still be required by the advertising provider.

## Consent-based tracking
Root Tracking settings default off. Public IDs are validated separately from the encrypted Meta server token. Configure GA4 or GTM carefully: placing duplicate Google tags in both may duplicate downstream analytics. Configure matching browser/server Meta Pixel IDs. Live provider acceptance/attribution has not been certified without owner credentials.

Visitors choose optional analytics/advertising separately from newsletter and cart reminders. Decline/withdrawal prevents queued delivery, erases matching information and reloads open tabs through a storage notification. Account, auth, checkout, payment callbacks, token confirmations and recovery pages never load trackers. Browser Purchase waits until the next eligible public page; browser delivery is best effort. Blockers/network failures may prevent it. Server Meta events may still be delivered under active consent.

The server creates ViewContent, AddToCart and InitiateCheckout from actual application actions. Purchase requires fully received positive live payment, never an unpaid COD placement, sandbox receipt or browser success parameter. Browser endpoints cannot submit authority to create purchases. Browser and Meta CAPI share opaque event IDs; order numbers, names, email/phone/address, IP/user agent and server tokens never enter browser event payloads. Server matching data is encrypted and purpose-bound to consent; only IP/user agent are sent to Meta, so match quality may be lower than contact-based implementations.

CAPI uses Graph v26.0, stable event-name/event-ID retries (maximum three), redacted failures and accepted/needs-review status. `accepted` means Meta acknowledged the event, not attribution or ad delivery. The scheduled reconciliation can recover missed order events and interrupted event claims. No actual provider events were sent from this development session.

## Workers
Run a queue worker and scheduler in production. Tasks cover feedback, watches, notices, review/coupon reminders, newsletter, recovery, tracking retries and purchase reconciliation. Use production SMTP/SMS settings, HTTPS, private logs and the existing deployment/backup guidance. Tests need 512 MB PHP CLI memory, configured in `phpunit.xml`. Never run migration-reset/concurrency tests on the application database.
