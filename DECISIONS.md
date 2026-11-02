# Decisions

- 2026-11-02: The attachment defines the product requirements; the current user request limits implementation to Phase 0. Future phases remain deferred.
- 2026-11-02: Section 18 describes a local-first workflow and supersedes earlier queue defaults: sync queue, file cache, log mail, fake integrations, MySQL normal use with SQLite quick-start support. Preserve local usability.
- 2026-11-02: The user's current explicit GitHub push request overrides Section 18's no-remote assumption. Use the existing repository and publish small Phase 0 commits; no hosted deployment is requested.
- 2026-11-02: Original implementation; no commercial source, branding, markup or assets copied. Use official package sources and licensed self-hosted fonts.
- 2026-11-02: Use Filament ^3.3 to preserve the fixed Livewire 3 stack. Upstream panel installation documentation requires PHP 8.1+, Laravel 10+, Livewire 3+. Laravel 12 upstream release notes support PHP 8.3 (security fixes through February 2027). Documentation sites were policy-blocked; official GitHub documentation was used instead.
- 2026-11-02: PHP 8.3 is provisioned with an official Docker image because the managed host lacks PHP. SQLite is the initial runnable development database; normal .env.example defaults remain MySQL. Filament uses its packaged styles, isolated from the Tailwind 4 storefront build.
- 2026-11-02: Keep this request at Phase 0 even though the document describes automatic continuation. The current user explicitly asks for Phase 0 only and a later continuation command.
- 2026-11-02: The Phase 0 storefront is an honest pre-opening shell. Do not present unimplemented catalog/checkout links or fabricated store data as working features.
- 2026-11-02: Dependency installation uses official Git sources with TLS trust configured. For the distribution-only PHPStan package, bootstrap caches the exact locked official codeload archive using Composer's cache key; an existing lock checksum is checked when provided. No checksum or TLS bypass is used.
