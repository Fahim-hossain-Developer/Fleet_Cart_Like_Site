# Ecommerce implementation plan
PROJECT_SPEC.md is the baseline; ADDON_01_NEW_FEATURES.md adds mapped requirements. See docs/addon-phase-map.md for staged addon acceptance.

- Phase 0 complete: original Laravel/PHP foundation, staff auth/roles, Money/settings, bilingual shell and encrypted credential baseline.
- Phase 1 complete: catalog/admin, media/variants/options, CSV, browsing/FULLTEXT, clone/tabs/badges and catalog portions of B1–B5.
- Phase 2 complete: carts, live integer totals, coupons, BD location/shipping/tax, transactional COD/manual checkout, gifts, optional account setup, manual display currencies, receipt ownership and real MySQL concurrency evidence.
- Phase 3 complete: guarded order lifecycle/payment receipts/partial shipments, RMA/manual refunds, manual orders, risk flags, signed Bangla PDFs, customer auth/email OTP/address books/history/reorder/downloads, with verified email before guest-order linking.
- Phase 4 complete within user-approved provider scope: payment/courier abstractions and configured documented adapters, COD advance, separate accounting/booking reconciliation, SMS OTP and role-configured admin email OTP. Nagad/Steadfast/Pathao/RedX remain setup-only until reviewed protocols are supplied.
- Phase 5 implemented: translated scheduled CMS and 21 homepage blocks; flash/cart promotions; reviews/Q&A/lists; newsletters and scoped recovery; notification center; overlays/social sign-in; SEO/feeds and consented tracking/CAPI. See docs/marketing.md and PROGRESS.md for verification and live-provider limitations. Stop before Phase 5B.
- Phases 5B–8 retain the original order and addon Section 12 mapping. No production deployment or whole-addon completion is claimed.

Fixed modular Laravel stack, MySQL normally and SQLite quick start. All slow side effects are after commit; notification retry payloads stay encrypted/private. Full operational and verification instructions are in PROGRESS.md and the developer guide.
