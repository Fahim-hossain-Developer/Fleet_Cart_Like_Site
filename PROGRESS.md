# Progress

## Current scope
**Phase 0 — Foundation complete.** Stop here. Phase 1 requires the user's continuation instruction. This is a runnable foundation, not a functioning ecommerce store.

PROJECT_SPEC.md was read fully and preserved from the attachment. AGENTS.md and DECISIONS.md define the working rules. The repository was initially empty with no remote refs; work is on `phase-0-foundation` under the user's explicit push authorization.

## Implemented
- Laravel 12 / PHP 8.3 with committed Composer and npm lockfiles.
- Filament 3.3 / Livewire 3, Spatie permission/media/translatable/activitylog/backup, Scout and Dompdf installed. Media, backup automation, search and PDF features remain in their later phases.
- Pest, Pint, Larastan level 6; `composer setup`, `dev`, `test`, `lint`, `analyse`, `ci`; Vite/Tailwind 4.
- All specified domain directories with Models/Actions/DTOs/Events/Policies placeholders.
- Immutable integer-minor-unit Money: exact operations, currency consistency, nonnegative values and overflow protection. Current formatting assumes two decimal places.
- Allowlisted validated settings Action, persistent JSON model, per-key cache invalidation, policy and activity records.
- Separate `admins` table and `admin` guard; Filament dashboard/login; active-account and permission enforcement; five idempotently seeded staff roles. Passwords are excluded from activity records.
- Initial local admin command generates a random password once; repeat setup preserves the key, credentials and data. No production default admin is created.
- Responsive original Blade shell with header/footer/mobile drawer, keyboard dismissal/focus handling, en/bn locale session, translation scaffolding, Tailwind tokens and licensed local Inter/Noto Sans Bengali fonts.
- Native, Windows and optional Docker instructions; PHP 8.3 Docker image; managed-cloud helper and repeatable bootstrap.
- Local private source/database backup CLI, excluding environment secrets, dependencies, logs and uploaded media.

## Validation — 2026-11-02
- `composer ci`: **passed** (Pint; Larastan level 6 with no errors; **20 Pest tests, 54 assertions**; Vite production build).
- Isolated **MySQL 8.0**: migrations, role seeding and the same **20 tests / 54 assertions passed**. SQLite suite uses an in-memory database. No commerce concurrency claims are made.
- HTTP requests to `/` and `/admin/login`: **200**.
- Chromium: desktop home; 360px mobile drawer and Escape; Bangla locale switch; no horizontal overflow; actual staff login/dashboard; no browser errors or HTTP asset failures. Screenshots inspected.
- `scripts/cloud-bootstrap`: **passed on repeat execution**, preserving existing admin credentials and schema/data; `composer setup` also exercised separately.
- `project:backup`: valid zip, correct source/secret exclusions; SQLite snapshot extracted into a temporary location, integrity checked and seeded roles verified.
- `git diff --check`: clean.

## Diagnosed and corrected
- Host lacked PHP/Composer: provisioned PHP 8.3 with the required extensions and Node in a development image.
- Composer API archives were policy-blocked: installed official Git sources with trusted CA configuration. PHPStan's distribution-only archive was fetched from official GitHub codeload at its exact locked commit; bootstrap reproduces this without changing checksums or disabling TLS.
- New admin instances initially lacked their database-default active flag: added a matching model default; authorization/audit regression tests now pass.
- Repeat setup now skips an existing storage symlink.

## Reuse in this managed environment
Working directory: `/workspace/Fleet_Cart_Like_Site`.
- Install/refresh: `scripts/cloud-bootstrap`.
- Start: `scripts/cloud serve` (process must restart after snapshot restoration).
- Validate: `scripts/cloud composer ci`.
- Generated initial credentials are in the private ignored `storage/logs/local-setup.log` for this instance; never commit or publish its contents. On a fresh local setup they print once. Use a trusted local password reset process if needed.
- Current local `.env` uses SQLite; `.env.example` keeps MySQL defaults. Queue sync, cache file, mail log, public storage; provider config names are placeholders for future integrations.

## Not done / limitations
- Phase 1 and all later phases untouched: no catalog, cart, checkout, customer auth, provider integrations, demo packs or installer.
- No production deployment, release zip, GitHub Actions or published cloud snapshot claimed.
- MariaDB, Windows, the full Compose stack and MySQL backup/restore path are documented or implemented but not validated in this environment. MySQL runtime/migrations/tests were validated independently of Compose.
- PDF Bangla font registration belongs with invoice generation; Phase 0 bundles storefront fonts only.
- Performance targets, production security hardening, complete settings screens, admin CRUD and storefront commerce behavior remain in their specified phases.

## Exact next step
On “Continue with the next phase,” read the spec, decisions and source, verify the recorded baseline, then implement **Phase 1 — Catalog** only. Do not recreate the foundation.
