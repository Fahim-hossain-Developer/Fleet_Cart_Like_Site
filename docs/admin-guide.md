# Admin guide
Open /admin and sign in using the random credentials printed by initial local setup. The foundation dashboard is available to active staff with admin.access. Default roles: super-admin, manager, order-processor, content-editor, support. Settings/staff management permissions are reserved for super-admin. Catalog resources are documented below; catalog, checkout and order/customer tools are documented below.

## Catalog (Phase 1)
Active staff with `catalog.manage` can open Products, Categories, Brands, Tags, Attributes, Attribute Sets, Product Tabs, Badges, Import/Export and Catalog Preferences. Other staff receive 403. All new screens support English and Bangla UI labels.

Create categories/brands/tags first. Attributes have text/color/image values; assign values to products. Products support simple/variable/digital types, translated descriptions, minor-unit prices (125000 = 1250.00 in a two-decimal currency), sale windows, stock flags, categories/tags/brands, options, private download definitions, related links, SEO and video links/uploads. Gallery uploads generate WebP/AVIF and responsive card images. Edit gallery alt text/order or remove selected images using gallery metadata.

Save a variable product with selected attribute values, then use Generate variants. The Cartesian product is capped at 200 combinations, starts with zero stock, and repeat generation preserves existing variants. Fill variant stock/prices before publishing. Variant attributes created by the generator use attribute/value IDs; manual key/value labels are also displayed.

Duplicate is available on product rows, the edit page and bulk selection. Copies are drafts, have unique slugs/SKUs and reset stock unless explicitly retained. Image files are physically copied; originals are preserved. Product deletion is soft deletion.

Product Tabs supports global, selected-category and per-product content. Reorder global tabs in the table or product tabs in the product repeater. Use the product's Hide global tabs selector for exceptions. Rich content is sanitized on storefront output.

Badges can target products/categories with a date window and priority. Preferences controls new-product days, maximum badges, top bar, clone stock default and sidebar sections. A best-seller badge is not fabricated before actual order statistics exist.

Import/Export accepts a new-product CSV with columns `sku,slug,name_en,name_bn,price,qty,status`; max 500 rows / 2 MB. Prices are integer minor units; status is `draft` or `active`. Valid rows import; errors report row numbers. Existing SKUs/slugs are rejected, never overwritten. Queue follows QUEUE_CONNECTION (sync locally); results belong to the importing staff member. Export contains base product fields, neutralizes spreadsheet formulas and requires catalog permission. CSV round-trip uses this documented simple-product contract; variants/media are managed in their dedicated forms.

Use the bulk Update price, stock or categories action to set either numeric field (zero is valid) and/or attach categories to selected products. Blank numeric fields preserve current values; category assignment preserves existing categories. Changes are validated and logged.

## Cart & Checkout (Phase 2)
Checkout Preferences, tax classes/rates and currencies require `checkout.manage` (super admin by default). Shipping/location tools require `shipping.manage`; coupons require `marketing.manage` (also assigned to managers). Orders is now managed in Phase 3 under `orders.manage`; see the section below.

The default local setup seeds factual Bangladesh location names and example Inside/Outside Dhaka flat rates (BDT 80/150). These are development examples, not current courier quotes. Configure actual delivery prices before sales. Production setup seeds locations/base currency but does not enable sample shipping rates. Locations are editable; the bundled snapshot contains 8 divisions, 64 districts and 494 upazilas and may lag official boundary changes. Add optional area records under upazilas to enable area dropdowns; where no area data exists, checkout uses free text.

Shipping methods support flat, weight-based increments, free-above threshold and pickup, with explicit district coverage. Unsupported districts cannot silently receive free shipping. Define tax classes/rates, choose whether tax is included, and assign classes/weights on products. Percent rates use basis points (1000 = 10%). Included tax is reported separately from additional tax.

Coupons support fixed, percent and free shipping, minimum spend, caps, dates, total and per-normalized-phone usage limits. Usage is rechecked and consumed atomically on placement. Advanced eligibility/cart rules remain Phase 5.

Checkout Preferences selects one-page/three-step layout, order-number prefix, guest checkout, gift defaults/tax, account creation, and COD/bank/manual-mobile enable/instructions. Bank/mobile methods cannot place an order without configured instructions plus a transaction reference or private proof upload. Every COD/manual order starts unpaid; the application does not claim payment verification. Do not put provider credentials in public payment instructions.

Gift messages, recipient details, fee and hide-price flag are order snapshots used by Phase 3 invoices/packing slips. Product gift fee overrides category fees; category fallback is the maximum assigned category fee. Per-line overrides are per unit and replace the order-wide default when present. Otherwise one default order fee applies. Full unfulfilled cancellation should refund the fee; fulfilled refund decisions belong to Phase 3.

New optional accounts receive a one-hour single-use setup link through configured mail or the private local SMS log. Existing contact details remain guest orders; no automatic account attachment or login occurs. Customer login/verification and address books are now implemented in Phase 3. Disabling guest checkout requires that later login flow to be available.

Currencies use manual ppm rates relative to BDT (1000000 = 1.0), decimals, display rounding and markup. The base rate/precision cannot be changed; all charges and authoritative totals remain BDT. Cards show display estimates; price filters are explicitly in BDT. Provider-backed rates arrive in Phase 6. Currency and language choices persist in session/cookies and authenticated profile fields.

## Orders & Customers (Phase 3)

Open Orders → View for status, timeline, internal/public notes, quantity edits before payment/shipment, received-payment receipts, partial shipments, return approval, manual refund receipts, resend, invoice/packing/label PDFs and private proof downloads. Create supports unpaid COD phone orders using real catalog/shipping validation. See [order-management.md](order-management.md) for exact transition, inventory, gift refund and permission rules before processing orders.

Customers has search, blocked filter, a detail/history page, notes, tags, blocking and bounded CSV export. Support has read access; management/exports are owner/manager-only. Phone Blacklists stores normalized +880 numbers as review warnings. Checkout Preferences now controls deduction timing and high-value COD threshold. Customer erasure requests appear on customer details; handle them through your documented retention/support process.

Bangla PDFs require the updated PHP image/Pango runtime. Do not call manual payment/refund records a gateway transaction. Real payment, courier and SMS adapters remain Phase 4.
