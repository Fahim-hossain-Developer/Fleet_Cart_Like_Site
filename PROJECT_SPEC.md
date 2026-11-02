# PROJECT_SPEC.md — Modern eCommerce CMS (FleetCart-style, Bangladesh-ready)

> **Instruction to Codex (read first):**
> This file is the single source of truth for the whole project. Read it fully before writing any code.
> Build **phase by phase** (Section 15). After each phase: run tests, update `PROGRESS.md` (create it), and only then continue.
> Do **not** change the stack in Section 3. Do **not** skip a phase. If something is ambiguous, pick the simplest option, write the decision in `DECISIONS.md`, and continue.
> This is an **original implementation**. It is *inspired by* the feature set of commercial scripts like FleetCart. Do **not** copy any code, markup, CSS, images or text from any third-party product.

---

## 1. Project Overview

**Goal:** A self-hosted, modern, fast, SEO-friendly eCommerce CMS (storefront + admin panel) that one business can install on a normal VPS or good shared hosting and use to sell its own products online.

**Primary market:** Bangladesh (BDT currency, Bangla + English, bKash/Nagad/SSLCommerz/COD, local couriers, Facebook-driven sales). Must still work for any country (multi-currency, multi-language, RTL-ready).

**Model:** Single store (single vendor). Multi-vendor is out of scope for v1.

**Non-goals (v1):** marketplace/multi-vendor, native mobile apps, subscriptions, multi-warehouse ERP.

**Quality bar:** Lighthouse mobile ≥ 85 (Performance), ≥ 95 (SEO, Best Practices, Accessibility) on home, category and product pages.

---

## 2. Users & Roles

| Role | Description |
|---|---|
| Guest | Browse, cart, guest checkout, track order |
| Customer | Account, addresses, orders, wishlist, reviews, downloads |
| Staff roles | Custom roles with granular permissions (below) |
| Super Admin | Everything, including settings, roles, backups |

Default roles seeded: `super-admin`, `manager`, `order-processor`, `content-editor`, `support`.

---

## 3. Tech Stack (FIXED — do not change)

- **Backend:** PHP 8.3, **Laravel 12**
- **Database:** MySQL 8 (utf8mb4). Must also run on MariaDB 10.6+.
- **Admin panel:** **Filament v3** (or latest stable) with custom resources, widgets, and a custom dashboard
- **Storefront:** Laravel **Blade + Livewire 3 + Alpine.js + Tailwind CSS 4**, Vite build. Server-rendered for SEO; Livewire only for interactive parts (cart, search, filters, checkout steps).
- **Auth:** Laravel Breeze-style custom auth for customers; Filament auth for staff (separate guards: `web`, `admin`)
- **Roles/permissions:** `spatie/laravel-permission`
- **Media:** `spatie/laravel-medialibrary` (local disk by default, S3/R2 compatible), automatic WebP/AVIF conversions + responsive sizes
- **Translations:** `spatie/laravel-translatable` for DB content + Laravel lang files for UI
- **Queue:** database driver by default (works on cheap hosting), Redis optional
- **Cache:** file by default, Redis optional
- **Search:** MySQL FULLTEXT by default; optional Meilisearch via Laravel Scout (driver switch in `.env`)
- **PDF:** `barryvdh/laravel-dompdf` (invoices, with Bangla font support — bundle Noto Sans Bengali)
- **Testing:** Pest (feature + unit), Laravel Dusk optional for checkout E2E
- **Code style:** Laravel Pint, PHPStan level 6 (Larastan)
- **Modularity:** Organize by domain modules under `app/Domain/*` (Catalog, Cart, Checkout, Orders, Customers, Marketing, Content, Settings, Shipping, Payments, Reports). Each domain has Models, Actions, DTOs, Events, Policies. Use **Action classes** for business logic; controllers/Livewire stay thin.

**Hosting targets:** (a) VPS with Nginx + PHP-FPM, (b) cPanel hosting with PHP 8.3 (must work: no Node needed at runtime, built assets committed in release zip, `public/` can be mapped to `public_html`).

---

## 4. Folder Structure (target)

```
app/
  Domain/
    Catalog/ Cart/ Checkout/ Orders/ Customers/ Marketing/
    Content/ Settings/ Shipping/ Payments/ Reports/
  Filament/ (Resources, Pages, Widgets)
  Http/ (Controllers, Middleware)
  Livewire/ (Storefront components)
  Services/ (Integrations: couriers, SMS, analytics)
  Support/ (helpers, money, slugs)
database/ (migrations, factories, seeders)
resources/views/ (themes/default/..., emails, pdf)
resources/lang/ (en, bn)
routes/ (web.php, admin.php, api.php)
tests/ (Feature, Unit)
installer/ (web installer)
demos/ (demo store packs: {slug}/manifest.json, content/*.json, media/, preview.jpg + schema/)
docs/ (user + developer docs)
PROJECT_SPEC.md  PROGRESS.md  DECISIONS.md  AGENTS.md
```

Create `AGENTS.md` at the root containing a short summary of: stack, commands (`composer test`, `composer lint`, `npm run build`), coding rules, and "read PROJECT_SPEC.md before any task".

---

## 5. Core Features (Baseline — parity with typical commercial Laravel eCommerce CMS)

### 5.1 Catalog
- Products: name, slug, SKU, short & long description (rich editor), brand, categories (multiple), tags, status (draft/active), visibility, featured flag, new-until date.
- Pricing: regular price, special/sale price with start & end date, cost price (admin only), tax class.
- Inventory: manage stock yes/no, quantity, stock status (in/out/backorder), low-stock threshold, max qty per order.
- Media: multiple images (drag-reorder), base image + gallery, **product video (YouTube/Vimeo/upload)**, alt text per image.
- **Variants & options:** attributes (Color, Size…) with values (swatch color/image support) → auto-generate variants; each variant has its own SKU, price, stock, image. Also **custom product options** (text, textarea, dropdown, checkbox, radio, date, file upload) with optional price adjustment.
- Attribute sets & filterable attributes (feed storefront filters).
- Downloadable/digital products (file upload or external link, download limit, expiry).
- Related, up-sell, cross-sell products (manual + auto by category).
- Product SEO fields: meta title, description, canonical, OG image, slug history (301 redirect on slug change).
- Bulk actions: publish/unpublish, delete, assign category, update price/stock.
- **Import/Export:** CSV/XLSX import & export for products, categories, customers, orders (queued, with validation report).
- Categories: unlimited nesting, image, banner, description, SEO, sort order, show-in-menu flag.
- Brands: logo, description, SEO.
- Tags.
- Flash sales / campaigns: time-boxed discounts on selected products with countdown and limited quantity.

### 5.2 Storefront
- Home page built from **configurable sections** (see 6.1).
- Mega menu (categories + custom links), mobile drawer menu, sticky header, announcement bar.
- Product listing: filters (category, brand, price range slider, attributes, rating, in-stock, on-sale), sorting, pagination or infinite scroll, grid/list toggle, filter state in URL.
- Product page: gallery with zoom + lightbox, variant picker updating price/stock/image live, quantity, add to cart, **Buy Now**, wishlist, compare, share buttons, tabs (description, specs, reviews, Q&A), related products, recently viewed, sticky mobile add-to-cart bar.
- Live search with suggestions (products, categories), search results page, "no results" suggestions, search term log.
- Cart: mini-cart drawer + cart page, update qty, remove, coupon apply, shipping estimator, save for later.
- Checkout (see 5.4).
- Wishlist, compare (up to 4 products).
- Customer account: dashboard, orders (reorder, invoice PDF, cancel while pending, track), addresses, profile, change password, reviews, downloads, wallet/credits (v2).
- Static pages (About, Terms, Privacy, Return policy), contact form, FAQ.
- Newsletter signup (store locally + optional Mailchimp/Brevo sync).
- 404 page with search and popular products; maintenance mode page.
- Cookie consent banner (GDPR-style, configurable).

### 5.3 Marketing
- Coupons: percent/fixed/free shipping, min cart value, max discount cap, usage limit total/per customer, date range, product/category/brand include/exclude, first-order-only.
- Automatic discounts / cart rules (e.g., "10% off above 3000 BDT", "buy 2 get 1" in v2).
- Flash sale (5.1), banners & sliders, popups (newsletter/offer, with frequency control).
- Product reviews & ratings: verified-purchase badge, moderation queue, photo uploads, helpful votes, admin reply.
- Product Q&A (customer asks, admin answers).
- Abandoned cart tracking + recovery email/SMS/WhatsApp link (queued, with schedule).
- Email marketing hooks: order, shipping, review request after delivery.
- Sitemap.xml (auto, split by type), robots.txt, structured data (Product, Offer, AggregateRating, BreadcrumbList, Organization, FAQ), Open Graph + Twitter cards, canonical tags, hreflang for multi-language.
- Tracking: Google Analytics 4, Google Tag Manager, **Meta (Facebook) Pixel + Conversions API (server-side)**, TikTok Pixel, with standard events (ViewContent, AddToCart, InitiateCheckout, Purchase) and **event de-duplication via event_id**.

### 5.4 Checkout, Payments, Shipping, Tax
- One-page or 3-step checkout (admin setting). Guest checkout allowed (toggle). Phone-first checkout (email optional) — important for BD.
- Address fields configurable per country; for Bangladesh: Division → District → Upazila/Thana → Area dropdown (seed data), plus address line.
- Order notes, gift message (optional), terms checkbox.
- **Payment gateway abstraction** (`PaymentGateway` interface: `initiate`, `verify/callback`, `refund`, `webhook`). Gateways:
  - Cash on Delivery (with optional **partial advance** requirement, e.g. delivery charge paid via bKash first)
  - Bank transfer (instructions + proof upload)
  - **SSLCommerz**, **bKash (tokenized checkout)**, **Nagad**, **aamarPay**
  - Stripe, PayPal
  - Manual mobile payment (bKash/Nagad/Rocket "send money" number + TrxID field with admin verification)
- Shipping: flat rate, free shipping above threshold, weight-based, zone-based (district groups, e.g. Inside Dhaka / Outside Dhaka), local pickup. Multiple methods per zone.
- Tax: tax classes, rates per country/region, inclusive/exclusive display.
- Multi-currency (display conversion with manual or auto rates), base currency BDT.
- Order totals engine in one tested class (`PriceCalculator`) — all money stored as integers (minor units) via a `Money` value object.

### 5.5 Orders & Fulfillment
- Order lifecycle statuses (configurable): Pending → Confirmed → Processing → Packed → Shipped → Delivered → Completed; plus Cancelled, Refunded, Returned, Failed, On-hold.
- Separate payment status (unpaid, partial, paid, refunded) and fulfillment status.
- Order detail: timeline/history with notes (internal + customer-visible), edit items before shipping, add manual payment, print invoice / packing slip / shipping label (PDF, Bangla supported), resend emails.
- Partial shipments with tracking number & carrier; **customer-facing order tracking page** (by order no + phone) — a known gap in FleetCart, we include it.
- Refunds & returns (RMA): request from account, admin approve/reject, restock option, refund via original gateway or manual.
- Manual/phone order creation by admin (create order for customer).
- Stock deduction on order confirm (configurable: on placement / on payment), auto-restock on cancel.
- Duplicate-order protection and **fraud/risk flags**: phone blacklist, repeat-cancel customers, same-IP burst orders, high-value COD warning.
- Courier integrations (Bangladesh): **Steadfast, Pathao, RedX** — push order, get tracking, webhook status sync, COD amount collection reports.

### 5.6 Customers
- List, filters, detail with order history, notes, tags, groups (e.g., VIP) with group-based pricing/discount (v2).
- Block/unblock, export, GDPR data export/delete request.
- Login: email/phone + password, **OTP login via SMS**, social login (Google, Facebook).
- Email verification (optional), password reset.

### 5.7 CMS / Content
- Pages (rich editor, SEO, templates), **blog** (categories, tags, authors, SEO) — included here even though some competitors lack it.
- Menus builder (header, footer columns, mobile).
- Sliders, banners with scheduling and device-specific images.
- Home page section builder (6.1).
- Media manager (upload, folders, search, bulk delete, usage check).
- Email templates editor (per event, per language) with variables and preview.
- Translatable everything: products, categories, pages, sections, menu labels.

### 5.8 Reports & Dashboard
- Dashboard widgets: today/7d/30d sales, orders by status, new customers, top products, low stock, recent orders, conversion funnel, traffic sources (from UTM).
- Reports (filter by date, export CSV): sales, orders, products sold, product views, customers, coupons used, taxes, shipping, search terms, abandoned carts, payment methods, courier performance (COD collected vs pending).

### 5.9 Settings (admin)
- General (store name, logo, favicon, contact, timezone, currency, units).
- Languages (add/enable, default, RTL flag), currencies & rates.
- Theme/Appearance: colors, fonts, logo sizes, header/footer layout, dark mode toggle for storefront (optional), custom CSS/JS injection.
- Checkout, payment, shipping, tax settings.
- Email (SMTP/Mailgun/SES/Brevo), SMS gateway (BD providers + Twilio), test-send buttons.
- Social links, social login keys, reCAPTCHA / Cloudflare Turnstile.
- SEO defaults, robots, redirects manager (301/302), schema toggles.
- Maintenance mode with allowed IPs.
- Image/CDN/storage disk, cache clear buttons, queue status, scheduler health.
- Roles & permissions, staff users, **activity log** (who changed what — `spatie/laravel-activitylog`).
- Backups (`spatie/laravel-backup`) with download + schedule; system info page; one-click clear cache.
- **Web installer** (requirements check → DB → admin account → demo data optional) and **updater** (version check + migrate runner).

---

## 6. Advanced / "Better than FleetCart" Additions (build these after baseline)

Priority tags: **[P1]** high value, **[P2]** nice, **[P3]** later.

### 6.1 Storefront Builder [P1]
Home page = ordered list of admin-configurable sections, drag to reorder, enable/disable, per-language: Hero slider, Category grid, Product carousel (by tag/category/flash sale/best-seller/new), Banner row (1–4), Brand logos, Testimonials, Blog posts, Features/USP strip, Newsletter, Rich text/HTML, Video, Countdown offer.

### 6.2 Bangladesh-first Commerce [P1]
- Bangla/English switch with full UI translation; Bangla numerals option; BDT formatting (`৳ 1,250`).
- Phone-first checkout with BD number validation (`01[3-9]xxxxxxxx`) and normalization to `+8801…`.
- Division/District/Upazila data seeded; delivery charge auto from district zone.
- COD partial-advance, courier push in one click, bulk courier booking, courier status sync, COD reconciliation report.
- Incomplete-order capture: save checkout phone/name as soon as typed (with consent notice) for follow-up.
- WhatsApp: "Order on WhatsApp" button on product, floating chat button, order-confirmation message links.
- Facebook Conversions API + Catalog feed (XML/CSV) for Meta & Google Merchant Center.
- SMS: order confirm, shipped, OTP, abandoned cart (BD gateways + custom HTTP gateway adapter).

### 6.3 Conversion Boosters [P1]
- Sticky mobile add-to-cart & buy-now, free-shipping progress bar, stock-left urgency ("Only 3 left"), recently viewed, "frequently bought together" bundles, quantity-based tier pricing, cart upsell drawer, exit-intent popup, back-in-stock notify (email/SMS), price-drop alert for wishlist, order bump at checkout (1 checkbox add-on).

### 6.4 Performance & Technical [P1]
- Full-page caching for guests on catalog pages (invalidate on product change), Redis optional.
- Image pipeline: WebP/AVIF, `srcset`, lazy-load, LCP image preload, blurhash/placeholder.
- Critical CSS inline for home, deferred JS, no layout shift (reserve image ratios).
- DB indexes reviewed (slug, sku, status, category pivot, order number, phone).
- Queue everything slow (emails, SMS, courier API, image conversions, exports).
- Rate limiting on login/OTP/coupon/checkout endpoints; honeypot + Turnstile on forms.

### 6.5 Admin Experience [P2]
- Global search (⌘K) across products/orders/customers.
- Kanban view for orders by status; bulk status change with notes.
- Quick-edit price/stock inline in product list.
- Saved filters, column chooser, dark mode in admin.
- Notifications (new order sound/toast, low stock, failed payment) + optional Telegram/WhatsApp admin alerts.
- Setup checklist on first login (add logo, product, payment, shipping, domain, SEO).

### 6.6 Marketing Extras [P2]
- Loyalty points / store credit, referral codes, gift cards, customer groups pricing, bundle products, pre-orders, product comparison tables per category, size guide per category, affiliate links (simple), UTM capture on orders.
- Review-request automation after Delivered (+ coupon reward for photo reviews).
- A/B test hero banners (simple split) [P3].

### 6.7 Platform [P2/P3]
- REST API (`/api/v1`, Sanctum tokens) for products, orders, customers, cart → enables future mobile app/Next.js storefront. OpenAPI docs generated.
- Webhooks (order.created, order.paid, stock.low…) with signed payloads and retry.
- POS screen for offline shop (barcode/SKU search, cash/card, receipt print) [P3].
- Multi-warehouse / multiple pickup locations [P3].
- Plugin/module system via Laravel packages with a documented hook/event list [P3].
- Marketplace/multi-vendor [out of scope].

### 6.8 Security [P1]
- Strict validation via Form Requests; policies on every model; CSRF everywhere; XSS-safe rich text (HTMLPurifier); uploads validated (mime + extension + size) and stored non-executable.
- 2FA (TOTP) for admin users; login attempt throttling; session timeout; IP allowlist option for admin.
- Audit log, signed URLs for downloads/invoices, `.env` never exposed, security headers (CSP, HSTS, X-Frame-Options, Referrer-Policy).
- Payment webhooks verified by signature/IP; idempotent callbacks; never trust client totals (recalculate server-side).

### 6.9 Demo Store Templates (one-click importable, fully editable) [P1]

**Idea:** Like Avada/Elementor-style theme demos. The CMS ships with **many ready-made demo stores** for different business types. After install (or any time later) the admin opens **Admin → Appearance → Demo Gallery**, previews a demo, clicks **Import**, and the whole store (design + sample content) appears. Everything imported is normal data, so the admin can edit/replace/delete it from the usual admin screens. The goal: a shop owner goes live in under 30 minutes.

#### 6.9.1 What a Demo Pack contains
A demo pack is a folder in `demos/{slug}/`:

```
demos/{slug}/
  manifest.json        # name, slug, version, business_type, store_mode, languages, tags, requires (min app version), preview_url, description(en/bn)
  preview.jpg          # gallery thumbnail (1200x800)
  screenshots/         # optional: home/category/product/checkout shots (desktop + mobile), made by `demo:screenshot`
  content/
    settings.json      # store mode, theme preset, colors, fonts, header/footer variant, product-card style, product-page layout, currency, checkout mode
    categories.json    brands.json   attributes.json   products.json   (with variants + options)
    pages.json         posts.json    menus.json        sliders.json    banners.json
    home_sections.json # ordered section list for the home page (Section 6.1)
    landing_pages.json # for single-product demos (6.9.4)
    reviews.json       faqs.json     coupons.json      shipping_zones.json (sample)
  media/               # sample images (see 6.9.6)
  lang/{en,bn}.json    # demo text translations
```

All content files reference each other by a stable **`demo_key`** (e.g. `cat:panjabi`, `prod:panjabi-cotton-01`) and never by numeric ids.

#### 6.9.2 Demo Gallery (admin UI)
- Grid of cards: thumbnail, name, business-type badge, "Single product" / "Multi product" badge, language badges, **Preview** and **Import** buttons.
- Filters by business type + search.
- **Preview (decided: local-first, no hosting needed):** The project runs locally for now (no GitHub, no hosted demo site). So **do NOT build a separate no-DB preview mode.** "Preview" = (a) the gallery card shows `preview.jpg` + a screenshot slider (`screenshots/*.jpg` in the pack), and (b) a **"Try this demo"** button that runs the normal import (with automatic snapshot) so the admin sees the real storefront at `http://localhost:8000`, and can press **Rollback** or **Switch demo** to try another. Importing a demo must therefore be fast (target < 60 s on a laptop for any demo) and fully reversible. Keep `preview_url` in `manifest.json` optional for the future when a hosted demo site exists.
- **Screenshots:** provide `php artisan demo:screenshot {slug}` which, if headless Chrome/Playwright is installed, captures home, category, product, cart/checkout (or landing page for single-product demos) at desktop + mobile widths into the pack; if not installed, it prints instructions and the gallery falls back to `preview.jpg` only.
- **Import wizard:** choose what to import with checkboxes — Design & settings, Categories & products, Pages & blog, Menus & sliders, Sample reviews, Sample coupons; choose mode: **Merge** (keep existing data) or **Replace** (wipe existing catalog/content first, only after typing a confirmation word). Shows a progress bar (queued job, resumable, per-step status).
- **Safety:** before any import, auto-create a **snapshot/backup** (DB dump of affected tables + settings). Provide **Rollback last import**.
- **Remove demo content:** deletes only records created by that demo (tracked by `demo_import_items`), leaving the admin's own products/orders untouched. Never delete a product that already has orders (soft-delete + hide instead).
- **Re-import / "Reset this demo":** idempotent — importing the same pack twice must not create duplicates (match by `demo_key`).
- **Switch demo:** import a different demo's *design only* (settings + home sections + menus) without touching the catalog.
- Import from the **web installer** too: last step "Choose a starter demo (or start empty)".
- After import, show a **post-import checklist**: replace logo, replace sample images, edit products, set payment/shipping, set domain/SEO.
- **Export as Demo Pack** (admin button): exports the current store (design + content + media) into a zip with the same format, so new demos can be created from any configured store, and so a developer can add demos without code changes. Also **Import from zip** (upload a pack).

#### 6.9.3 Theme Presets & Layout Variants (needed so demos look genuinely different)
Demos must differ in design, not just content. Build a **variant system** driven by settings (no per-demo custom code):

| Area | Variants to build (min.) |
|---|---|
| Header | 4 (classic with search bar, centered logo, minimal/sticky, mega-menu with top bar) |
| Footer | 3 (4-column, minimal, newsletter-focused) |
| Product card | 5 (minimal, hover-second-image, quick-view + add-to-cart overlay, boxed with badges, list-style/food-card) |
| Product page | 4 (gallery-left, gallery-thumbs-bottom, sticky-info, landing-style long page) |
| Category page | 3 (sidebar filters, top filters, no filters/simple grid) |
| Home sections | 15+ section types with several styles each (Section 6.1) |
| Typography | 6 font pairings (Bangla + English safe, self-hosted) |
| Colors | CSS-variable palettes; each demo defines primary, secondary, accent, background, radius, shadow style |

A **Theme Preset** = a saved bundle of these choices. Demo packs reference a preset in `settings.json`; the admin can later change any single choice in **Appearance → Customize** (live preview) and save as a new preset.

#### 6.9.4 Single-Product Store Mode (separate templates)
Many Bangladeshi sellers sell **one product** (e.g. hair oil, herbal item, gadget) with a long landing page and an on-page order form. Support it as a first-class mode:

- Setting `store_mode = multi | single`. In **single** mode the home page `/` *is* the product landing page; no cart page; the order form is on the page; shop/category pages are hidden (toggle); header/footer are minimal.
- **Landing Page Builder** (reuse the Section builder): sections such as Hero (headline, price, CTA), Benefits/icons, Problem→Solution, Ingredients/Specs, Image gallery, Video, Before/After, Customer reviews (with photos), FAQ, Guarantee/Delivery info, Countdown offer, Sticky bottom CTA bar, **Order Form**, Footer.
- **On-page Order Form:** name, phone, address (with district → auto delivery charge Inside/Outside Dhaka), **package selector** (1 pc / 2 pc combo / 3 pc – each with its own price & free-delivery rule), quantity, payment method (COD default, optional bKash/Nagad), optional order bump checkbox, live order summary. Submits via Livewire/AJAX, creates a normal Order, redirects to a **Thank-you page** (order number, optional one-click upsell, WhatsApp button).
- **Any product can get its own landing page** in multi mode too (Product → "Landing page" tab → enable and build with the same sections, served at `/lp/{slug}`), so a normal store can also run ad-campaign landing pages.
- Tracking: Meta Pixel/CAPI + TikTok + GA4 events fire on the landing page (ViewContent, InitiateCheckout when the form is touched, Purchase on success), with de-duplication.
- Speed rules: landing page ≤ 150 KB of JS, hero image preloaded, no layout shift, works well on slow 4G.
- Abuse protection: honeypot + rate limit + duplicate-order guard (same phone + product within X minutes) + incomplete-order capture.
- Ship **at least 3 single-product demo variants** (see 6.9.5).

#### 6.9.5 Demo Packs to Ship (minimum 13)
Each demo includes 12–20 sample products (single-product demos include 1 hero product + 2–3 packages), 6–10 categories where relevant, home sections, menus, 4–5 pages (About, Contact, Delivery & Return, Terms, Privacy), 3–5 blog posts, 6+ sample reviews, FAQ, sample coupon, BD shipping zones (Inside Dhaka / Outside Dhaka), and **Bangla + English text**.

| # | Slug | Business type | Mode | Notes / what makes it different |
|---|---|---|---|---|
| 1 | `fashion-panjabi` | Men's fashion (Panjabi, shirts, pants) | multi | Size & color swatches, size guide, big lookbook banners |
| 2 | `fashion-saree` | Women's wear (Saree, Three-piece, Kurti) | multi | Fabric/occasion filters, zoomable images, festival (Eid/Puja) banner section |
| 3 | `fabric-thaan-kapor` | Cloth/fabric shop (by meter/yard) | multi | Unit = meter/yard, min order qty, swatch images |
| 4 | `food-grocery` | Grocery & daily items | multi | Weight/pack variants (500g/1kg), compact product cards, quick add, delivery slot field |
| 5 | `food-homemade` | Homemade/frozen/organic food (pickle, honey, ghee, dates) | multi | Warm look, "fresh/organic" badges, combo packs, preorder days |
| 6 | `restaurant-cloud-kitchen` | Restaurant / cloud kitchen | multi | Menu-style listing, add-ons options, delivery time, area-based charge |
| 7 | `electronics-gadgets` | Electronics & gadgets | multi | Specs table, compare, warranty badge, EMI info block |
| 8 | `beauty-cosmetics` | Beauty, skincare, perfume | multi | Soft color theme, ingredients tab, shade swatches, reviews with photos |
| 9 | `jewelry-accessories` | Jewelry/bags/watches | multi | Premium minimal look, large images, wishlist-first |
| 10 | `kids-baby` | Kids & baby products | multi | Playful colors, age filter, gift section |
| 11 | `home-furniture` | Furniture/home decor | multi | Large gallery, dimension options, 3-step checkout |
| 12 | `books-stationery` | Books & stationery | multi | Author/publisher as brand, preview excerpt, digital download option |
| 13 | `single-product-classic` | Any single product (general) | **single** | Long landing page, order form with packages |
| 14 | `single-product-herbal-health` | Herbal/oil/health single product | **single** | Problem→solution, ingredients, before/after, trust badges |
| 15 | `single-product-gadget` | Single gadget/tech product | **single** | Video-first hero, feature icons, spec table, countdown offer |

Optional extra later: pharmacy/medicine (needs Rx rules), mobile accessories, auto parts, flower/gift, digital products.

#### 6.9.6 Sample Media Rules
- Do **not** download or copy copyrighted/stock images. Codex must generate **license-safe placeholder images** with a script (`php artisan demo:make-placeholders`) — soft-colored gradient backgrounds with the product/category name and simple shapes, correct aspect ratios, WebP + JPG.
- Pack size target: each demo zip ≤ 15 MB (the core app can fetch heavier optional images later from a configurable "demo assets URL").
- Admin can replace any image via the normal media manager. Show a banner "These are sample images – replace them" until replaced (track via `is_demo` flag on media).
- Sample text must be generic and original; no real brand names/trademarks.

#### 6.9.7 Technical Design
- `App\Domain\Settings\Demo\DemoRepository` — scans `demos/` + uploaded packs, validates `manifest.json` (JSON Schema in `demos/schema/manifest.schema.json`).
- `ImportDemoPack` **Action** orchestrating small step classes: `SnapshotStep`, `SettingsStep`, `CatalogStep`, `ContentStep`, `MenusStep`, `MediaStep`, `FinalizeStep`; each implements `DemoImportStep { run(DemoContext); rollback(DemoContext); }`.
- Run as a **queued job chain** with progress stored in `demo_imports`; works with the `database` queue and, if the queue worker isn't running (common on shared hosting), falls back to **sync mode in small batches via AJAX polling** (each request processes N records).
- Use DB transactions per step; on failure → mark failed, offer **Rollback**.
- Every created record is registered in `demo_import_items (demo_import_id, model_type, model_id, demo_key)`.
- Slug collisions: append `-2`, `-3`; never overwrite admin-created records in **Merge** mode.
- Artisan commands: `demo:list`, `demo:import {slug} --only=... --mode=merge|replace`, `demo:export {slug}`, `demo:remove {slug}`, `demo:validate {path}`, `demo:make-placeholders`.
- Permissions: `demo.view`, `demo.import`, `demo.export`; all actions logged in the activity log.
- Tests: import each shipped demo on a fresh DB (smoke test, run by `composer ci`), import twice → no duplicates, remove → only demo records deleted, rollback restores snapshot, manifest validation fails on bad pack, single-mode demo makes `/` render the landing page and order form creates an order.

---

## 7. Database Schema (core tables; add `created_at`, `updated_at`, soft deletes where noted)

| Table | Key columns |
|---|---|
| `users` | id, name, email?, phone?, password, email_verified_at, phone_verified_at, status, group_id, last_login_at, deleted_at |
| `admins` | id, name, email, password, two_factor_secret, status |
| `addresses` | id, user_id, name, phone, country, division, district, upazila, area, line1, line2, postcode, is_default |
| `categories` | id, parent_id, slug, name(json), description(json), image, sort, is_active, seo(json), `_lft/_rgt` or path |
| `brands` | id, slug, name(json), logo, is_active, seo(json) |
| `products` | id, sku, slug, name(json), short_desc(json), description(json), brand_id, type(simple/variable/digital), status, price, special_price, special_from, special_to, cost, tax_class_id, manage_stock, qty, stock_status, low_stock_at, max_per_order, is_featured, new_until, video_url, seo(json), views, rating_avg, rating_count, deleted_at |
| `category_product` | category_id, product_id |
| `product_tag`, `tags` | standard |
| `attributes`, `attribute_values` | name(json), type(text/color/image), value, sort |
| `product_variants` | id, product_id, sku, price, special_price, qty, image_id, attributes(json), is_active |
| `product_options`, `option_values` | product_id, type, label(json), required, price, price_type |
| `product_relations` | product_id, related_id, type(related/upsell/crosssell) |
| `product_downloads` | product_id, file, limit, expiry_days |
| `flash_sales`, `flash_sale_items` | start/end, product_id, price, qty, sold |
| `coupons`, `coupon_usages` | code, type, value, rules(json), limits, dates, user_id, order_id |
| `cart_rules` | conditions(json), actions(json), priority, dates |
| `carts`, `cart_items` | session/user, items, coupon_id, totals cache, abandoned_at, recovery_sent_at |
| `orders` | id, number, user_id?, guest_email, phone, status, payment_status, fulfillment_status, currency, rate, subtotal, discount, shipping, tax, total, paid_amount, payment_method, shipping_method, billing(json), shipping_address(json), note, ip, utm(json), risk_score, placed_at |
| `order_items` | order_id, product_id, variant_id, name, sku, options(json), qty, unit_price, discount, tax, total |
| `order_events` | order_id, type, note, is_public, actor |
| `payments` | order_id, gateway, trx_id, amount, status, payload(json), paid_at |
| `refunds`, `returns` | order_id, items(json), amount, reason, status |
| `shipments` | order_id, courier, tracking_no, consignment_id, status, cod_amount, payload(json), items(json) |
| `shipping_zones`, `shipping_methods`, `shipping_rates` | zone→locations(json), method→type, rate rules |
| `tax_classes`, `tax_rates` | country, region, rate, priority |
| `reviews` | product_id, user_id, rating, title, body, images(json), is_verified, status, admin_reply |
| `questions` | product_id, user_id, question, answer, status |
| `wishlists`, `compare_lists` | user/session, product_id |
| `pages`, `posts`, `post_categories` | slug, title(json), body(json), seo(json), status, published_at |
| `menus`, `menu_items` | tree with polymorphic link target |
| `sliders`, `slides`, `banners` | image(desktop/mobile), link, schedule, position |
| `home_sections` | type, config(json), sort, is_active, locale scope |
| `settings` | key, value(json), group |
| `currencies`, `languages` | code, rate/flags, is_default, is_active |
| `redirects` | from, to, code |
| `search_logs` | term, results, user_id |
| `newsletter_subscribers` | email/phone, status, source |
| `notifications_log` | channel, to, template, status, payload |
| `webhooks`, `webhook_deliveries` | url, events, secret, attempts |
| `locations` | type(division/district/upazila/area), parent_id, name(json) |
| `demo_imports` | id, demo_slug, version, options(json), mode(merge/replace), status(queued/running/done/failed/rolled_back), progress, snapshot_path, error, imported_by, imported_at |
| `demo_import_items` | demo_import_id, model_type, model_id, demo_key (unique per demo_slug+model_type) |
| `theme_presets` | id, name, slug, config(json: colors, fonts, header/footer/card/product-page/category variants), is_system |
| `landing_pages` | id, product_id?, slug, sections(json), is_active, seo(json), views, orders_count |
| `product_packages` | product_id, label(json), qty, price, free_shipping, sort (for single-product order form) |
| `activity_log`, roles/permissions tables | via packages |

Rules: money = unsigned big integers in minor units; every FK indexed; unique indexes on `slug`, `sku`, `orders.number`, `coupons.code`.

---

## 8. Key Business Rules (must be unit-tested)

1. Price shown = special price if active (date window) else regular; flash-sale price overrides when active and stock remains.
2. Cart totals computed server-side only, in this order: item prices → cart rules → coupon → shipping → tax → total. Rounding once, at line level.
3. Stock reservation: on checkout start (optional 15 min hold), deduct on order confirm per setting, restore on cancel/failure/refund-with-restock.
4. Coupon validation: re-checked at order placement (race-safe with DB transaction + row lock on usage counters).
5. Order number format configurable, e.g. `ORD-YYMMDD-XXXX`, collision-safe.
6. Payment callbacks are idempotent; an order is marked paid only after server-to-server verification.
7. Guest orders are linkable to an account later via phone/email match after verification.
8. Slug change creates a redirect entry automatically.
9. Deleting a product with orders = soft delete only.
10. Variants: parent product price range shown as "from X"; out-of-stock variants shown disabled.

---

## 9. Storefront Routes (reference)

`/` · `/shop` · `/category/{slug}` · `/brand/{slug}` · `/product/{slug}` · `/search` · `/cart` · `/checkout` · `/checkout/success/{order}` · `/track-order` · `/wishlist` · `/compare` · `/blog` · `/blog/{slug}` · `/page/{slug}` · `/contact` · `/account` · `/account/orders` · `/account/orders/{number}` · `/account/addresses` · `/account/reviews` · `/account/downloads` · `/login` · `/register` · `/forgot-password` · `/sitemap.xml` · `/robots.txt` · `/feeds/meta.xml` · `/feeds/google.xml`. Admin lives at `/admin` (configurable prefix).

Language prefix optional (`/bn/...`) with hreflang.

---

## 10. Emails / SMS / Notifications

Events: order placed (customer + admin), payment received/failed, order confirmed, shipped (with tracking), delivered, cancelled, refunded, return status, abandoned cart (1h / 24h / 72h), review request (3 days after delivery), back-in-stock, password reset, OTP, welcome, newsletter confirm, low-stock (admin), new review (admin).
All templates editable in admin, per language, with variables (`{customer_name}`, `{order_number}`, `{tracking_url}` …) and test-send.

---

## 11. Installer & Updates

- `/install` wizard: server requirements check, folder permissions, DB credentials test, run migrations, create super admin, optional demo seeder (50 products, categories, banners, pages), write `.env`, lock installer.
- Release packaging script producing a zip with `vendor/` and built assets for shared hosting.
- Version table + in-admin "check for update" (stub for now) and safe migration runner.

---

## 12. Testing & Quality Requirements

- Pest feature tests for: cart math, coupon rules, stock deduction, checkout (COD + one gateway mocked), order status transitions, permissions, import/export, slug redirects.
- Unit tests for `Money`, `PriceCalculator`, shipping rate resolver, BD phone normalizer.
- Factories + seeders for all core models.
- Local quality gate: `composer ci` script runs pint, phpstan, pest and `npm run build` in one command. A GitHub Actions file (`.github/workflows/ci.yml`) is **optional / later** — create it only when the repo is pushed to GitHub (see Section 18).
- No feature is "done" without tests + admin UI + storefront UI + translations (en + bn).

---

## 13. UI / UX Guidelines

- Mobile-first (most BD traffic is mobile). Touch targets ≥ 44px. Sticky bottom bars on mobile for cart/checkout actions.
- Clean modern design: neutral base, one primary brand color (configurable CSS variables), generous spacing, skeleton loaders, no layout shift.
- Accessible: semantic HTML, labels, focus states, contrast AA, keyboard navigable menus/drawers.
- Bangla font: Noto Sans Bengali (self-hosted), English: Inter (self-hosted). No external font CDN.
- Theme structure: `resources/views/themes/default` with documented sections/partials so a second theme can be added later.

---

## 14. Documentation Deliverables

- `README.md` (install, run, test, build, deploy)
- `docs/admin-guide.md` (every admin screen)
- `docs/developer-guide.md` (architecture, modules, adding a payment gateway/courier, events/hooks)
- `docs/deploy-vps.md` and `docs/deploy-cpanel.md`
- `CHANGELOG.md`

---

## 15. Build Plan (phases — do in order, one at a time)

Each phase ends with: migrations + seeders, admin UI, storefront UI (if applicable), tests green, `PROGRESS.md` updated.

**Phase 0 — Foundation**
- [ ] Laravel 12 project, packages installed, Pint/Larastan/Pest configured, `composer ci` + `composer setup` scripts (Section 18)
- [ ] Domain folder structure, `Money` object, settings service, helpers
- [ ] Admin panel skeleton (Filament), guards, roles/permissions seeded, activity log
- [ ] Storefront layout (header, footer, mobile drawer), Tailwind tokens, i18n (en/bn) scaffolding, Bangla + Inter fonts

**Phase 1 — Catalog**
- [ ] Categories, brands, tags, attributes
- [ ] Products (simple), media library, SEO fields, stock fields
- [ ] Variable products + options, downloadable products
- [ ] Admin CSV import/export
- [ ] Storefront: category/brand listing with filters/sort, product page, search (FULLTEXT), related/recently viewed

**Phase 2 — Cart & Checkout**
- [ ] Cart (session + DB for logged-in), mini-cart, coupons (basic)
- [ ] `locations` seed (BD), address forms, shipping zones/methods, tax
- [ ] Checkout (guest + customer), COD + bank transfer + manual mobile payment
- [ ] Order creation, number generator, emails, success page, stock deduction

**Phase 3 — Orders & Customers**
- [ ] Admin order management, statuses/timeline, invoice/packing slip PDFs (Bangla OK)
- [ ] Customer accounts, addresses, order history, reorder, cancel, tracking page
- [ ] Refunds/returns, manual orders, risk flags

**Phase 4 — Payments & Couriers (BD)**
- [ ] Gateway interface + SSLCommerz, bKash, Nagad, aamarPay, Stripe, PayPal
- [ ] COD partial advance
- [ ] Steadfast, Pathao, RedX integrations + webhooks + COD report
- [ ] SMS gateway layer + OTP login

**Phase 5 — Content & Marketing**
- [ ] Pages, blog, menus builder, sliders/banners, **home section builder**
- [ ] Flash sales, cart rules, reviews & Q&A, wishlist, compare
- [ ] Abandoned cart recovery, newsletter, popups
- [ ] SEO: sitemap, schema, redirects, OG; tracking (GA4/GTM/Meta Pixel + CAPI/TikTok); product feeds

**Phase 5B — Theme Variants, Single-Product Mode & Demo Store Templates (Section 6.9)**
- [ ] Theme preset system + layout variants (header, footer, product card, product page, category page, fonts, colors) with live customizer
- [ ] `store_mode` single/multi, landing page builder, on-page order form with packages, thank-you page, per-product landing pages (`/lp/{slug}`)
- [ ] Demo pack format + JSON schema, `DemoRepository`, import steps, snapshot/rollback, remove-demo-content, idempotent re-import
- [ ] Demo Gallery admin UI (screenshots + "Try this demo" = import with snapshot, no separate preview mode), import wizard, export-as-pack, import-from-zip, installer step "choose a starter demo"
- [ ] `demo:make-placeholders` generator + build the 15 demo packs from Section 6.9.5 (start with 3: `fashion-panjabi`, `food-grocery`, `single-product-classic`; verify the whole flow; then build the remaining 12)
- [ ] Tests from Section 6.9.7 green via `composer ci`

**Phase 6 — Reports, Settings, Installer**
- [ ] Dashboard widgets + reports with CSV export
- [ ] Full settings screens, email template editor, backup, 2FA, maintenance mode
- [ ] Web installer, release packaging script, docs

**Phase 7 — Polish & Performance**
- [ ] Page caching, image pipeline, critical CSS, Lighthouse pass, accessibility pass
- [ ] Security headers, rate limits, penetration checklist, load test notes

**Phase 8 — Extras (optional, pick by priority tag in Section 6)**
- [ ] Loyalty/store credit, bundles, back-in-stock, REST API, webhooks, POS…

---

## 16. Definition of Done (per feature)

1. Migration + model + factory + policy
2. Admin CRUD (permissions respected, validation, bulk actions where relevant)
3. Storefront UI (responsive, accessible, SEO tags)
4. Translations en + bn
5. Tests (feature + unit) passing; Pint + PHPStan clean
6. Activity log + events fired where relevant
7. Documented in `docs/`
8. `PROGRESS.md` updated (what's done, what's next, known issues)

---

## 17. How Codex Should Work

- Start by creating `AGENTS.md`, `PROGRESS.md`, `DECISIONS.md`, then begin **Phase 0**.
- Work in small commits; each commit message: `phase-X: short description`.
- Prefer boring, well-documented Laravel conventions over clever abstractions.
- Never hardcode secrets, URLs or currency symbols; use config/settings.
- Never trust client input for prices, totals, stock or permissions.
- When a third-party API (bKash, Nagad, courier) needs real credentials, build against an interface + fake driver + sandbox config, and document required `.env` keys. Do not invent API endpoints: leave a clearly marked `TODO(api-docs)` where official docs are needed.
- If the spec conflicts with itself, the **later numbered section wins**; log the decision.
- At the end of every phase, print a short summary: done / not done / next steps / how to run & test.

## 18. Local Development Setup (no GitHub, no hosting yet)

**Decision:** For now the project lives only on the developer's own computer with a local AI coding tool. No GitHub, no remote CI, no hosted demo site. Everything must be runnable and verifiable locally. (Record this in `DECISIONS.md` at the start.)

- **Version control:** run `git init` locally and commit per phase (`phase-X: ...`). No remote required. Keep `.gitignore` correct (`.env`, `vendor/`, `node_modules/`, `storage/*.key`, uploaded media).
- **Local run options (document all in README):** (1) `php artisan serve` + `npm run dev`, (2) Laragon/XAMPP on Windows, (3) optional Docker Compose (`docker-compose.yml` with php-fpm, nginx, mysql, mailpit).
- **`.env.example` local defaults:** `APP_URL=http://localhost:8000`, MySQL for normal use, **SQLite allowed for quick local runs** (all migrations must work on both MySQL and SQLite — avoid MySQL-only SQL; if FULLTEXT search is unavailable on SQLite, fall back to `LIKE`), `QUEUE_CONNECTION=sync` (so demo import and emails run without a worker), `MAIL_MAILER=log` (or Mailpit), `CACHE_STORE=file`, `FILESYSTEM_DISK=public`, payment gateways in **sandbox/fake** mode.
- **`composer setup`** (one command): install deps, copy `.env`, generate key, migrate, seed roles + default admin (`admin@example.com` / random password printed once), `storage:link`, `npm install && npm run build`.
- **`composer dev`**: starts server + Vite + queue listener + log tail together.
- **`composer ci`**: pint, phpstan, pest, build.
- **Fast demo testing locally:** `php artisan migrate:fresh --seed && php artisan demo:import fashion-panjabi` must give a complete working store in under a minute. Provide `php artisan demo:import {slug} --fresh` as a shortcut that resets the DB first (**only allowed when `APP_ENV=local`**).
- **Fake external services locally:** SMS driver `log`, courier driver `fake`, payment drivers `fake` (simulate success/fail/cancel pages) so checkout can be fully tested with no internet and no credentials.
- **Testing on phone (same Wi-Fi):** document `php artisan serve --host=0.0.0.0` and setting `APP_URL` to the PC's LAN IP, since most customers use mobile.
- **Backup of the project itself:** since there is no GitHub, add `php artisan project:backup` (zips source without `vendor`/`node_modules` plus a DB dump into a `backups/` folder outside `public/`) and remind in README to copy it to external storage regularly.
- **Later (optional):** when the repo moves to GitHub/hosting, add the CI workflow, a hosted demo site (set `preview_url` in each demo manifest), and deployment docs.

---

*End of spec.*
