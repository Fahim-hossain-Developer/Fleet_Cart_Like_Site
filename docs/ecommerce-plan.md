# Implementation plan
PROJECT_SPEC.md is the full feature matrix, architecture and schema reference.

Current authorization: Phase 0 foundation only. All catalog, cart, checkout, order, customer, provider, marketing, demo pack, installer, report and polish features remain deferred to their numbered phases. No claim of complete store functionality is made.

Phase 0 acceptance: Laravel 12 / PHP 8.3; locked packages; setup and quality scripts; domain directories; integer Money; persisted settings; separate admin guard; seeded permissions and roles; activity log; Filament dashboard; responsive bilingual Blade shell with local fonts; meaningful tests, static analysis and frontend build.

Use MySQL 8 in normal development and production; SQLite quick-start and fast tests. MySQL and MariaDB compatibility must be tracked separately from SQLite test evidence. No live provider credentials are needed in Phase 0.
