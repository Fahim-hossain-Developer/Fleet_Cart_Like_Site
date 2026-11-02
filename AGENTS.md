# Project instructions
Read PROJECT_SPEC.md fully before any task, then PROGRESS.md and DECISIONS.md.

## Scope and stack
Original single-vendor ecommerce CMS. PHP 8.3, Laravel 12, MySQL 8 / MariaDB 10.6+ (SQLite for quick local use), Filament, Livewire 3, Alpine, Tailwind 4, Blade and Vite. Pest, Pint and Larastan level 6. English/Bangla, BDT, Asia/Dhaka.

## Commands
Target commands: `composer setup`, `composer dev`, `composer test`, `composer lint`, `composer ci`, `npm run build`. Check PROGRESS.md for which are implemented and verified.

## Rules
Use app/Domain/* modules and Action classes; thin controllers and Livewire components. Integer minor-unit money; server-side validation and authorization; separate customer/admin guards. Never commit secrets. Original assets only. Add meaningful tests and translations with features. Record decisions, validation and blockers. Small commits: `phase-X: description`.

Use the existing checkout; do not create worktrees unless requested. Implement Phase 0 only for the current request. Stop at its boundary; later phases require the user's continuation instruction. The user's explicit push authorization overrides the spec's older local-only Git assumption.
