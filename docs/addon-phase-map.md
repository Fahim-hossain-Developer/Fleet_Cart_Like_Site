# Addon 01 phase mapping

The current user explicitly requested Section 12's mapping. Its specific phase assignments override the opening blanket delay until Phases 0–2. The original addon is preserved at the root.

| Item | Assigned phase | Current delivery / remaining work |
| --- | --- | --- |
| A1 Clone | 1 | Row, edit header and bulk actions; unique draft copies; stock reset/keep; variants/options/values/tabs/download definitions/relations/taxonomies/media copied; activity entry. Media is physically copied, so deleting a copy cannot remove the original's files. Shared-file references are not offered. |
| A2 Tabs | 1 | Translated global/category-scoped/per-product tabs; ordering; active flag; per-product suppression of global tabs; sanitized rendering. |
| A3 Badges | 1 | Priority/limit, new/sale/out-of-stock/low-stock/free-shipping/pre-order rules; scheduled custom badges on products/categories; rendered cards/product pages. Cart-line badges come in Phase 2; flash-sale and real sales-based best-seller rules require later marketing/order data. No fake sales badges. |
| B1 Search | 1–2 | Category-scoped live product/category suggestions; keyboard-accessible form/links; browser-local recent and database popular terms. |
| B2 Top bar | 1–2 | Toggle, announcement, cookie/session language persistence and base-currency display. Multiple-currency choices/conversion arrive in Phase 2; profile persistence waits for customer accounts. Contact/tracking/help links appear with their real routes. No dead links or relabeled amounts. |
| B3 Quick view | 1–2 | Lazy-loaded modal, image, price, active/in-stock variants, quantity and full-product link. Cart submission is Phase 2. |
| B4 Toolbar | 1–2 | URL-bound page size, columns, grid/list, sort, count, filters and clear-all; sorting/filtering uses displayed sale/variant price. |
| B5 Widgets | 1–2 | Configurable category tree, attribute filters, latest/sale/top-rated products and tags. Ratings require real review aggregates; empty blocks stay empty. Blog widgets and sidebar marketing banners belong with real content in Phase 5. |
| C1 Gift / C2 Account-at-checkout | 2 | Deferred. |
| C3 Email OTP | 3–4 | Deferred. |
| Currency rates | 2 basic / 6 adapters | Deferred. |
| Extra gateways | 8 optional | Deferred. |
| Security addendum | 0 baseline → 7 | Backfilled encrypted credential column, masked server-side vault accessor, hidden serialization, audit exclusions and executable storefront leak tests. Future cart/checkout/landing routes and built JavaScript must join crawler coverage when implemented. Full Security Scan UI/alerts remain Phase 7. |
| B6/B7/B8, C4/C5 | 5 | Deferred. |
| D1–D3 | 5B | Deferred. |
| Content blocks | 5/5B | Deferred. |
| Dashboard/ops | 6 | Deferred. |
| A4 AI | 8 optional | Deferred, no provider keys needed. |

The addon's complete acceptance checklist spans multiple phases; it is not claimed complete in Phase 1.
