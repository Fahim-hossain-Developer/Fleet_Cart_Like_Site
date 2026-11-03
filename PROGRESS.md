# Progress

## Current authorized work
**Phase 5B in progress** on `phase-5b-themes-landings-demos`, baseline pushed Phase 5 `ea36f13`. The user authorized continuation and Retry after a cloud outage; connection and clean checkout verified. Read PROJECT_SPEC.md, addon, DECISIONS.md and docs/phase-5b-plan.md. Stop before Phase 6. Earlier Phase 5 evidence is in docs/history/phase-5-progress.md.

## Implemented checkpoint
- Typed theme presets and six original system presets; four headers, three footers, five cards, four product layouts, three listing arrangements and per-section style choices. Seven additional licensed self-hosted font packages provide six English/Bangla pairings. Logo/color/radius/shadow controls, safe contrast fallbacks and no arbitrary templates/scripts.
- Private expiring owner-bound signed previews, optimistic revision-checked publish and reusable preset creation via the real Filament customizer. Theme preview pages suppress tracking/overlays and iframe forms. Homepage cards can open the real global quick view; existing cart/checkout actions remain connected.
- Theme/content target suite: **14 passed / 148 assertions**. Larastan level 6 and Vite build passed. Final Phase 5B full CI/MySQL/browser evidence is not yet complete.
- Runtime server and scheduler restarted through scripts/cloud; original keys/data/staff credentials retained. New theme migration and roles/system preset seeds applied locally.

## Next work
Implement typed theme/customizer/layout variants first, then landing/templates/packages/real checkout and mapped D1–D3, then safe demo repository/import/export/snapshot/rollback/gallery/commands and all fifteen original bilingual packs. Verify the first three required packs before generating the remaining twelve. See the plan for acceptance and preservation invariants.

## Baseline evidence and runtime
Phase 5 final CI: 215 passed/1433 assertions, 20 MySQL-only skips; MySQL full 232/1491 plus final affected suite 25/189; Pint 590 files, Larastan, Vite, audits and responsive/Bangla/RTL/browser checks passed. These are baseline results, not Phase 5B evidence.

PHP 8.3 runs through scripts/cloud using commerce-php83. Docker is reachable after restart; no app/scheduler containers were running at inspection. Preserve retained key/data/credentials; restart services and verify HTTP. Build assets sequentially with HTTP/browser/DB suites. Use disposable databases for migration-reset/races. No live provider or deployment claims.

## Landing checkout checkpoint
Implemented per-product landing builder, six reusable templates with JSON round trips, typed packages, separate campaign carts, exact server totals, optional bump/fields, three form modes and checkout-purpose COD phone proof. Single-store root/hidden cart routing and protected receipts connect to existing order/payment/stock engines. Fifteen-minute upsell/downsell appends one immutable priced line, with shipping and original receipts preserved. Native Alpine quick view and click-to-load videos keep the landing bundle at 86.29 KB (29.66 KB gzip).

Validation: focused LandingCheckout/Appearance/Content suite **24 passed / 218 assertions**; Larastan level 6 passed; build passed. These are checkpoint results. Demo imports/packs, full CI, MySQL races and final browser checks remain in progress. No Phase 6 work started.
