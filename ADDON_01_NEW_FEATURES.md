# ADDON_01_NEW_FEATURES.md — Additional Features (delta only)

> **Instruction to Codex:** This file is an **addendum** to `PROJECT_SPEC.md`. Read `PROJECT_SPEC.md` first, then this file.
> It contains **only features that are NOT already specified** in `PROJECT_SPEC.md` (or that were only mentioned in one line and are expanded here).
> Same rules apply: same fixed stack, original implementation (no copying of any third-party code/markup/CSS/text), tests + admin UI + storefront UI + en/bn translations for every feature, update `PROGRESS.md`.
> Where a feature below fits an existing phase, build it in that phase (see the mapping table in Section 12). Do not start these before Phase 0–2 are done.
> Priority tags: **[P1]** high value, **[P2]** nice, **[P3]** later.

---

## 1. Catalog & Admin Productivity

### A1. Duplicate Product (Clone) [P1]
Adding many similar products is slow, so cloning is essential.
- Button on product list row + product edit page + bulk action "Duplicate selected".
- Copies: all fields, categories, tags, attributes, variants (with new SKUs `-copy-1`), options, related links, media (reference or physically copy per setting), SEO fields (slug auto-suffixed).
- New copy is created as **draft**, stock reset to 0 (toggle: keep stock), reviews/orders/views are **not** copied.
- Activity log entry "duplicated from #id".
- Tests: variants/options cloned correctly, unique slug/SKU guaranteed, original unchanged.

### A2. Custom Product Tabs [P2]
- Admin can define **global tabs** (e.g. "Delivery & Return", "Warranty", "Size Guide") shown on all/selected-category products, and **per-product tabs** (rich text).
- Drag-to-reorder, enable/disable per product, translatable.

### A3. Product Badges System [P1]
- Automatic badges: **New** (within N days, configurable), **Sale -XX%**, **Out of Stock**, **Low Stock**, **Free Shipping**, **Flash Sale**, **Pre-order**, **Best Seller** (top N by sales in last 30 days).
- Manual custom badges (text + color + optional icon) assignable per product/category, with start/end date.
- Max badges per card configurable (default 2, priority-ordered). Badge style follows the active theme preset.
- Shown on product card, product page, cart line.

### A4. Admin AI Assistant (optional, off by default) [P2]
- Provider-agnostic `AiProvider` interface with drivers (OpenAI, Gemini, Anthropic, or `none`), keys in Settings → Integrations (encrypted at rest, never sent to the browser).
- Buttons inside the product/category/page/blog editors: **Generate description**, **Improve text**, **Generate SEO title/meta**, **Suggest alt text for images**, **Translate en ↔ bn**, **Draft reply to review/question**.
- Always produces a **draft the admin must review** — never auto-publishes. Show token/cost usage log and a monthly cap setting.
- Never send customer personal data (phone/address) to the AI provider.
- Tests with a fake driver.

---

## 2. Storefront UX Additions

### B1. Header Search with Category Dropdown [P1]
- Search bar has an **"All Categories" dropdown** scoping the query; live suggestions respect the selected category.
- Keyboard accessible; recent searches (local storage) and popular searches (from `search_logs`).

### B2. Top Bar with Switchers [P1]
- Thin top bar (toggle in settings) containing: welcome/announcement text, **language switcher**, **currency switcher**, quick links (Contact, Track Order, Help), social icons, phone/WhatsApp.
- Language and currency choice persisted in cookie + saved on customer profile.

### B3. Quick View Modal [P1]
- Product card "eye" icon opens a modal: gallery, price, variant picker, qty, Add to cart, link to full page. Lazy-loaded (no extra weight on listing pages). Works with all product-card variants.

### B4. Listing Toolbar [P2]
- Per-page selector (12/24/48/96), **grid columns toggle (2/3/4/5)** on desktop, grid/list toggle, sort dropdown, result count ("Showing 1–24 of 312"), active-filter chips with "Clear all". All state in the URL.

### B5. Sidebar Widgets for Shop & Blog [P2]
- Configurable sidebar blocks: **Browse Categories (nested tree, collapsible)**, price slider, attribute filters, **Latest Products**, **Top Rated**, **Sale Items**, banner, tags cloud.
- Blog sidebar: **blog search**, categories with counts, recent posts, tags, popular posts, newsletter box.

### B6. Floating Action Buttons [P1]
- Configurable floating group: **WhatsApp**, **Messenger**, **Call**, **Back-to-top**, optional **Live-chat embed** (script injection). Per-button enable, position (left/right), pages to show/hide, pre-filled WhatsApp message with product name + URL on product pages.
- Must not cover the sticky mobile add-to-cart bar (auto-offset).

### B7. Social Proof Notifications (honest) [P3]
- Small toast "Someone from Dhaka bought *Product X* 12 minutes ago" built **only from real recent orders** (city only, no names/phones), toggle + frequency + exclude list. No fake/generated data.

### B8. Auth Pages [P2]
- Modern split-screen login/register with social login buttons, "continue as guest", phone-or-email field, OTP flow, password strength meter, remember me; same style in RTL.

---

## 3. Checkout & Customer Additions

### C1. Gift Option with Extra Charge [P2]
- Setting: enable "Make it a gift" at checkout. Options: gift wrap (fixed fee, configurable per product/category/order), gift message, hide prices on packing slip/invoice, recipient name/phone.
- Fee appears as a separate line in totals, taxes optional, refunded rules defined.

### C2. Create Account at Checkout / Auto-Account [P1]
- Guest can tick "Create an account" (password optional). Optional mode: **auto-create account from phone** after first order and send a set-password link via SMS.
- Existing phone/email → prompt login or continue as guest (no duplicate accounts).

### C3. Email OTP & Verification Codes [P1]
- In addition to SMS OTP: **email OTP login / email verification code** for customers (first login or risky login), rate-limited, 6-digit, 10-min expiry, hashed storage.
- **Admin login OTP by email** as an alternative to TOTP 2FA (setting per role).

### C4. Customer Notification Center [P3]
- In-app bell in account area: order updates, back-in-stock, price-drop, coupon expiry, review replies; mark-read; preferences per channel (email/SMS/in-app).

### C5. Review Settings [P2]
- Settings: auto-approve vs moderate, require verified purchase, min text length, allow photos, max photos, profanity filter, one review per product per customer, send review request after N days (ties to Phase 5 automation).

---

## 4. Multi-Currency Details [P2]
- Provider adapters for **automatic exchange-rate updates** (e.g., open-API providers; keep as pluggable `RateProvider` with a `manual` default), scheduled job (daily), rate history table, per-currency rounding rules and display format (symbol position, decimals), "rate markup %" setting.
- Orders store the rate used. Payment gateways that don't support the display currency charge in the base currency and show a notice.
- Currency auto-selection by country (optional, off by default).

---

## 5. Extra Payment Gateway Adapters [P3]
Build behind the same `PaymentGateway` interface (sandbox + fake driver first; use official docs, leave `TODO(api-docs)` where unknown):
Razorpay, Paystack, Flutterwave, Mercado Pago, Authorize.net, Mollie, Checkout.com / 2Checkout (pick by demand), Upay / Rocket / Tap (BD wallets) if documentation is available.
Each gateway: enable toggle, test/live mode, supported currencies, min/max amount, fee surcharge option, logo upload, sort order, per-gateway instruction text.

---

## 6. Security Addendum (important) [P1]

Known real-world failure to avoid: a publicly reported vulnerability in an older version of a competing script exposed a payment-gateway key through page content on the storefront. Therefore:

- **Never print secret keys, API secrets, webhook secrets, SMTP passwords, AI keys, or courier tokens into Blade views, inline JS, JSON config blobs, or API responses.** Only public/publishable keys may reach the browser, and only on pages that need them.
- Store all credentials **encrypted** (Laravel `encrypted` cast) in `settings`; show them **masked** in admin (`••••1234`) with a "Replace" action; never re-display in full.
- Add an automated test that crawls the main storefront pages (home, category, product, cart, checkout, landing page) and **fails if the HTML/JS contains any value from the credential store or patterns like `sk_live_`, `secret`, `private_key`**.
- Add a **Security Scan** screen in admin (simple checks): `APP_DEBUG=false` in production, `.env` not web-accessible, `storage` permissions, default admin password changed, HTTPS on, admin prefix changed, queue/scheduler running, backups recent.
- Log and alert on repeated failed logins, webhook signature failures, and admin permission changes.

---

## 7. Landing Page / Single-Product Extras (extends Section 6.9.4)

### D1. Landing Page Template Library [P2]
- In the Landing Page Builder: **"Start from template"** with 6+ starter layouts (general, health/herbal, gadget, fashion, food, service-style), each fully editable and reusable across products. Save any landing page as a template; export/import as JSON.

### D2. Order Form Variations [P2]
- Choose per landing page: **inline form**, **popup form** (CTA opens modal), or **sticky bottom form** on mobile.
- Optional fields toggle: email, alternate phone, delivery note, preferred delivery time; custom extra field (text/dropdown).
- Optional **OTP phone verification before placing COD order** (setting) to cut fake orders.

### D3. Upsell/Downsell After Purchase [P3]
- One-click post-purchase offer on the thank-you page (add to the same order within 15 min, shipping unchanged), with decline path.

---

## 8. Storefront Content Blocks (extends Section 6.1) [P2]
New home/landing section types: **Countdown banner**, **Product tabs (New / Best Seller / On Sale in one carousel with tabs)**, **Shop by Brand**, **Shop by Price**, **Instagram/Facebook feed grid (static images with links, no API dependency)**, **Delivery & Payment info strip**, **Comparison table (Us vs Others)**, **Image hotspots** (tag products on a lookbook image), **Video gallery**, **Store locations / showroom list with map link**.

---

## 9. Admin Dashboard & Ops Additions

- **Global activity feed** on dashboard (new order, new review, low stock, failed payment) with quick actions.
- **Quick actions bar**: add product, create order, create coupon, clear cache.
- **Scheduled tasks status** page (last run of each scheduler job, failed jobs list with retry button).
- **Data cleanup tools**: delete old carts, expired OTPs, old logs, orphaned media (dry-run first).
- **Health check endpoint** `/health` (JSON, protected by token) for uptime monitors.
- **Staff notes on customers/orders** with @mention (notification to that staff).

---

## 10. New Database Tables / Columns (add via new migrations)

| Table / column | Purpose |
|---|---|
| `product_tabs` (id, scope global/product, product_id?, category_ids json, title json, body json, sort, is_active) | A2 |
| `badges` (id, key, label json, color, icon, rule json, priority, starts_at, ends_at, is_active), `badge_product` | A3 |
| `ai_usage_logs` (id, user_id, action, provider, tokens_in, tokens_out, cost, created_at) | A4 |
| `gift_options` / order columns `is_gift`, `gift_message`, `gift_fee`, `hide_prices` | C1 |
| `otp_codes` (id, identifier, channel, code_hash, purpose, expires_at, attempts, used_at) | C3 |
| `user_notifications` (use Laravel notifications table) + `notification_preferences` | C4 |
| `currency_rate_history` (currency_id, rate, source, fetched_at) | Section 4 |
| `landing_templates` (id, name, sections json, thumbnail, is_system) | D1 |
| `floating_buttons` (type, enabled, position, config json, pages json) — or in `settings` | B6 |
| `settings` keys for review rules, social-proof, top bar, listing defaults | B2, B4, B7, C5 |

---

## 11. Acceptance Checklist (addendum features)

- [ ] Duplicate product works from list, edit page and bulk; variants/options cloned; slugs/SKUs unique
- [ ] Badges render correctly on card/page/cart with priority limit
- [ ] Header search category dropdown + top bar switchers persist choice
- [ ] Quick view works for simple and variable products without full page reload
- [ ] Listing toolbar state survives refresh and shared URL
- [ ] Floating buttons configurable and don't overlap mobile sticky bar
- [ ] Gift option fee and message flow through order, invoice, packing slip
- [ ] Auto-account + email OTP flows rate-limited and tested
- [ ] Credential-leak crawler test passes on all main pages
- [ ] Security Scan screen reports correct status for each check
- [ ] AI assistant disabled by default; with fake driver produces draft only
- [ ] All new strings in en + bn; RTL checked for new UI

---

## 12. Where to Build Each Item (map to PROJECT_SPEC phases)

| Item | Build in |
|---|---|
| A1 Duplicate product, A2 Custom tabs, A3 Badges | Phase 1 (Catalog) |
| B1 Search dropdown, B2 Top bar, B3 Quick view, B4 Toolbar, B5 Sidebar widgets | Phase 1 / Phase 2 (storefront UI) |
| C1 Gift option, C2 Auto-account | Phase 2 (Checkout) |
| C3 Email OTP | Phase 3 (Customers) / Phase 4 (OTP login) |
| Section 4 Currency rates | Phase 2 (basic) → Phase 6 (provider adapters) |
| Section 5 Extra gateways | Phase 8 (optional) |
| Section 6 Security addendum | Start in Phase 0 (rules + test skeleton), finish in Phase 7 |
| B6 Floating buttons, B7 Social proof, B8 Auth pages | Phase 5 |
| C4 Notification center, C5 Review settings | Phase 5 |
| D1–D3 Landing extras | Phase 5B |
| Section 8 Content blocks | Phase 5 / 5B |
| Section 9 Dashboard & ops | Phase 6 |
| A4 AI assistant | Phase 8 (optional) |

*End of addendum.*
