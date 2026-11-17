# Embroidery E-Commerce & Business Management Platform
## Complete Project Brief and Software Requirements Specification

**Document type:** Master project brief / SRS  
**Project:** Embroidery patches, motifs and related products e-commerce platform  
**Initial deployment:** Shared hosting  
**Initial markets:** Pakistan and international customers  
**Primary stack:** Core PHP, MySQL/MariaDB, Tailwind CSS, vanilla JavaScript  
**Document status:** Baseline specification for discovery, design and implementation  
**Version:** 1.0

---

# 1. Executive Summary

The project is a custom e-commerce platform for selling embroidery patches, motifs and related textile embellishments. It consists of two connected applications:

1. **Customer-facing storefront:** A responsive public website where visitors discover products, search and filter the catalog, inspect product images and variants, add products to a cart, check out, pay using configured methods, create an account if desired, and follow orders.
2. **Administrative business portal:** A secure management application where authorized staff maintain the catalog, variants, inventory, categories, collections, promotions, homepage content, customers, orders, shipping, payment records, reports and store configuration.

Both applications use a shared application backend and a central database. The storefront must never contain business-critical rules that can be bypassed by changing browser data. Prices, discounts, stock, shipping, tax, payment state and order totals are recalculated and validated server-side.

The system should be designed as a modular monolithic PHP application initially. This is appropriate for a small team and shared-hosting launch, while keeping module boundaries clear enough to support a later move to a VPS or cloud infrastructure. It must not claim that ordinary shared hosting can support one million simultaneous users. The codebase should avoid unnecessary coupling so that caching, background workers, separate media storage, read replicas and multiple application servers can be introduced when infrastructure permits.

## 1.1 Product vision

Create a trustworthy, visually distinctive embroidery shopping experience that makes detailed product discovery easy and gives the business reliable control over orders, stock, content and customer service.

## 1.2 Business objectives

- Sell embroidery patches, motifs and related products online in Pakistan and internationally.
- Present products using high-quality galleries and clear variant selection.
- Let staff manage the website without editing source code.
- Reduce manual order handling and stock mistakes.
- Provide accurate order totals, shipping charges and payment records.
- Give customers self-service access to their profiles and orders.
- Support made-to-order products as well as stocked products.
- Make the system maintainable, secure, measurable and ready for future growth.

## 1.3 Success measures

The business should define numeric targets before launch. Suggested measures include:
- Successful checkout completion rate.
- Product page to add-to-cart conversion.
- Cart abandonment rate.
- Order confirmation time.
- Dispatch time.
- Cancellation and return rate.
- Stock accuracy.
- Payment reconciliation accuracy.
- Storefront page response time.
- Failed job and email delivery rate.
- Customer support response time.

---

# 2. Scope

## 2.1 In scope

- Public storefront and responsive layouts.
- Product catalog, product variants, images, attributes and SKU management.
- Categories, nested categories, collections, tags and search.
- Product-level, variant-level and made-to-order inventory modes.
- Cart, wishlist and checkout.
- Guest and registered checkout modes configurable in settings.
- Customer registration, authentication, account and address book.
- Order lifecycle, order history and status timeline.
- Order invoice, packing slip and shipping label printing.
- COD and bank-transfer payment methods at initial release.
- Manual payment verification and transaction records.
- Manual shipping rules and optional courier API integration.
- Promotions, coupon codes and scheduled offers.
- Homepage banners, announcement bar, navigation, content pages and footer management.
- Admin dashboard, roles, permissions and activity logs.
- Sales, order, product, customer and inventory reports.
- Email notifications and optional later SMS/WhatsApp integrations.
- SEO metadata, sitemap, robots controls and structured data.
- Backup, restore procedures, monitoring and security controls.
- Shared-hosting deployment with documented constraints and migration plan.

## 2.2 Out of scope for initial release unless separately approved

- Native iOS/Android applications.
- Marketplace/multi-vendor seller accounts.
- Full warehouse management across multiple physical warehouses.
- Automated international customs documentation.
- Automated card processing before a suitable payment gateway and merchant account are selected.
- Complex ERP/accounting integrations.
- AI product recommendations.
- Real-time multi-region infrastructure.
- Guaranteed one-million concurrent-user capacity on shared hosting.

These may be future enhancements. Their data model implications should be considered where inexpensive, but they must not delay the first usable release.

## 2.3 Assumptions and decisions

| Area | Confirmed baseline |
|---|---|
| Market | Pakistan and international |
| Currency | PKR initially; currency-ready design |
| Languages | English initially; translation-ready |
| Checkout | Admin setting: guest + account, or account required |
| Inventory | Supports product stock, variant stock and made-to-order |
| Shipping | Manual methods/rates and optional courier API |
| Payments | COD and bank transfer initially |
| Hosting | Shared hosting initially |
| Frontend | PHP-rendered pages, HTML, Tailwind CSS |
| Backend | Core PHP, no Laravel requirement |
| Database | MySQL/MariaDB with InnoDB |
| Interactivity | Vanilla JavaScript and Fetch/AJAX |
| Architecture | Modular monolith, migration-ready |

**Open business decisions to finalize during discovery:** business legal name and address, contact details, return window, warranty/defect policy, dispatch SLA, product measurement units, international shipping destinations, shipping rate table, bank account display details, tax registration and applicable tax rules, email sender, domain, hosting provider limits, and whether international prices are displayed in PKR only or converted.

---

# 3. Users, Actors and Permissions

## 3.1 Customer actors

### Visitor
- Browse public pages and active products.
- Search, filter, sort and view product details.
- Add items to a session cart.
- Start guest checkout only if enabled.
- Register or sign in.
- View public policies and contact information.

### Registered customer
- All visitor capabilities.
- Maintain profile and addresses.
- View own orders and order timelines.
- Save wishlist items.
- Reorder eligible products.
- Request cancellation/return where policy allows.
- View payment instructions and submit bank-transfer proof if enabled.
- Request account data export or deletion subject to legal and operational retention requirements.

## 3.2 Staff actors

| Role | Typical permissions |
|---|---|
| Super Admin | Full system configuration and access |
| Store Manager | Products, orders, customers, promotions, reports |
| Catalog Manager | Products, variants, categories, collections, images |
| Order Manager | Orders, confirmation, packing, dispatch and printing |
| Inventory Manager | Stock, adjustments, stock movements and purchasing |
| Finance/Accounts | Payment verification, refunds, invoices and financial reports |
| Content Manager | Banners, homepage sections, pages, menus and SEO |
| Support Agent | Customer lookup, order view, permitted notes and support actions |
| Read-only Analyst | Approved dashboards and reports without mutation |

Roles are templates. Permission checks must be granular and enforced on every protected server request. A hidden button is not an authorization mechanism.

## 3.3 Permission model

Permissions should use a consistent format such as:
- `products.view`, `products.create`, `products.update`, `products.archive`
- `inventory.view`, `inventory.adjust`, `inventory.receive`
- `orders.view`, `orders.confirm`, `orders.cancel`, `orders.dispatch`, `orders.refund`
- `payments.view`, `payments.verify`, `payments.refund`
- `customers.view`, `customers.update`, `customers.block`
- `cms.manage`, `reports.view`, `settings.manage`, `users.manage`, `logs.view`

Sensitive permissions such as refunds, permanent deletion, role changes and payment verification should be separately assignable.

---

# 4. Information Architecture

## 4.1 Storefront navigation

Recommended primary navigation:
- Home
- Shop
- Categories
- Collections
- New Arrivals
- Best Sellers
- Deals
- About
- Contact

Header utilities:
- Search
- Account
- Wishlist
- Cart with item count
- Country/currency selector if enabled

Navigation items, labels, order and visibility should be manageable from admin.

## 4.2 Storefront page inventory

| Page | Purpose | Key content/actions |
|---|---|---|
| Home | Discover the brand and catalog | Announcement, hero, categories, deals, featured collections, new arrivals, editorial banner, newsletter |
| Shop | Browse all active products | Search, filters, sorting, pagination |
| Category listing | Browse a product family | Category description, child categories, products |
| Collection listing | Browse curated products | Collection story, image, products |
| Deals | Find active offers | Sale products, coupon guidance, expiry labels |
| Search results | Find matching products | Query, suggestions, filters, result count |
| Product detail | Evaluate and select a product | Gallery, description, variants, stock, price, quantity, add-to-cart |
| Cart | Review intended purchase | Items, variants, quantity, remove, coupon, estimate |
| Checkout | Submit an order | Contact, address, shipping, payment, totals, consent |
| Order success | Confirm submission | Order number, summary, next steps |
| Login | Authenticate | Email/username and password |
| Register | Create account | Name, email, phone, password, verification |
| Forgot/reset password | Recover access | Single-use expiring token |
| My account | Customer self-service | Profile, addresses, orders, wishlist, security |
| Order detail | Inspect a specific own order | Items, payment, shipping, timeline |
| Track order | Track without exposing private data | Order reference plus verification mechanism |
| Wishlist | Saved products | Remove and add to cart |
| About | Brand information | Story, values, imagery |
| Contact | Customer enquiries | Contact details and validated form |
| FAQ | Answer common questions | Searchable question groups |
| Policies | Explain legal/business terms | Shipping, returns, privacy, terms |
| 404/500 | Recover from errors | Helpful navigation; no stack traces |

## 4.3 Admin page inventory

| Module | Pages/views |
|---|---|
| Authentication | Login, logout, password reset, optional 2FA |
| Dashboard | KPI cards, charts, recent orders, low stock, alerts |
| Products | List, create, edit, duplicate, archive, bulk import/export |
| Product variants | Variant matrix, attributes, SKU, pricing and stock |
| Categories | List, create, edit, hierarchy, image and SEO |
| Collections | List, create, edit, manual/automatic membership |
| Inventory | Stock overview, adjustments, movement history, reservations |
| Promotions | Campaigns, coupons, rules, schedules, usage |
| Orders | List, detail, status changes, notes, print documents |
| Payments | Payment list, bank transfer review, reconciliation, refunds |
| Shipping | Zones, methods, rate tables, couriers, shipments, tracking |
| Customers | List, profile, order history, status and notes |
| Reviews | Moderation and response |
| CMS | Homepage sections, banners, pages, menus and footer |
| Reports | Sales, orders, products, customers, stock and finance |
| Suppliers | Supplier records and purchase orders (optional MVP extension) |
| Staff | Users, roles and permission assignment |
| Activity logs | Searchable audit trail |
| Settings | Store, checkout, currency, tax, email, shipping, payments, SEO |
| System | Queue/job status, logs, backup status and health checks |

---

# 5. Storefront Functional Requirements

Requirement IDs are stable references for development and testing.

## 5.1 Homepage and content

- **FR-HOME-001:** The homepage shall display an optional announcement bar controlled by admin.
- **FR-HOME-002:** Admin shall create, edit, schedule, activate, deactivate and reorder promotional banners.
- **FR-HOME-003:** Banner records shall support desktop image, mobile image, alt text, headline, body, CTA label, destination URL, start/end dates and status.
- **FR-HOME-004:** Homepage sections shall be configurable, reorderable and independently visible/hidden.
- **FR-HOME-005:** Supported sections shall include hero, category tiles, featured collection, daily deals, new arrivals, best sellers, editorial/promotional feature, testimonials and newsletter.
- **FR-HOME-006:** The site shall render useful content when optional sections are disabled.
- **FR-HOME-007:** Admin shall manage footer links, contact details and social links.
- **FR-HOME-008:** The storefront shall not display draft, archived, scheduled-future or expired content.

## 5.2 Catalog, categories and collections

- **FR-CAT-001:** Visitors shall browse active products.
- **FR-CAT-002:** Categories shall support parent-child hierarchy.
- **FR-CAT-003:** A product may belong to multiple categories if configured.
- **FR-CAT-004:** Collections shall support manually selected products and rule-based membership in a later phase.
- **FR-CAT-005:** Product listings shall support pagination.
- **FR-CAT-006:** Product listings shall support sort by relevance, newest, price ascending, price descending and popularity where data exists.
- **FR-CAT-007:** Filters shall be generated from available catalog attributes and shall not show empty values.
- **FR-CAT-008:** Search shall match product name, SKU, description, tags and category names according to configured search rules.
- **FR-CAT-009:** Search input shall be safely normalized and bounded; search must not accept raw SQL or unsafe sort expressions.
- **FR-CAT-010:** URLs shall use stable SEO-friendly slugs with collision prevention.

## 5.3 Product detail and variants

- **FR-PROD-001:** A product shall have name, SKU or parent reference, slug, summary, full description, status and SEO fields.
- **FR-PROD-002:** A product shall support multiple images, alt text, captions, sort order and a primary image.
- **FR-PROD-003:** Product gallery shall support thumbnail selection and a larger image view.
- **FR-PROD-004:** A product shall support configurable attributes such as size, color, design, finish or material.
- **FR-PROD-005:** Variant combinations shall be explicitly created and stored; the application shall not assume every possible attribute combination exists.
- **FR-PROD-006:** Each sellable variant shall support unique SKU, price, optional compare-at price, stock policy, weight, status and inventory quantity where relevant.
- **FR-PROD-007:** The customer shall be required to select all mandatory options before adding a variant to cart.
- **FR-PROD-008:** Price and availability shall update when a variant is selected.
- **FR-PROD-009:** Unavailable variants shall be clearly marked and cannot be purchased unless made-to-order/backorder rules explicitly allow it.
- **FR-PROD-010:** Product detail shall show delivery/dispatch information where configured.
- **FR-PROD-011:** Product detail shall support related products and review summary.
- **FR-PROD-012:** Product changes shall not rewrite historical order item snapshots.

## 5.4 Inventory modes

The system must support three inventory modes, configurable at product level.

### A. Variant-level stock
- Each variant has its own available quantity.
- Example: small/gold has 12; large/gold has 4.
- Orders reserve or deduct stock for the exact selected variant.
- Variant stock changes are recorded as stock movements.

### B. Product-level stock
- A product has one shared stock pool.
- Variants may affect price or presentation but share the product's available quantity.
- The admin UI must make this shared-stock behavior explicit to prevent staff misunderstanding.

### C. Made-to-order
- Product/variant is produced after order.
- No fixed on-hand quantity is required.
- Optional production lead time, maximum order quantity and availability flag are supported.
- Admin may set a production capacity or order cap in a later phase.
- Customer sees an honest preparation estimate.
- Made-to-order orders must be visible in production/fulfilment queues.

Common inventory rules:
- **FR-INV-001:** Stock cannot become negative through normal checkout.
- **FR-INV-002:** Checkout must validate stock again immediately before order placement.
- **FR-INV-003:** Stock reservation expiry shall be configurable if temporary reservations are used.
- **FR-INV-004:** Cancellation and refund flows shall follow configured restock rules.
- **FR-INV-005:** Manual stock adjustments require a reason and an audit record.
- **FR-INV-006:** The system shall distinguish on-hand, reserved and available quantities where reservation is implemented.
- **FR-INV-007:** Low-stock thresholds shall be configurable per product/variant.
- **FR-INV-008:** Inventory history shall record source, quantity change, actor, timestamp and reference order/purchase/adjustment.
- **FR-INV-009:** Stock shall be changed atomically using database transactions and row-level locking or a safe equivalent.
- **FR-INV-010:** Bulk imports shall validate duplicate SKUs and invalid quantities before applying changes.

## 5.5 Cart and wishlist

- **FR-CART-001:** Visitors shall be able to use a session-based cart.
- **FR-CART-002:** Registered customers shall be able to retain a cart across sessions where practical.
- **FR-CART-003:** Cart lines shall identify product and exact variant, quantity and price snapshot for display; final price is recalculated at checkout.
- **FR-CART-004:** Customers shall change quantity and remove lines.
- **FR-CART-005:** Quantity shall be validated against stock and configured order limits.
- **FR-CART-006:** Coupon application shall be validated server-side.
- **FR-CART-007:** Cart shall display subtotal, discount, estimated shipping if available and estimated total.
- **FR-CART-008:** Cart merge after login shall handle duplicate variant lines and stock limits.
- **FR-WISH-001:** Registered customers shall save and remove wishlist products.
- **FR-WISH-002:** Wishlist products that become unavailable shall be shown as unavailable, not silently purchasable.

## 5.6 Checkout configuration

- **FR-CHK-001:** Admin shall choose one of two checkout policies:
  1. Guest checkout allowed, with account creation optional.
  2. Registration/login required before checkout.
- **FR-CHK-002:** The active policy shall be enforced on the server, not only by hiding UI.
- **FR-CHK-003:** If guest checkout is enabled, guest orders shall still have a contact email/phone and a secure order reference.
- **FR-CHK-004:** If registration is required, unauthenticated customers shall be redirected to sign in/register and returned to checkout safely.
- **FR-CHK-005:** Checkout shall collect contact, billing and shipping details required by the selected destination.
- **FR-CHK-006:** Checkout shall show available shipping methods based on destination, cart contents and configured rules.
- **FR-CHK-007:** Checkout shall show only enabled payment methods that are eligible for the order/destination.
- **FR-CHK-008:** Checkout shall recalculate product prices, discounts, shipping and tax on the server.
- **FR-CHK-009:** Checkout shall require acceptance of applicable terms/policies.
- **FR-CHK-010:** The system shall prevent duplicate submissions using an idempotency token or equivalent.
- **FR-CHK-011:** Order placement shall create the order and order items in a database transaction.
- **FR-CHK-012:** The customer shall receive an order confirmation page and, where configured, email.
- **FR-CHK-013:** A failed order transaction shall not leave partially deducted stock or orphaned order records.

## 5.7 Customer accounts

- **FR-AUTH-001:** Customers shall register using configured required fields.
- **FR-AUTH-002:** Passwords shall be hashed using PHP's password hashing API.
- **FR-AUTH-003:** Email verification shall be configurable and recommended.
- **FR-AUTH-004:** Password reset shall use single-use, expiring, hashed tokens.
- **FR-AUTH-005:** Login endpoints shall be rate-limited.
- **FR-AUTH-006:** Session identifiers shall be regenerated after authentication and privilege changes.
- **FR-ACC-001:** Customers shall update profile details subject to validation.
- **FR-ACC-002:** Customers shall manage multiple shipping addresses.
- **FR-ACC-003:** Customers shall view only their own orders.
- **FR-ACC-004:** Customers shall view order status history and tracking information.
- **FR-ACC-005:** Customers shall request cancellation/return only when order state and policy allow.
- **FR-ACC-006:** Customers shall manage wishlist and password.
- **FR-ACC-007:** Account deletion requests shall preserve records that must legally/operationally be retained, while removing or anonymizing eligible personal data under approved policy.

---

# 6. Admin Functional Requirements

## 6.1 Dashboard

- **FR-ADM-001:** Dashboard shall show date-filtered gross sales, order count, average order value and order status counts.
- **FR-ADM-002:** Dashboard shall show recent orders and their current status.
- **FR-ADM-003:** Dashboard shall show low-stock and out-of-stock alerts.
- **FR-ADM-004:** Dashboard shall show top products where reliable order data exists.
- **FR-ADM-005:** Dashboard widgets shall respect user permissions.
- **FR-ADM-006:** Date range and timezone must be clear.
- **FR-ADM-007:** Dashboard totals shall have documented definitions (e.g., placed orders vs paid orders; gross vs net).

## 6.2 Product administration

- **FR-ADM-PROD-001:** Authorized staff shall create, edit, duplicate, activate, deactivate and archive products.
- **FR-ADM-PROD-002:** Admin shall upload multiple product images with file type, size and dimension validation.
- **FR-ADM-PROD-003:** Admin shall reorder images and select a primary image.
- **FR-ADM-PROD-004:** Admin shall define attributes and variant combinations.
- **FR-ADM-PROD-005:** Admin shall manage SKU, price, sale price, stock mode, quantity, weight and lead time.
- **FR-ADM-PROD-006:** Admin shall assign categories, collections and tags.
- **FR-ADM-PROD-007:** Admin shall preview product before publishing.
- **FR-ADM-PROD-008:** Admin shall import/export catalog data using a documented CSV/XLSX template.
- **FR-ADM-PROD-009:** Import shall provide a validation report and safe preview before commit.
- **FR-ADM-PROD-010:** Admin shall bulk update permitted fields with a confirmation summary.

## 6.3 Categories and collections

- **FR-ADM-CAT-001:** Staff shall create, edit, reorder, activate and archive categories.
- **FR-ADM-CAT-002:** Category records shall support parent category, description, image, slug and SEO fields.
- **FR-ADM-CAT-003:** The system shall prevent circular category parent relationships.
- **FR-ADM-COL-001:** Staff shall create and edit collections with title, description, image, slug and schedule.
- **FR-ADM-COL-002:** Collections shall support manual product selection in MVP.
- **FR-ADM-COL-003:** Removing a product from a collection shall not delete the product.

## 6.4 Promotions and coupons

- **FR-PROMO-001:** Staff shall create fixed-value and percentage discounts.
- **FR-PROMO-002:** Promotions shall support start/end times and active state.
- **FR-PROMO-003:** Coupon rules shall support minimum subtotal, eligible products/categories, usage limit, per-customer limit and expiry.
- **FR-PROMO-004:** The system shall define whether discounts stack; default is no stacking.
- **FR-PROMO-005:** The server shall validate eligibility at checkout and again at order placement.
- **FR-PROMO-006:** Discount calculations shall use decimal-safe arithmetic and defined rounding.
- **FR-PROMO-007:** Admin shall see coupon usage and remaining limits.

## 6.5 Order management

- **FR-ORD-001:** Staff shall search/filter orders by order number, date, customer, status, payment state and fulfilment state.
- **FR-ORD-002:** Order detail shall show customer snapshot, address snapshot, items, variant details, totals, payment, shipment, notes and history.
- **FR-ORD-003:** Order statuses shall include at minimum Pending Confirmation, Confirmed, Processing, Dispatched, Delivered, On Hold and Cancelled.
- **FR-ORD-004:** Payment state shall be separate from order state.
- **FR-ORD-005:** Staff shall confirm orders subject to permission and validation.
- **FR-ORD-006:** Staff shall record processing notes and internal notes.
- **FR-ORD-007:** Dispatch shall record courier, service, tracking reference and dispatch timestamp when applicable.
- **FR-ORD-008:** Delivery completion shall record timestamp and optional proof/reference.
- **FR-ORD-009:** Cancellation shall require a reason and follow stock-restoration policy.
- **FR-ORD-010:** Every status transition shall be recorded with actor, timestamp and note.
- **FR-ORD-011:** Admin shall print invoice, packing slip, picking list and shipping label.
- **FR-ORD-012:** Admin shall export filtered order lists.
- **FR-ORD-013:** Partial fulfilment and partial refund are future-capable but must not be shown as supported until implemented and tested.
- **FR-ORD-014:** Historical order line names, SKU, variant label, price, tax and discount shall remain immutable snapshots.

### Suggested order lifecycle

`Pending Confirmation → Confirmed → Processing → Dispatched → Delivered`

Alternative transitions:
- Pending Confirmation → On Hold
- Pending Confirmation → Cancelled
- Confirmed → On Hold
- Confirmed → Cancelled (subject to fulfilment/payment state)
- Processing → Dispatched
- Processing → Cancelled (with stock/payment rules)
- Dispatched → Delivered
- Dispatched → Delivery Exception / Returned (future extension)

The application must define allowed transitions and reject invalid transitions. Payment state remains independent: Pending, Awaiting Verification, Paid, Failed, Partially Refunded, Refunded.

## 6.6 Payment management

- **FR-PAY-001:** Initial payment methods shall include COD and bank transfer.
- **FR-PAY-002:** Admin shall enable/disable each payment method.
- **FR-PAY-003:** Bank transfer instructions shall be editable in settings and displayed only when that method is selected.
- **FR-PAY-004:** If customer proof upload is enabled, files shall be validated and stored privately.
- **FR-PAY-005:** Authorized staff shall approve/reject a bank transfer and record reference, amount, date and reviewer.
- **FR-PAY-006:** COD shall be represented as a payment method with collection state tracked separately.
- **FR-PAY-007:** Payment records shall preserve provider/reference, amount, currency, status and timestamps.
- **FR-PAY-008:** Refunds shall require permission, reason and transaction record.
- **FR-PAY-009:** Never store raw card numbers, CVV or sensitive card credentials.
- **FR-PAY-010:** Future gateway callbacks shall be authenticated, signature-verified, idempotent and logged safely.

## 6.7 Shipping management

- **FR-SHIP-001:** Admin shall configure manual shipping methods and rates.
- **FR-SHIP-002:** Shipping configuration shall support zones/countries/regions and enable/disable state.
- **FR-SHIP-003:** Rate strategies shall include flat rate, free shipping threshold and weight-based rate where configured.
- **FR-SHIP-004:** Shipping method availability shall be recalculated from destination and cart data.
- **FR-SHIP-005:** Admin shall create shipment records and record courier, tracking number, dispatch date and tracking URL.
- **FR-SHIP-006:** Manual fulfilment shall work without any courier API.
- **FR-SHIP-007:** Courier integrations shall be adapter-based, with credentials stored outside public source files.
- **FR-SHIP-008:** API failure shall not prevent staff from completing a manual dispatch.
- **FR-SHIP-009:** Courier status polling/webhooks shall be idempotent and logged.
- **FR-SHIP-010:** International shipping shall allow country-specific availability, rates, delivery estimates and restrictions.

## 6.8 Customer administration

- **FR-CUST-001:** Staff shall search customers by name, email, phone and customer ID.
- **FR-CUST-002:** Customer detail shall show profile, addresses, order history, totals and notes according to permission.
- **FR-CUST-003:** Staff shall block/unblock accounts with reason and audit entry.
- **FR-CUST-004:** Staff shall not view plaintext passwords.
- **FR-CUST-005:** Sensitive profile edits shall be audited.
- **FR-CUST-006:** Customer communication preferences shall be respected.

## 6.9 CMS and website settings

- **FR-CMS-001:** Admin shall manage announcement bar content and schedule.
- **FR-CMS-002:** Admin shall manage banners and hero slides with desktop/mobile assets.
- **FR-CMS-003:** Admin shall manage homepage section visibility and order.
- **FR-CMS-004:** Admin shall manage static pages and policy content.
- **FR-CMS-005:** Admin shall manage navigation and footer menus.
- **FR-CMS-006:** Admin shall manage social/contact details.
- **FR-CMS-007:** Admin shall preview content before publishing.
- **FR-CMS-008:** Rich text content shall be sanitized using an allowlist.
- **FR-CMS-009:** Admin shall manage SEO title, meta description, canonical URL and social preview image where appropriate.

## 6.10 Reports

Required reports:
- Sales by date range.
- Orders by status.
- Sales by payment method.
- Sales by shipping destination.
- Product and variant sales.
- Best-selling products.
- Low-stock/out-of-stock report.
- Inventory movement report.
- Coupon usage.
- Customer acquisition and repeat purchase.
- Refund/cancellation report.
- Bank-transfer reconciliation report.

Reports shall state whether totals include cancelled, unpaid, refunded or tax amounts. Export must respect permissions and active filters.

---

# 7. Business Rules and Workflows

## 7.1 Product publication workflow

1. Catalog Manager creates a draft product.
2. Adds images, descriptions, categories and collections.
3. Defines attributes and valid variant combinations.
4. Sets prices and inventory mode.
5. Reviews SKU uniqueness, image quality, stock and SEO fields.
6. Previews product.
7. Publishes product.
8. Product becomes visible only if active, within any publication schedule and purchasable.

## 7.2 Checkout workflow

1. Customer selects a product and exact variant.
2. Server validates product status and current variant availability.
3. Cart stores product/variant reference and quantity.
4. Customer proceeds to checkout.
5. Checkout policy is read from store settings.
6. Customer supplies contact and delivery details.
7. Server determines eligible shipping methods.
8. Customer selects COD or bank transfer.
9. Server recalculates item prices, promotion, shipping and taxes.
10. Customer reviews final total and submits.
11. Server validates idempotency token and rechecks stock/coupon.
12. Database transaction creates order and immutable order items.
13. Inventory is reserved/deducted according to the chosen stock strategy.
14. Order is assigned initial status and payment state.
15. Confirmation is shown and notifications are queued/sent.
16. If any transactional step fails, transaction rolls back.

**Stock policy decision:** Choose and document whether stock is deducted on order placement or reserved until confirmation. Recommended initial policy: reserve stock at successful order placement, then commit deduction on confirmation or keep reservation until cancellation/expiry. For simpler shared-hosting MVP, immediate deduction at order placement with automatic restock on cancellation can be used, but must be explicit and tested. Do not mix both approaches.

## 7.3 Bank transfer workflow

1. Customer selects bank transfer.
2. Checkout displays configured account instructions and payment reference.
3. Order is created with payment state `Awaiting Verification`.
4. Customer may submit proof if that feature is enabled.
5. Finance staff checks bank statement independently.
6. Staff approves or rejects with a note.
7. Approval records verified amount, date, reference and staff actor.
8. Customer is notified of the result.
9. An uploaded receipt alone is never treated as proof of cleared funds.

## 7.4 COD workflow

1. Customer selects COD.
2. Order is created with payment state `Pending Collection`.
3. Staff confirms and prepares order.
4. Shipment is dispatched.
5. On delivery, payment state becomes `Paid/Collected` only after collection confirmation.
6. Failed delivery and return-to-origin are recorded separately.

## 7.5 Return/refund workflow

1. Customer submits a request or staff creates it.
2. System checks order state, product eligibility and return window.
3. Staff approves/rejects with reason.
4. Returned goods are inspected where relevant.
5. Inventory is restocked only if policy and condition permit.
6. Refund is recorded against original payment method or manual payout.
7. Customer and order timeline are updated.
8. All financial adjustments are auditable.

## 7.6 Product and order audit workflow

Every important mutation should capture:
- Actor user ID (or system process).
- Action name.
- Entity type and ID.
- Timestamp.
- Before/after values for appropriate fields.
- Reason/comment where required.
- IP/user-agent where lawful and useful.
- Request/correlation ID.

Do not store passwords, card secrets, session tokens or full bank credentials in logs.

---

# 8. Data Model and Database Requirements

The following is a logical schema inventory. Final SQL types, indexes and constraints must be specified in the database design phase.

## 8.1 Core tables

### Identity and access
- `admin_users`
- `customers`
- `roles`
- `permissions`
- `role_permissions`
- `admin_user_roles`
- `customer_addresses`
- `password_reset_tokens`
- `email_verification_tokens`
- `login_attempts`
- `activity_logs`

### Catalog
- `products`
- `product_images`
- `categories`
- `product_categories`
- `collections`
- `collection_products`
- `tags`
- `product_tags`
- `attributes`
- `attribute_values`
- `product_attributes`
- `product_variants`
- `variant_attribute_values`

### Inventory
- `inventory`
- `stock_movements`
- `stock_reservations`
- `inventory_adjustments`
- `suppliers` (optional first release)
- `purchase_orders` (optional first release)
- `purchase_order_items` (optional first release)

### Shopping and orders
- `carts`
- `cart_items`
- `wishlists`
- `wishlist_items`
- `orders`
- `order_items`
- `order_status_history`
- `order_notes`
- `order_addresses`
- `order_coupons`

### Payments and shipping
- `payment_methods`
- `payments`
- `payment_transactions`
- `payment_proofs`
- `refunds`
- `shipping_zones`
- `shipping_zone_locations`
- `shipping_methods`
- `shipping_rates`
- `shipments`
- `shipment_events`

### Promotions and content
- `promotions`
- `promotion_products`
- `coupons`
- `coupon_redemptions`
- `banners`
- `homepage_sections`
- `pages`
- `menus`
- `menu_items`
- `reviews`
- `newsletter_subscribers`
- `notifications`
- `email_logs`

### Configuration and system
- `store_settings`
- `tax_rules`
- `currencies`
- `countries`
- `jobs` (only if a custom database queue is used)
- `failed_jobs`
- `backup_logs`
- `system_logs`

## 8.2 Data integrity requirements

- Use InnoDB.
- Use foreign keys for core relationships where supported by the deployment.
- Use `DECIMAL(p,s)` for monetary amounts; never use floating-point values for money.
- Store currency code with monetary values.
- Store order totals as snapshots.
- Use UTC for stored timestamps; render in configured store/customer timezone.
- Use unique constraints for product slugs, SKU, order number and relevant account identifiers.
- Index foreign keys and common filters.
- Use transactions for order placement, stock movement, payment state changes and refunds.
- Use soft-delete/archive only where restoration and reporting are meaningful; do not soft-delete financial history indiscriminately.
- Keep order item and address snapshots independent from later customer/product edits.
- Use explicit status columns or lookup tables with constrained values.
- Avoid storing redundant totals unless the update rules are clear and tested.

## 8.3 Order snapshot fields

Each order item should preserve:
- Product ID reference (nullable if product later archived/deleted).
- Variant ID reference (nullable if variant later archived).
- Product name at purchase.
- SKU at purchase.
- Variant label at purchase.
- Unit price.
- Quantity.
- Line subtotal.
- Discount allocation.
- Tax amount/rate if applicable.
- Line total.
- Currency.
- Optional image reference/snapshot for invoice rendering.

Order should preserve billing/shipping address snapshots, currency, subtotal, discount, shipping, tax, grand total, payment method label, customer contact snapshot and applicable policy/version reference.

---

# 9. API and Application Interface Requirements

The storefront can use PHP page rendering for initial HTML and JSON endpoints for interactive operations.

Suggested endpoint families:
- `GET /api/products`
- `GET /api/products/{slug}`
- `GET /api/categories`
- `GET /api/search`
- `GET /api/cart`
- `POST /api/cart/items`
- `PATCH /api/cart/items/{id}`
- `DELETE /api/cart/items/{id}`
- `POST /api/coupons/validate`
- `POST /api/checkout/quote`
- `POST /api/orders`
- `GET /api/orders/{public-reference}` (with secure verification)
- `POST /api/auth/login`
- `POST /api/auth/register`
- `POST /api/auth/logout`
- `GET/PATCH /api/account/profile`
- `GET /api/account/orders`

Admin endpoints must be separately protected and permission checked.

API rules:
- Validate HTTP method, content type, payload size and schema.
- Return consistent JSON envelopes and appropriate HTTP status codes.
- Do not expose stack traces or SQL errors.
- Use CSRF protection for cookie-authenticated state-changing requests.
- Rate-limit login, search, coupon checks and order creation.
- Never trust client-submitted price, discount, stock, role, payment status or order total.
- Use idempotency for checkout and webhook processing.
- Log request IDs and safe operational metadata.
- Paginate list endpoints and cap page size.

---

# 10. Security Requirements

## 10.1 Application security

- Use PDO prepared statements for all database input.
- Escape output according to HTML, attribute, URL and JavaScript context.
- Sanitize rich text using a strict allowlist.
- Apply CSRF tokens to state-changing requests.
- Use secure, HttpOnly, SameSite session cookies.
- Enable HTTPS and secure cookie flags in production.
- Regenerate session ID after login and privilege changes.
- Hash passwords using `password_hash()` with a modern supported algorithm.
- Use `password_needs_rehash()` during login.
- Rate-limit login, password reset, registration and sensitive endpoints.
- Apply account lockout/backoff without enabling easy denial-of-service against known users.
- Validate file MIME type, extension, size, dimensions and actual image decoding.
- Rename uploads with random identifiers; never trust original filenames.
- Store sensitive uploads outside public web root.
- Deny script execution in media directories.
- Protect against path traversal and unsafe file deletion.
- Restrict admin access by permission on every request.
- Require re-authentication for especially sensitive operations where practical.
- Use optional two-factor authentication for administrators.
- Keep secrets and credentials out of source control and public directories.
- Use least-privilege database credentials.
- Disable production error display; log privately.
- Apply security headers including Content-Security-Policy appropriate to the site, frame protections, content-type sniffing protection and referrer policy.
- Keep PHP, libraries and hosting software patched.

## 10.2 Payment security

- Initial COD and bank transfer flows must be auditable.
- Bank transfer proof upload must not auto-mark payment as paid.
- Future gateways must verify signatures/callbacks server-side.
- Webhooks must be idempotent and replay-resistant.
- Never store full card number, CVV or raw payment credentials.
- Store only necessary provider references and payment status data.

## 10.3 Privacy

- Collect only required personal information.
- Publish privacy and cookie disclosures appropriate to the markets served.
- Restrict staff access to customer personal data.
- Define retention periods for orders, invoices, payment records and logs.
- Support customer access/export/deletion requests subject to applicable legal retention.
- Avoid exposing customer data in URLs, analytics events or error messages.

## 10.4 Operational security

- Scheduled backups must be encrypted and stored off-server.
- Test restore procedures periodically.
- Use separate development, staging and production environments.
- Restrict database administration tools.
- Maintain audit logs for staff and system actions.
- Monitor failed login spikes, repeated checkout failures and suspicious admin activity.

---

# 11. Non-Functional Requirements

## 11.1 Performance

- **NFR-PERF-001:** Core pages should be optimized for fast first render on typical mobile connections.
- **NFR-PERF-002:** Use server-side pagination and bounded queries.
- **NFR-PERF-003:** Images shall be resized and compressed; use WebP/AVIF where supported with fallback.
- **NFR-PERF-004:** Use lazy loading below the fold.
- **NFR-PERF-005:** Avoid loading large JavaScript libraries for simple interactions.
- **NFR-PERF-006:** Cache safe public catalog/content data where feasible.
- **NFR-PERF-007:** Never cache private account, cart, checkout or admin responses in a shared public cache.
- **NFR-PERF-008:** Database query plans and slow queries shall be reviewed before launch.

Suggested initial targets (to be validated on the actual hosting plan):
- Storefront cached page TTFB: under 800 ms at the 75th percentile.
- Main product listing query: under 300 ms under representative test data.
- Checkout server processing: under 2 seconds excluding external provider latency.
- Core Web Vitals should be measured on real mobile devices and optimized toward Google's good thresholds.

These are targets, not guarantees; hosting resources, network, traffic and third-party services affect results.

## 11.2 Scalability

- The application shall avoid storing session state only in local files if multiple servers are later required.
- Business logic shall be modular and not tied to one server's filesystem.
- Media storage shall have an abstraction so local storage can later move to object storage.
- Email and non-critical work shall be queue-capable.
- Cache invalidation shall be explicit after product, category and content updates.
- Database queries shall be index-aware and paginated.
- The system shall be load-tested against defined traffic scenarios.

**Capacity clarification:** One million registered users is a data-volume and account-management requirement. One million simultaneous active users is a major distributed-systems requirement. Shared hosting is suitable only for an initial low/moderate-traffic launch subject to provider limits. Do not market or certify million-concurrent capacity without load tests and an appropriately sized multi-server architecture.

## 11.3 Availability and recovery

- Target initial availability should be agreed with hosting provider.
- Backups should include database and uploaded media.
- Suggested baseline: daily full backup plus more frequent database backups where hosting supports them.
- Define Recovery Point Objective (RPO) and Recovery Time Objective (RTO).
- Restore testing must be performed, not merely backup creation.
- Deployments should include rollback instructions.

## 11.4 Usability and accessibility

- Responsive from small mobile screens through desktop.
- Keyboard-operable navigation, dialogs, forms and menus.
- Visible focus states.
- Labels and useful validation messages.
- Sufficient contrast.
- Alt text for meaningful product and banner imagery.
- Do not rely on color alone to communicate stock/order status.
- Clear empty, loading, success and error states.
- Avoid intrusive popups and excessive animation.

## 11.5 Compatibility

Support current major versions of Chrome, Edge, Firefox and Safari, plus modern Android/iOS browsers. Test the actual shared-hosting PHP and MySQL versions before deployment.

## 11.6 Maintainability

- Consistent naming and formatting.
- Shared reusable layouts and helpers.
- No duplicated checkout or price-calculation logic.
- Documented configuration and deployment steps.
- Versioned database migrations or controlled SQL upgrade scripts.
- Automated tests for critical business rules.
- Clear separation of public, admin, account, API, private storage and configuration files.

---

# 12. Shared Hosting Deployment Plan

## 12.1 Initial deployment shape

- PHP application on Apache shared hosting.
- MySQL/MariaDB database supplied by hosting provider.
- HTTPS enabled.
- Public document root points only to public storefront files.
- Admin/account paths protected by application authentication.
- Private configuration, logs, backups and payment proofs outside public web root where hosting allows.
- If the provider forces the project into `public_html`, use deny rules and carefully separate private directories; preferably choose a host that supports a public document root and private storage.
- Use cron jobs only where provider permits; otherwise use controlled scheduled requests with secret tokens or hosting scheduler.
- Use SMTP credentials supplied by a trusted email provider.
- Store media locally initially with image optimization and strict limits.

## 12.2 Shared hosting limitations

Potential constraints:
- CPU and concurrent process limits.
- PHP execution time and memory limits.
- Database connection and query limits.
- No Redis or persistent worker processes.
- Restricted cron frequency.
- Limited SSH/Composer access.
- Storage and inode limits.
- No load balancer or horizontal scaling.
- Provider-specific upload and email restrictions.

The implementation must degrade gracefully where advanced services are unavailable. A database-backed lightweight queue or cron-driven job runner may be used initially, but it must have locking, retries and failed-job visibility.

## 12.3 Migration path

1. Shared hosting: launch and validate the business workflow.
2. Managed VPS: move PHP application, database and private storage; configure backups and monitoring.
3. Performance tier: add Redis, CDN, queue workers and object storage.
4. Scale tier: load balancer, multiple PHP app instances, database primary/replicas, centralized sessions and observability.
5. High-concurrency tier: capacity testing, traffic shaping, read/write strategy, regional CDN and specialist infrastructure design.

---

# 13. SEO, Analytics and Marketing

- Editable title and meta description per product/category/collection/page.
- Canonical URLs.
- XML sitemap for active indexable content.
- Robots.txt configuration.
- Open Graph/social sharing metadata.
- Product structured data with accurate price, availability and currency.
- Breadcrumb structured data where appropriate.
- Clean slugs and redirect handling when slugs change.
- Search-friendly category descriptions.
- Analytics integration with consent/privacy controls appropriate to the markets served.
- Campaign/source tracking that does not leak personal data.
- Newsletter subscription with consent and unsubscribe handling.
- Abandoned-cart reminders only with lawful consent and configurable limits.

---

# 14. Notifications

Notification events may include:
- Account verification.
- Password reset.
- Order placed.
- Order confirmed.
- Bank transfer awaiting verification.
- Bank transfer accepted/rejected.
- Order processing.
- Order dispatched with tracking.
- Order delivered.
- Order cancelled.
- Refund status changed.
- Low-stock alert.
- New order alert to staff.
- Abandoned cart reminder (optional).

Templates should be editable or at least centrally managed. Notifications should not block checkout. Failed delivery should be logged and retried safely.

---

# 15. Testing and Quality Assurance

## 15.1 Test levels

- Unit tests for pricing, coupon rules, shipping calculation, permissions and stock logic.
- Integration tests for database transactions, order creation and payment status transitions.
- Functional tests for customer and admin journeys.
- Security tests for authentication, authorization, CSRF, XSS, SQL injection and upload validation.
- Responsive visual checks.
- Browser compatibility checks.
- Backup/restore test.
- Performance and load tests.
- User acceptance testing with real staff workflows.

## 15.2 Critical test scenarios

1. Guest checkout enabled: visitor places a COD order successfully.
2. Guest checkout disabled: visitor must authenticate before checkout.
3. Variant-level stock: purchase of one variant does not incorrectly reduce another.
4. Product-level stock: all variants share the configured pool.
5. Made-to-order: purchase succeeds without fixed stock and shows lead time.
6. Two simultaneous purchases cannot oversell the last unit.
7. Invalid coupon is rejected server-side.
8. Expired promotion is not applied.
9. Bank-transfer proof upload does not automatically mark payment paid.
10. Unauthorized staff cannot access restricted admin actions by direct URL/API.
11. Customer A cannot access Customer B's order by changing an ID.
12. Duplicate checkout submission does not create duplicate orders.
13. Cancellation follows stock-restoration policy exactly once.
14. Historical invoice remains unchanged after product price/name edit.
15. Invalid image/script upload is rejected.
16. Courier API failure still permits manual shipment entry.
17. Search and listing pagination work with a large catalog.
18. Admin changes to announcement/banner appear on storefront after cache invalidation.
19. Failed email does not roll back an already placed order.
20. Database backup can be restored successfully.

## 15.3 Acceptance criteria

A module is accepted only when:
- Its defined requirements are implemented.
- Permission checks are tested.
- Validation and error states are present.
- Critical data changes are audited.
- Responsive layouts are checked.
- No critical/high-severity defect remains open.
- Relevant test evidence is recorded.
- User acceptance owner signs off.

---

# 16. Delivery Plan and Dependencies

Recommended build sequence:

1. Discovery and final business rules.
2. Database schema and application foundation.
3. Authentication, roles, permissions and audit.
4. Product, variant, category and collection management.
5. Storefront shell, homepage and catalog.
6. Cart, checkout policy and customer accounts.
7. Inventory transaction model and order creation.
8. Order management and print documents.
9. COD and bank-transfer workflow.
10. Manual shipping and courier adapter foundation.
11. CMS, banners, promotions and coupons.
12. Reports, notifications and SEO.
13. Security, performance and UAT.
14. Shared-hosting deployment and operational handover.

Dependencies:
- Product and variant schema before catalog UI.
- Stock policy before checkout/order transaction implementation.
- Shipping zones/rates before final checkout total calculation.
- Payment state model before order status dashboard.
- Permission model before admin module rollout.
- CMS schema before editable homepage sections.
- Hosting capability audit before final deployment configuration.

---

# 17. Risks and Mitigations

| Risk | Mitigation |
|---|---|
| Shared hosting resource limits | Benchmark early; define upgrade trigger |
| Overselling stock | Transactional stock validation and concurrency tests |
| Incorrect order totals | Central server-side pricing service |
| Duplicate orders | Idempotency key and transaction boundaries |
| Fake bank-transfer proof | Manual statement verification |
| Courier API unavailable | Manual shipping fallback |
| Unsafe image uploads | Strict validation and private storage |
| Admin misuse | RBAC, audit logs and least privilege |
| Slow catalog pages | Indexes, pagination, caching and optimized images |
| Scope creep | MVP acceptance criteria and change control |
| International shipping complexity | Country zones and explicit rate rules |
| Data loss | Off-server backups and restore drills |
| False scalability claims | Measured load tests and capacity reports |

---

# 18. Release Definition

## MVP release must include
- Storefront homepage, catalog, product detail, search and category/collection pages.
- Product and variant management.
- All three inventory modes.
- Customer account and configurable checkout policy.
- Cart and checkout.
- COD and bank transfer.
- Manual shipping rules and dispatch tracking.
- Order management and print documents.
- Basic promotions/coupons.
- Homepage announcement/banner controls.
- Admin roles and permissions.
- Core reports.
- Security baseline, backups and deployment documentation.

## Post-MVP
- Courier API integrations.
- Advanced returns/refunds.
- Advanced purchase/supplier management.
- Automated abandoned-cart campaigns.
- Reviews and loyalty.
- Multi-language and multi-currency display.
- Advanced analytics.
- Redis, object storage, workers and multi-server deployment.

---

# 19. Glossary

- **Product:** The parent catalog item shown to shoppers.
- **Variant:** A specific sellable configuration, such as size + color.
- **SKU:** Unique stock-keeping identifier for a product/variant.
- **Collection:** A curated group of products.
- **Category:** Hierarchical classification used for navigation.
- **Made-to-order:** A product prepared after a customer order rather than drawn from fixed stock.
- **On-hand stock:** Physical quantity recorded as available at the business.
- **Reserved stock:** Quantity temporarily allocated to open orders.
- **Available stock:** Quantity that can currently be sold.
- **Order status:** Fulfilment/business progress.
- **Payment status:** State of money collection or settlement.
- **Shipment:** A delivery consignment linked to an order.
- **Coupon:** A code or rule that grants a discount.
- **CMS:** Content management tools for editable website content.
- **RBAC:** Role-based access control.
- **Idempotency:** Repeating the same request does not create duplicate effects.

---

# 20. Final Implementation Principles

1. Build a reliable commerce workflow before adding decorative complexity.
2. Keep all business-critical calculations on the server.
3. Treat order history and financial records as immutable snapshots.
4. Separate order state, payment state and shipment state.
5. Support all three inventory modes explicitly.
6. Make guest-vs-account checkout a setting with server-side enforcement.
7. Keep manual shipping and payment operations usable even when integrations are unavailable.
8. Use the supplied visual reference as a design direction, not as a rigid template.
9. Launch on shared hosting only within measured resource limits.
10. Design clear migration seams for future infrastructure.
11. Never promise one million simultaneous users without architecture, capacity tests and evidence.
12. Maintain this SRS as the source of truth; approved changes should be versioned and reflected in tests and implementation tasks.
