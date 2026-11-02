# Addon 01 phase mapping

The current user explicitly requested Section 12's mapping. Its specific phase assignments override the opening blanket delay until Phases 0–2. The original addon is preserved at the root.

| Item | Assigned phase | Current delivery / remaining work |
| --- | --- | --- |
| A1 Clone | 1 | Row, edit header and bulk actions; unique draft copies; stock reset/keep; variants/options/values/tabs/download definitions/relations/taxonomies/media copied; activity entry. Media is physically copied, so deleting a copy cannot remove the original's files. Shared-file references are not offered. |
| A2 Tabs | 1 | Translated global/category-scoped/per-product tabs; ordering; active flag; per-product suppression of global tabs; sanitized rendering. |
| A3 Badges | 1 | Priority/limit, new/sale/out-of-stock/low-stock/free-shipping/pre-order rules; scheduled custom badges on products/categories; rendered cards/product pages. Cart-line badges are implemented in Phase 2; Phase 5 adds real flash-sale quota/prices and recent eligible live-sales best-seller ranking/badges. No fake sales badges. |
| B1 Search | 1–2 | Category-scoped live product/category suggestions; keyboard-accessible form/links; browser-local recent and database popular terms. |
| B2 Top bar | 1–2 | Toggle, announcement, cookie/session language persistence and base-currency display. Manual multi-currency choices/conversion and authenticated profile field persistence are implemented in Phase 2; customer login is delivered in Phase 3. Contact/tracking/help links appear with their real routes. No dead links or relabeled amounts. |
| B3 Quick view | 1–2 | Lazy-loaded modal, image, price, active/in-stock variants, quantity and full-product link. Cart submission is implemented in Phase 2; products with required custom options use the full product form. |
| B4 Toolbar | 1–2 | URL-bound page size, columns, grid/list, sort, count, filters and clear-all; sorting/filtering uses displayed sale/variant price. |
| B5 Widgets | 1–2 | Configurable category tree, attribute filters, latest/sale/top-rated products and tags. Ratings require real review aggregates; empty blocks stay empty. Phase 5 adds real blog categories/recent/popular/tags/newsletter and scheduled shop/blog sidebar banners. |
| C1 Gift / C2 Account-at-checkout | 2 | Implemented: fees/recipient/message/hide-price snapshots; optional/auto new accounts with expiring setup link and encrypted delivery retry. Existing contacts remain unattached guest orders. Invoice/packing/label gift rendering, refund rules and verified customer accounts are delivered in Phase 3. |
| C3 Email OTP | 3–4 | Phase 3: customer email login/verification codes, hashed six digits, ten-minute expiry, attempt/send limits, single use and verified guest-order linking. First verification revokes pre-verification credentials/sessions. Phase 4 adds delivered SMS phone login/verification and opt-in per-role admin email codes, including password/session takeover defenses and concurrent single-use tests. TOTP remains Phase 6; external SMS/mail delivery is not certified. |
| Currency rates | 2 basic / 6 adapters | Manual rates/history, integer conversion/rounding/markup, profile/cookie choice and order snapshots implemented. All payment charges stay base currency. Automatic provider adapters remain Phase 6. |
| Extra gateways | 8 optional | Deferred. |
| Security addendum | 0 baseline → 7 | Backfilled encrypted credential column, masked server-side vault accessor, hidden serialization, audit exclusions and executable storefront leak tests. Cart/checkout/receipt leak coverage was added in Phase 2. Future landing routes and built JavaScript must join crawler coverage when implemented. Phase 5 extends the crawler to CMS/marketing/auth/list pages and built JavaScript. Full Security Scan UI/alerts remain Phase 7. |
| B6 Floating controls | 5 | Configured WhatsApp/Messenger/call/top and click-to-load Tawk IDs, public path scope, safe targets, measured mobile purchase-bar offset. Real widget configuration remains externally unverified. |
| B7 Social proof | 5 | Default-off city/product/time notices from recent fulfilled fully received live orders only; privacy, frequency and excluded paths. No fabricated sales. |
| B8 Auth pages | 5 | Split responsive auth/strength/guest/phone-or-email/OTP/remember flows; configured Google state/PKCE/userinfo and Facebook app/subject token verification; explicit linking after fresh contact proof, encrypted root keys. Live provider app verification remains pending owner credentials. |
| C4 Notification center | 5 | Private in-app/read state, per-channel preferences, real order/stock/price/coupon/reply sources, durable consent-checked email/SMS outboxes. |
| C5 Review settings | 5 | Moderation/purchase/minimum/photos/profanity/one revision per customer/product and optional recent-delivery review requests. |
| D1–D3 | 5B | Deferred. |
| Content blocks | 5/5B | Phase 5: 21 typed homepage blocks, tabs/countdown/brand/price/social image grid/payment-delivery/comparison/hotspots/video/location support, translated/scheduled/sanitized. Landing/demos/variants remain Phase 5B. |
| Dashboard/ops | 6 | Deferred. |
| A4 AI | 8 optional | Deferred, no provider keys needed. |

The addon's complete acceptance checklist spans multiple phases; it is not claimed complete after Phase 5; later mapped items remain deferred.
