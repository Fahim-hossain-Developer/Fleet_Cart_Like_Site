# Phase 0 admin guide
Open /admin and sign in using the random credentials printed by initial local setup. The foundation dashboard is available to active staff with admin.access. Default roles: super-admin, manager, order-processor, content-editor, support. Settings/staff management permissions are reserved for super-admin. Catalog resources are documented below; orders and checkout arrive in later phases.

## Catalog (Phase 1)
Active staff with `catalog.manage` can open Products, Categories, Brands, Tags, Attributes, Attribute Sets, Product Tabs, Badges, Import/Export and Catalog Preferences. Other staff receive 403. All new screens support English and Bangla UI labels.

Create categories/brands/tags first. Attributes have text/color/image values; assign values to products. Products support simple/variable/digital types, translated descriptions, minor-unit prices (125000 = 1250.00 in a two-decimal currency), sale windows, stock flags, categories/tags/brands, options, private download definitions, related links, SEO and video links/uploads. Gallery uploads generate WebP/AVIF and responsive card images. Edit gallery alt text/order or remove selected images using gallery metadata.

Save a variable product with selected attribute values, then use Generate variants. The Cartesian product is capped at 200 combinations, starts with zero stock, and repeat generation preserves existing variants. Fill variant stock/prices before publishing. Variant attributes created by the generator use attribute/value IDs; manual key/value labels are also displayed.

Duplicate is available on product rows, the edit page and bulk selection. Copies are drafts, have unique slugs/SKUs and reset stock unless explicitly retained. Image files are physically copied; originals are preserved. Product deletion is soft deletion.

Product Tabs supports global, selected-category and per-product content. Reorder global tabs in the table or product tabs in the product repeater. Use the product's Hide global tabs selector for exceptions. Rich content is sanitized on storefront output.

Badges can target products/categories with a date window and priority. Preferences controls new-product days, maximum badges, top bar, clone stock default and sidebar sections. A best-seller badge is not fabricated before actual order statistics exist.

Import/Export accepts a new-product CSV with columns `sku,slug,name_en,name_bn,price,qty,status`; max 500 rows / 2 MB. Prices are integer minor units; status is `draft` or `active`. Valid rows import; errors report row numbers. Existing SKUs/slugs are rejected, never overwritten. Queue follows QUEUE_CONNECTION (sync locally); results belong to the importing staff member. Export contains base product fields, neutralizes spreadsheet formulas and requires catalog permission. CSV round-trip uses this documented simple-product contract; variants/media are managed in their dedicated forms.

Use the bulk Update price, stock or categories action to set either numeric field (zero is valid) and/or attach categories to selected products. Blank numeric fields preserve current values; category assignment preserves existing categories. Changes are validated and logged.
