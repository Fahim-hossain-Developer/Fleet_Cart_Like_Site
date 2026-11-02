# Phase 5 — Content & Marketing

User continuation on 2026-11-03 (Asia/Dhaka) authorizes Phase 5 only. Baseline: Phase 4 `85b52e2`. Work on the existing checkout; stop before Phase 5B.

## Delivery matrix
1. CMS: scheduled translated pages/blog/categories/tags, safe rich content and SEO, menus/tree validation, scheduled sliders/banners, ordered enabled home sections, contact/FAQ and matching blog sidebar widgets. Typed validated home blocks include the applicable addon Section 8 types; no landing templates or theme-variant system yet.
2. Commerce marketing: bounded flash-sale inventory/pricing integrated into listings/cart/locking checkout, ordered automatic cart discounts before coupons, real purchase reviews/photos/moderation/helpfulness/replies/Q&A and configurable review policies/requests. Wishlist and max-four comparison support guests/accounts. Finish factual flash/best-seller badges.
3. Lifecycle: consent-based abandoned-cart recovery and newsletter double opt-in/unsubscribe, safe frequency-controlled popups; notification center/read state and channel preferences for real supported events. Back-in-stock/price-drop watches may use actual transitions, never fabricated notifications.
4. Reach/UX: sitemap/schema/OG/canonical/redirects/robots/feeds; optional consented GA4/GTM/Meta/TikTok browser adapters and queued server Meta CAPI with durable event IDs/de-duplication and fake contract evidence when credentials are absent. Floating WhatsApp/Messenger/call/back-to-top/chat controls avoid sticky bars; honest social proof uses real non-test eligible orders and city only. Split auth pages, strength feedback, RTL and configured social auth only.
5. Final gate: meaningful ownership/secret-leak/XSS/discount/replay/concurrency tests, disposable MySQL, responsive/RTL/Bangla/browser review, audits/build/static checks, updated guides/addon map, small commits/push and saved exact-revision environment draft. No live tracking/auth/mail delivery claims without credentials and actual evidence.

## Invariants
No arbitrary customer URLs/scripts/HTML enter trusted output. Sanitize rich content; allow only supported link/media schemes and bounded typed block config. Draft/scheduled content must not leak through public routes/sitemaps/feeds. Marketing tokens are random/hashed, scoped and expiring; recovering a cart must not claim an account, order or private option file. Require explicit marketing consent before sending recovery/newsletter messages or loading tracking. Store tracking identifiers/tokens privately and never include customer address/contact in browser tracking or social proof. Do not send external messages from this development session.

Flash allocations and stock serialize under order/product locks; cancellation returns only the original allocated units once. Original snapshot totals, coupons, tax and refund math remain authoritative. Policies/factories and admin controls accompany new entities. Scheduling uses queues and durable deduplication, with honest local log/fake evidence.

## Delivered
All five matrix rows are implemented and validated within the limitations in PROGRESS.md and docs/marketing.md. Full SQLite CI, disposable MySQL/concurrency, provider contracts, credential crawler, audits and responsive/Bangla/RTL/browser evidence are recorded there. Do not start Phase 5B in this task.
