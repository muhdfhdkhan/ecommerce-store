# Embroidery E-Commerce Platform — MVP Scope and Delivery Plan

**Version:** 1.0  
**Purpose:** Define the smallest complete, usable first release without losing the foundations needed for later growth.

---

# 1. MVP Goal

Launch a real embroidery patches and motifs store where customers can discover products, choose sizes/colors/design variants, place orders using COD or bank transfer, and receive order updates. Staff must be able to manage products, stock, orders, shipping, payments and homepage content from a secure admin portal.

The MVP is not a clickable mockup. It must complete the real business transaction from product creation to dispatch.

## 1.1 Confirmed configuration

- Markets: Pakistan and international customers.
- Checkout policy: configurable between guest checkout and registration-required.
- Inventory: product-level, variant-level and made-to-order modes.
- Shipping: manual rates and dispatch, with integration-ready courier design.
- Payments: COD and bank transfer.
- Hosting: shared hosting initially.
- Stack: Core PHP, MySQL/MariaDB, Tailwind CSS, vanilla JavaScript and Fetch API.

---

# 2. MVP Release Boundaries

## Must ship

1. Storefront homepage with editable announcement, banners and sections.
2. Shop, category, collection and deal listings.
3. Product detail with gallery, attributes, variants and variant-specific price/availability.
4. Search, filters, sort and pagination.
5. Cart and coupon application.
6. Customer registration/login and account.
7. Configurable checkout policy.
8. Checkout with address, shipping and payment selection.
9. COD and bank-transfer workflows.
10. Product-level, variant-level and made-to-order inventory.
11. Admin product/category/collection management.
12. Admin order workflow and print documents.
13. Manual shipping and tracking reference.
14. Customer management.
15. Basic promotions/coupons.
16. Admin users, roles and permissions.
17. Core reports, audit logs, backup and deployment.

## Defer until after MVP

- Automated courier booking and live tracking.
- Online card gateway.
- Multi-warehouse inventory.
- Supplier purchase-order automation.
- Loyalty points and referrals.
- Advanced product recommendations.
- Native mobile apps.
- Automated WhatsApp messaging.
- Complex international tax/duty calculation.
- Multi-language interface.
- Multi-currency checkout.
- One-million-concurrent-user infrastructure.

---

# 3. MVP Page Inventory

## 3.1 Public storefront

| ID | Page | MVP content and behavior |
|---|---|---|
| S-01 | Home | Announcement, hero, category tiles, daily deals, featured collection, new arrivals, editorial banner, footer |
| S-02 | Shop | Product grid, search, filters, sort, pagination |
| S-03 | Category | Category intro, subcategories, filtered products |
| S-04 | Collection | Collection image/story and product grid |
| S-05 | Deals | Active sale products and offer labels |
| S-06 | Search results | Query, result count, product cards, empty state |
| S-07 | Product detail | Gallery, description, variant selector, price, stock/lead time, quantity, add-to-cart |
| S-08 | Cart | Variant lines, quantity, remove, coupon, totals |
| S-09 | Checkout | Contact, address, shipping, payment, order review |
| S-10 | Order success | Order number, summary and next steps |
| S-11 | Login | Customer authentication |
| S-12 | Register | Account creation |
| S-13 | Forgot/reset password | Secure password recovery |
| S-14 | Account overview | Recent orders and profile shortcuts |
| S-15 | Profile | Customer details |
| S-16 | Addresses | Add/edit/delete shipping addresses |
| S-17 | My orders | Customer's own order history |
| S-18 | Order detail | Items, totals, payment and status timeline |
| S-19 | Wishlist | Saved products |
| S-20 | About | Brand story |
| S-21 | Contact | Contact details and enquiry form |
| S-22 | FAQ | Common questions |
| S-23 | Policies | Shipping, returns, privacy and terms |
| S-24 | 404/error | Recovery links and safe error messaging |

## 3.2 Admin portal

| ID | Page | MVP content and behavior |
|---|---|---|
| A-01 | Admin login | Secure staff authentication |
| A-02 | Dashboard | Sales, orders, pending confirmations, low stock, recent orders |
| A-03 | Product list | Search, filters, status, create/edit/archive |
| A-04 | Product create/edit | Details, SEO, images, category, collection |
| A-05 | Variant manager | Attributes, combinations, SKU, price, stock mode |
| A-06 | Category list/form | Hierarchy, image, status, SEO |
| A-07 | Collection list/form | Image, description, selected products |
| A-08 | Inventory overview | On-hand/reserved/available where supported |
| A-09 | Stock adjustment | Quantity, reason and audit |
| A-10 | Stock history | Movement list and filters |
| A-11 | Order list | Search, date, status and payment filters |
| A-12 | Order detail | Customer snapshot, items, totals, notes and timeline |
| A-13 | Order print | Invoice, packing slip and picking list |
| A-14 | Dispatch form | Courier/manual carrier, tracking reference and date |
| A-15 | Payment list | COD and bank-transfer records |
| A-16 | Bank transfer review | Proof/reference, approve/reject and reviewer note |
| A-17 | Shipping settings | Zones, methods, flat/free/weight rates |
| A-18 | Promotions | Fixed/percentage campaign and schedule |
| A-19 | Coupons | Code, limits, eligibility and usage |
| A-20 | Customer list | Search, status and order count |
| A-21 | Customer detail | Profile, addresses, orders and notes |
| A-22 | Homepage builder | Section order and visibility |
| A-23 | Announcement/banner manager | Text, images, CTA and schedule |
| A-24 | Pages/menu/footer | Editable basic content and links |
| A-25 | Staff users | Invite/create, activate/deactivate |
| A-26 | Roles/permissions | Assign granular capabilities |
| A-27 | Reports | Sales, orders, products and stock |
| A-28 | Activity logs | Who changed what and when |
| A-29 | Store settings | Identity, timezone, currency, contact |
| A-30 | Checkout settings | Guest/account-required selector |
| A-31 | Payment settings | COD and bank transfer enablement |
| A-32 | Email settings | SMTP configuration and test |
| A-33 | SEO settings | Defaults, sitemap and robots controls |
| A-34 | Backup/system | Backup status, logs and operational checks |

---

# 4. MVP Workflows

## 4.1 Catalog setup

1. Admin creates categories.
2. Admin creates collections.
3. Admin creates product and uploads images.
4. Admin defines attributes and valid variants.
5. Admin chooses inventory mode per product.
6. Admin sets SKU, prices, stock or made-to-order lead time.
7. Admin previews and publishes.
8. Product appears in relevant storefront listings.

## 4.2 Customer purchase

1. Visitor searches or browses.
2. Visitor opens product detail.
3. Visitor selects mandatory variant options.
4. Visitor chooses quantity and adds to cart.
5. Visitor reviews cart and optional coupon.
6. Checkout behavior follows the admin-selected policy.
7. Customer supplies delivery details.
8. Server calculates shipping and final total.
9. Customer selects COD or bank transfer.
10. Customer submits order.
11. System validates stock, coupon, price and idempotency.
12. Order is created transactionally.
13. Customer sees confirmation.
14. Admin sees the new order.

## 4.3 Admin fulfilment

1. Order appears in Pending Confirmation.
2. Staff reviews address, items, stock and payment choice.
3. Staff confirms or places order on hold/cancels with reason.
4. Staff moves order to Processing.
5. Staff prints invoice and packing slip.
6. Staff prepares products.
7. Staff records courier/manual shipping and tracking number.
8. Staff dispatches order.
9. Customer can see status and tracking reference.
10. Staff marks delivered after confirmation.

## 4.4 Bank transfer

1. Customer sees account instructions.
2. Order is placed with Awaiting Verification.
3. Customer may submit transfer reference/proof if enabled.
4. Finance staff checks actual bank receipt.
5. Staff approves/rejects and records evidence/reference.
6. Payment status and order timeline are updated.

---

# 5. MVP Functional Acceptance Criteria

## Catalog
- A product can contain multiple images.
- Product can contain multiple valid variants.
- Each variant can have its own SKU, price and stock.
- Product-level shared stock works when selected.
- Made-to-order product can be purchased without fixed on-hand stock.
- Inactive products cannot be purchased.
- Variant selection is mandatory where options are required.

## Checkout
- Admin can switch between guest-enabled and account-required.
- The setting is enforced server-side.
- COD and bank transfer can each be enabled/disabled.
- Shipping options depend on destination and cart.
- Final amount is calculated on server.
- Repeated submit does not create duplicate orders.
- Last-unit concurrency test does not oversell.

## Orders
- New order appears in admin.
- Order status history records each change.
- Payment status is separate from order status.
- Invoice and packing slip print correctly.
- Dispatch records tracking details.
- Cancellation follows documented stock restoration behavior.

## Admin
- Staff permissions are checked on direct URLs and API requests.
- Catalog/content changes appear on the storefront.
- Audit log records critical edits.
- Staff cannot view customer passwords.
- Payment proof cannot self-approve.

## Security and operations
- CSRF, XSS, SQL injection and file upload tests pass.
- HTTPS is active.
- Private files cannot be fetched publicly.
- Backup and restore test passes.
- Production errors do not expose sensitive details.

---

# 6. MVP Database Baseline

Minimum logical tables:
- `admin_users`, `customers`, `roles`, `permissions`, `role_permissions`, `admin_user_roles`
- `customer_addresses`
- `products`, `product_images`, `product_variants`
- `categories`, `product_categories`
- `collections`, `collection_products`
- `attributes`, `attribute_values`, `variant_attribute_values`
- `inventory`, `stock_movements`, `stock_reservations`
- `carts`, `cart_items`, `wishlists`, `wishlist_items`
- `orders`, `order_items`, `order_addresses`, `order_status_history`, `order_notes`
- `payments`, `payment_transactions`, `payment_proofs`
- `shipping_zones`, `shipping_methods`, `shipping_rates`, `shipments`
- `promotions`, `coupons`, `coupon_redemptions`
- `banners`, `homepage_sections`, `pages`, `menus`, `menu_items`
- `store_settings`, `activity_logs`, `email_logs`, `backup_logs`

Use InnoDB, foreign keys, indexes, unique SKUs/order numbers, decimal monetary fields, immutable order snapshots and transactional checkout.

---

# 7. MVP Development Sequence

| Sprint | Focus | Completion outcome |
|---|---|---|
| 0 | Discovery and hosting audit | Decisions and hosting limits documented |
| 1 | PHP foundation, DB, sessions, security | Secure app shell and DB connection |
| 2 | Admin authentication, roles, audit | Permission-controlled admin |
| 3 | Catalog, categories, variants, images | Admin can publish products |
| 4 | Storefront shell and CMS | Homepage and navigation render |
| 5 | Shop, search, filters, product detail | Customer can discover products |
| 6 | Cart, account and addresses | Shopping intent persists |
| 7 | Inventory and checkout | Transaction-safe order placement |
| 8 | COD/bank transfer and order admin | Staff can process real orders |
| 9 | Shipping, print documents and notifications | Fulfilment can complete |
| 10 | Promotions, reports and SEO | Business operations improved |
| 11 | QA, security, backup and launch | Production-ready first release |

Sprint length should be estimated after the developer confirms available time, hosting access and final business rules. Do not promise a calendar duration before those are known.

---

# 8. Shared Hosting MVP Rules

- Verify PHP version, extensions, MySQL version, memory, execution time, upload limit, cron and SMTP before coding against assumptions.
- Keep private configuration and storage outside the public document root where possible.
- Use optimized images and strict upload limits.
- Use pagination and indexes.
- Avoid persistent workers unless hosting supports them.
- Use cron-driven queued work for non-critical notifications if needed.
- Do not assume Redis, shell access, Composer or object storage are available.
- Do not run heavy imports/reports in a single web request.
- Keep a migration checklist for a future VPS.

---

# 9. Explicit MVP Non-Goals

- Million simultaneous active users on shared hosting.
- Fully automated international customs and duties.
- Native mobile apps.
- Multi-vendor marketplace.
- Automated courier booking until provider credentials/API are confirmed.
- Card payments until a gateway is selected and approved.
- Multi-warehouse stock.
- Full accounting ledger.
- AI personalization.

---

# 10. MVP Release Checklist

### Business
- [ ] Store identity and policies approved.
- [ ] Shipping destinations and rates entered.
- [ ] COD and bank-transfer instructions verified.
- [ ] Return/cancellation rules approved.
- [ ] Product measurement and variant conventions agreed.

### Catalog
- [ ] Categories and collections configured.
- [ ] Products and variants imported/created.
- [ ] SKUs unique.
- [ ] Images optimized and alt text entered.
- [ ] Inventory mode checked for each product.

### Operations
- [ ] Staff roles assigned.
- [ ] Order statuses and transitions tested.
- [ ] Invoice and packing slip approved.
- [ ] Manual dispatch and tracking tested.
- [ ] Bank transfer review tested.

### Technical
- [ ] HTTPS active.
- [ ] Backup schedule configured.
- [ ] Restore test passed.
- [ ] Security test passed.
- [ ] Mobile responsive review passed.
- [ ] Production error logging enabled.
- [ ] Hosting limits and upgrade triggers documented.

**MVP is complete when a real customer can place an order and staff can verify, prepare, print, dispatch and close that order without editing the database manually.**
