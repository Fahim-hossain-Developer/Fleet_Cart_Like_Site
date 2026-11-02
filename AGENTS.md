# Project instructions
Read PROJECT_SPEC.md fully before any task, then ADDON_01_NEW_FEATURES.md, PROGRESS.md and DECISIONS.md.

## Scope and stack
Original single-vendor ecommerce CMS. PHP 8.3, Laravel 12, MySQL 8 / MariaDB 10.6+ (SQLite for quick local use), Filament, Livewire 3, Alpine, Tailwind 4, Blade and Vite. Pest, Pint and Larastan level 6. English/Bangla, BDT, Asia/Dhaka.

## Commands
Target commands: `composer setup`, `composer dev`, `composer test`, `composer lint`, `composer ci`, `npm run build`. Check PROGRESS.md for which are implemented and verified.

## Rules
Use app/Domain/* modules and Action classes; thin controllers and Livewire components. Integer minor-unit money; server-side validation and authorization; separate customer/admin guards. Never commit secrets. Original assets only. Add meaningful tests and translations with features. Record decisions, validation and blockers. Small commits: `phase-X: description`.

Use the existing checkout; do not create worktrees unless requested. Implement only the currently authorized phase. Phase 4 — Payments & Couriers is complete within its documented, user-approved scope, including addon C3 customer/SMS and role-configured admin email OTP. Read docs/integrations.md for pending merchant-protocol setup. Phase 5 — Content & Marketing and its mapped B6/B7/B8, C4/C5 and home blocks are implemented; read PROGRESS.md for the final validation record and docs/marketing.md for setup/privacy invariants. Stop before Phase 5B. The user's explicit push authorization overrides the spec's older local-only Git assumption.
