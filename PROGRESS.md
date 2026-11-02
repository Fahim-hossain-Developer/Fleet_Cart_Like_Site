# Progress

## Current scope
**Phase 5 — Content & Marketing in progress.** The user explicitly requested the next job after pushed Phase 4 `85b52e2`. Branch `phase-5-content-marketing`; stop before Phase 5B. Preserve all earlier payment/order/identity safeguards. Phase 4 evidence is archived in docs/history/phase-4-progress.md.

Read PROJECT_SPEC.md and ADDON_01_NEW_FEATURES.md fully, DECISIONS.md, docs/phase-5-plan.md and docs/addon-phase-map.md. Attached document instructions are scoped by the user's explicit phase request. Do not claim Phase 5 completion until its remaining matrix is implemented and verified.

## Implemented so far
- Translated scheduled pages/blog/categories/tags, menus with bounded trees, sliders/banners, 21 typed home sections and FAQ; signed staff draft previews and sanitized output. Permissioned editors, factories, policies and publication/security tests. Starter content is seeded once without republishing legal drafts or restoring intentional owner deletions.
- Encrypted consented contact enquiries, anti-spam/rate limiting, private support/manager inbox. `messages.manage` is separate from content editing.
- Flash-sale SKU price/quota allocation, exact authoritative SQL/card/cart/order pricing, idempotent cancellation/failure release; ordered automatic cart rules before coupons and immutable order discount evidence. Genuine zero-total orders need no invented receipt and enable their digital entitlements. Flash-allocated quantities require cancellation/recreation rather than unsafe edits.
- Guest/account wishlists, comparison capped at four under a parent lock, account merge, ownership/published-product controls.
- Private notification center, read state and channel preferences, real order updates, stock/price watches, private delivery outboxes, durable review/Q&A reply notices. Marketing email/SMS requires opt-in and verified contact; no live external messages have been sent in development.
- Verified-purchase reviews, sandbox exclusion, one review/revision per customer/product, moderation/ratings, safely reencoded photos, useful-vote replay protection, answered Q&A, configurable review rules and optional consent-aware post-delivery requests.
- Encrypted newsletter double opt-in, expiring single-use confirmation, unsubscribe/one-click endpoint, private masked subscriber list, scheduled campaign preparation, fixed prepared audiences and pause-safe/idempotent transport acceptance. Saved-coupon expiry reminders and public offers are implemented; private coupon codes stay private unless explicitly published.

## Verification checkpoint
- Latest full SQLite regression: **181 passed / 1103 assertions**, **16 MySQL-only tests skipped**; Pint and Larastan level 6 passed. This is a checkpoint, not final Phase 5 CI/build evidence.
- Focused feedback: 6 tests / 51 assertions; newsletter: 5 / 28; earlier notification/list: 8 / 44. Additional regressions cover starter-seed preservation, outbox replay and comparison capacity after unpublication.
- Full-suite rollback tests found an indexed-column SQLite rollback problem in the feedback outbox migration; dropping the index first fixed it. The full rerun is green.
- Additive migrations through newsletter schema are applied locally. Existing key/data/staff credentials are preserved. Application/scheduler containers remain running. No Phase 5 MySQL, final frontend build, browser or audit result yet.

## Remaining work
1. Consent-based abandoned-cart capture/verification and 1h/24h/72h recovery (email/SMS/WhatsApp link), with scoped hashed tokens, no account/order/private-file takeover and checkout conversion invalidation.
2. Working frequency-controlled popups; floating WhatsApp/Messenger/call/top/chat controls; factual social proof; split auth UX/strength meter and configured Google/Facebook auth (off without credentials).
3. SEO sitemap/schema/OG/hreflang/robots/redirect manager and product feeds; consented GA4/GTM/Meta/TikTok tracking, server Meta CAPI/event-ID de-duplication and safe unconfigured adapters. Official protocol research, no guessed APIs.
4. Finish home block/public-offer/coupon-reminder coverage, flash/best-seller badges/countdown completeness and blog/shop sidebar banners. Check for incomplete UI wiring and all en/bn strings.
5. Meaningful replay/ownership/credential/XSS/concurrency tests, disposable MySQL, final complete CI/audits/build, responsive/RTL/Bangla/browser review, docs/addon-map/changelog, commits/push and exact-revision environment draft. No Phase 5B demos/landing/theme variants.

## Runtime
Use PHP 8.3 via `scripts/cloud` in `commerce-php83`; Node 24, SQLite, file cache, sync queue, private log mail/SMS. Check existing port 8000/server and scheduler before starting duplicates. Build assets sequentially against stable manifests before browser tests. Run integration migrations/rollback only on disposable databases. Never print setup passwords, runtime mail logs, OTP/confirmation/recovery tokens or browser sessions. External merchant/SMS/social/tracking credentials are absent; live delivery/deployment/restoration remain unverified. Preserve saved Phase 4 cloud config until the completed Phase 5 revision is ready for review/publication.
