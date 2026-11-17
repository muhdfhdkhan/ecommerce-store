# Embroidery E-Commerce & Management System — Project Brief

**Document type:** Master Project Brief (pre-SRS)
**Version:** 1.0
**Status:** Draft for confirmation
**Currency / Language (initial):** PKR / English
**Primary market:** Pakistan (international-ready)

---

## Table of Contents

1. Executive Summary
2. Project Vision, Goals and Success Metrics
3. Business Context and Assumptions
4. Stakeholders and User Personas
5. Scope (In Scope / Out of Scope)
6. **Responsive & Mobile-First Design Requirements**
7. System Architecture Overview
8. Technology Stack
9. Customer Website — Functional Requirements
10. Product Management
11. Categories, Collections and Promotions
12. Cart, Checkout and Pricing Rules
13. Order Management
14. Customer Account Management
15. Shipping and Delivery
16. Payment Management
17. Inventory, Suppliers and Purchasing
18. Admin Software — Modules and Dashboard
19. Roles and Permissions
20. CMS, SEO and Notifications
21. Reports and Analytics
22. Security Requirements
23. Scalability and Performance Architecture
24. Database Design
25. Folder Structure
26. API and Integration Overview
27. Non-Functional Requirements
28. Testing and Quality Assurance
29. Deployment, Backup and Operations
30. Development Roadmap (Phases)
31. Risks and Mitigations
32. Open Decisions (to Confirm)
33. Deliverables and Next Steps
34. Glossary

---

## 1. Executive Summary

This project is a complete **E-Commerce Business Management Platform** for an embroidery business selling patches, motifs, neckline designs, sleeve motifs, bridal embroidery and related products. It consists of two interconnected applications sharing one PHP backend and one MySQL/MariaDB database:

1. **Customer Website (Storefront):** A professional online store where customers browse products, create accounts (or check out as guests), add items to the cart, place orders, pay, and track deliveries.
2. **Admin Management Software:** A private portal where the business manages products, categories, collections, inventory, customers, orders, payments, shipping, promotions, website content and reports.

Changes made in the admin portal must be reflected on the storefront immediately wherever applicable (prices, stock, banners, visibility, promotions).

**Core principles**

- **Mobile-first, responsive, easy to use on a phone** (both storefront and admin).
- **Security by design** (not added at the end).
- **One source of truth** for business rules (pricing, stock, discounts, shipping) on the server.
- **Modular, horizontally scalable** architecture.
- **Core PHP, no Laravel, no React frontend**, as per the preferred architecture.

---

## 2. Project Vision, Goals and Success Metrics

### 2.1 Vision

To give the business a single system to sell embroidery products online and run daily operations — from product upload to order delivery — without depending on a generic template or multiple disconnected tools.

### 2.2 Business Goals

| # | Goal | Description |
|---|------|-------------|
| G1 | Sell online | Customers can discover, choose variants (size/color) and buy easily |
| G2 | Operate efficiently | Staff can process orders, print documents and ship from one place |
| G3 | Control inventory | Variant-level stock with movement history to avoid overselling |
| G4 | Control the website | Banners, homepage sections and pages managed without code changes |
| G5 | Grow safely | Architecture can scale from a small VPS to a clustered setup |
| G6 | Be mobile-friendly | Most Pakistani e-commerce traffic is mobile; the experience must be excellent on phones |

### 2.3 Success Metrics (KPIs)

| Metric | Target (initial) |
|--------|------------------|
| Mobile Lighthouse performance score | ≥ 85 |
| Largest Contentful Paint (mobile, 4G) | ≤ 2.5 s |
| Checkout completion on mobile | Measured and ≥ desktop rate minus 10% |
| Time for staff to process an order (confirm → dispatch) | ≤ 3 minutes of staff effort |
| Overselling incidents | 0 |
| Admin task completion on phone (add product, update order status) | Possible without horizontal scrolling or zooming |
| Uptime | ≥ 99.5% |

---

## 3. Business Context and Assumptions

### 3.1 Business Context

- The business sells embroidery products where the **same design may exist in several sizes and colors**, each with its own price, SKU and stock.
- Customers are primarily in Pakistan, with possible international orders later.
- Common local payment preferences: **Cash on Delivery (COD)**, bank transfer, JazzCash, Easypaisa; cards via a gateway.
- Common local couriers: TCS, Leopards, PostEx and others (API access subject to account approval).

### 3.2 Default Assumptions (used until confirmed)

| Area | Assumed default |
|------|-----------------|
| Target market | Pakistan initially, international-ready |
| Checkout | Guest and registered customers both allowed |
| Inventory | Variant-level stock tracking (made-to-order flag supported per product) |
| Shipping | Manual configuration first; courier APIs added in a later phase |
| Payments | COD, bank transfer, JazzCash, Easypaisa, card gateway (phased) |
| Hosting | Cloud VPS, designed to scale |
| Currency | PKR; multi-currency ready in the data model |
| Language | English; Urdu/RTL ready in the layout and database |

---

## 4. Stakeholders and User Personas

### 4.1 Stakeholders

- Business owner (Super Admin)
- Store manager and operations staff
- Accountant
- Content/marketing staff
- Customers (registered and guest)
- Courier and payment providers
- Developer(s) / maintainers

### 4.2 Personas

**Persona A — Ayesha, mobile shopper (customer)**
Browses on a mid-range Android phone over a 4G connection. Wants fast loading, large product photos, easy size/color selection with big tap targets, and simple checkout with COD. Often arrives from Instagram/Facebook/WhatsApp links.

**Persona B — Hamza, returning customer**
Logs in, reorders previous items, checks order tracking, saves multiple addresses.

**Persona C — Bilal, order manager (staff)**
Uses a phone and a desktop. Needs to see new orders quickly, change status, enter tracking numbers, and print packing slips and labels.

**Persona D — Sana, product manager**
Uploads products with multiple images from her phone camera/gallery, edits prices and stock, creates collections for seasonal campaigns.

**Persona E — Owner**
Checks the dashboard on a phone at any time: today's sales, pending orders, low stock.

---

## 5. Scope

### 5.1 In Scope

- Public storefront with catalog, search, product details, cart, checkout, accounts, wishlist, order tracking, static pages and contact form.
- Admin portal for products, variants, categories, collections, promotions, orders, customers, inventory, suppliers, purchasing, shipping, payments, reviews, CMS, SEO, reports, users/roles, logs and settings.
- Order printing: invoice, packing slip, shipping label, picking list, daily summary, optional thermal receipt.
- Email notifications; optional SMS/WhatsApp via approved providers.
- Excel import/export for products and orders.
- Security, audit logging, backups and monitoring.
- **Responsive, mobile-first UI for both storefront and admin.**

### 5.2 Out of Scope (initial release)

- Native mobile apps (iOS/Android). The responsive web app is the mobile experience; a PWA option is described below.
- Multi-vendor marketplace functionality.
- Full accounting/ERP (general ledger, payroll).
- Marketplace sync (Daraz, Amazon, etc.) — can be a later phase.

---

## 6. Responsive & Mobile-First Design Requirements

> **Requirement:** The entire system — customer website **and** admin software — must be responsive and easy to use on mobile phones. Mobile is a first-class device, not an afterthought.

### 6.1 Design Approach

- **Mobile-first CSS** using Tailwind CSS: base styles target phones; `sm`, `md`, `lg`, `xl` breakpoints progressively enhance for larger screens.
- Design and review every screen at **360 px**, **390 px**, **768 px**, **1024 px** and **1440 px** widths.
- Test on real low/mid-range Android devices and iOS Safari, not just browser emulation.
- No feature may exist only on desktop unless it is clearly unsuitable for phones (e.g., bulk spreadsheet-style editing), and in that case a simpler phone alternative must exist.

### 6.2 Breakpoints

| Name | Min width | Typical device | Layout |
|------|-----------|----------------|--------|
| default | 0 px | Phones | Single column, bottom navigation, stacked cards |
| `sm` | 640 px | Large phones / small tablets | 2-column product grid |
| `md` | 768 px | Tablets | 3-column grid, side-by-side checkout summary |
| `lg` | 1024 px | Laptops | 4-column grid, sidebar filters, admin sidebar visible |
| `xl` | 1280 px | Desktops | Wider containers, extra dashboard widgets |

### 6.3 Touch and Usability Standards

| Rule | Requirement |
|------|-------------|
| Tap targets | Minimum **44 × 44 px** for all buttons, links, icons and form controls |
| Spacing | At least 8 px between adjacent tap targets |
| Font size | Body text ≥ **16 px** on mobile (also prevents iOS zoom on input focus) |
| Inputs | Correct `type` and `inputmode` (tel, email, numeric), `autocomplete` attributes, visible labels |
| Forms | Single-column on mobile, inline validation, clear error messages, large primary button |
| Scrolling | No horizontal page scroll at any width; wide tables scroll inside their own container or convert to cards |
| Gestures | Swipe on product galleries and banners, with visible buttons as fallback |
| Orientation | Works in portrait and landscape |
| One-handed use | Primary actions (Add to cart, Checkout, Save) reachable at the bottom of the screen |
| Safe areas | Respect notches and home indicators (`env(safe-area-inset-*)`) |
| Pinch zoom | Never disabled |

### 6.4 Customer Website — Mobile Requirements

**Navigation**
- Sticky compact header: logo, search icon, cart icon with item count, menu button.
- Slide-in menu (drawer) for categories, collections, account and policies.
- Optional **bottom navigation bar** on phones: Home, Shop, Search, Wishlist, Account.

**Homepage**
- Hero banners use separate **mobile images** (portrait/square crop) and desktop images.
- Horizontally swipeable carousels for featured categories, collections and products.
- Announcement bar is short, dismissible and does not push content down on scroll.

**Shop / Catalog**
- 2-column product grid on phones, with large product images and price visible.
- **Filters and sorting open in a full-screen or bottom-sheet panel** with "Apply" and "Clear" buttons, not a permanent sidebar.
- Infinite scroll or "Load more" with pagination fallback for SEO.
- Lazy-loaded, properly sized images (`srcset`, `sizes`, WebP/AVIF).

**Product Details**
- Swipeable image gallery with pinch/tap to zoom.
- Size and color selectors as large tappable chips/swatches; unavailable options clearly disabled.
- Price updates instantly when the variant changes.
- **Sticky "Add to Cart" bar** at the bottom of the screen.
- Collapsible sections for description, specifications, shipping and reviews.
- Share button (WhatsApp, copy link) — important for the local market.

**Cart & Checkout**
- Mini-cart drawer after "Add to Cart" with a clear "Checkout" button.
- Checkout optimized for phones: one-column, step indicator (Contact → Address → Shipping → Payment → Review) or a compact single-page accordion.
- Guest checkout is the default path; account creation is optional after purchase.
- Order summary is collapsible at the top on mobile and shows the total at all times.
- Address form with city selection, phone field with Pakistani format hint, and saved addresses for logged-in users.
- Payment method chosen via large radio cards (COD, bank transfer, JazzCash, Easypaisa, card).
- Sticky "Place Order" button.

**Account**
- Account pages are card-based lists on phones; order list shows status badge, date, total and a "Track" button.
- Order tracking timeline is vertical and readable on small screens.

**Performance on mobile**
- Page weight target: ≤ 1.5 MB for the homepage on first load.
- Critical CSS inlined or minimal; JavaScript deferred; minimal third-party scripts.
- Tailwind CSS is built and purged (no CDN runtime in production).
- Service worker / PWA option for caching static assets and an "Add to Home Screen" prompt (optional enhancement).

### 6.5 Admin Software — Mobile Requirements

The admin portal must be usable by staff on a phone for daily tasks.

**Layout**
- Sidebar collapses into a hamburger drawer on phones; bottom shortcut bar for the most-used actions (Orders, Products, Dashboard, More).
- Page headers are compact; page-level primary action (e.g., "Add Product") is a floating action button on phones.

**Dashboard**
- KPI cards stack in a single column or a 2×2 grid; charts resize and remain legible (Chart.js responsive mode).
- Recent orders and low-stock items appear as cards.

**Tables → Cards**
- Data tables (orders, products, customers) **transform into stacked cards on phones**, showing the most important fields (e.g., order number, customer, status, total) with a "View" action.
- Filters/search appear in a collapsible panel; search input is always visible at the top.

**Order Processing on Phone**
- Order detail page is a single column with sections: summary, items, customer, address, payment, shipping, timeline.
- One-tap status change using large buttons or a bottom sheet.
- Tracking number entry, courier selection and "Mark as Dispatched" can be done in under a minute.
- Click-to-call and click-to-WhatsApp links for the customer's phone number.
- Printing is available from the phone (PDF generation, opens in the system print/share dialog).

**Product Management on Phone**
- Upload images directly from the phone camera or gallery with multi-select and progress indicators.
- Variant editor uses expandable cards (one card per variant) instead of a wide spreadsheet grid; a "bulk edit" spreadsheet view remains desktop-optimized.
- Quick edit for price and stock.

**Forms**
- Long forms are split into collapsible sections or steps with a sticky Save/Cancel bar.
- Unsaved-changes warning.

### 6.6 Accessibility and Inclusivity

- Target **WCAG 2.1 AA**: color contrast ≥ 4.5:1, focus indicators, keyboard navigation, ARIA labels for icon buttons, alt text for images.
- Support text resizing up to 200% without layout breakage.
- Urdu/RTL-ready layouts: use logical CSS properties (`ms-`, `me-`, `ps-`, `pe-`), avoid hard-coded left/right where possible.
- Respect `prefers-reduced-motion`.
- Optional dark mode is a future enhancement; the initial admin style is light.

### 6.7 Responsive Acceptance Criteria

A screen is "done" only when:

1. It renders without horizontal scrolling at 360 px width.
2. All tap targets meet the 44 px minimum.
3. Primary actions are usable with one hand on a phone.
4. Text is readable without zooming.
5. Tables are converted to cards or contained scroll areas.
6. Images are responsive and lazy-loaded.
7. It was tested on at least one real Android phone and one iPhone (or accurate equivalents).
8. Lighthouse mobile accessibility score ≥ 90.

### 6.8 Admin Visual Style

A clean, modern, professional, light interface: white background, navy text, subtle grey borders and a green accent color. The same design tokens (colors, spacing, radius, typography) are shared via Tailwind configuration so that the storefront and admin feel consistent but can be themed separately.

---

## 7. System Architecture Overview

```
                 Customers / Staff (phones, tablets, desktops)
                               │
                     Cloudflare CDN + WAF
                               │
                         Load Balancer
                 ┌─────────────┼─────────────┐
            PHP App 1      PHP App 2      PHP App N
                 └─────────────┼─────────────┘
          ┌────────────┬───────┴───────┬────────────┐
       MySQL         Redis        Object Storage   Mail / SMS /
    (primary +    (cache, sessions,  (images, PDFs,   WhatsApp /
     replicas)       queues)          backups)      Payment / Courier
```

**Key design decisions**

- One shared codebase and database for storefront, account area, admin and APIs.
- Separate entry points: `/` (store), `/account`, `/admin`, `/api`.
- Business logic lives in `modules/` (services) and is reused by storefront, admin and API — no duplicated pricing or stock logic.
- Stateless application servers (sessions and cache in Redis) to allow horizontal scaling.
- Background jobs (Redis queue) for emails, invoices, notifications, image processing and report exports.

---

## 8. Technology Stack

| Layer | Technology |
|-------|------------|
| Frontend | PHP 8.3+, HTML5 |
| Backend | Core PHP 8.3+ (modular, service-oriented) |
| Styling | Tailwind CSS (mobile-first, built & purged) |
| Interactivity | Vanilla JavaScript (ES modules) |
| AJAX | Fetch API with JSON endpoints |
| Database | MySQL 8 / MariaDB (InnoDB) |
| DB access | PDO with prepared statements |
| Image processing | GD / Imagick (resize, WebP/AVIF, thumbnails) |
| Charts | Chart.js |
| Notifications (UI) | SweetAlert2 / lightweight toast component |
| PDF | Dompdf |
| Excel | PhpSpreadsheet |
| Email | PHPMailer over SMTP |
| Web server | Apache or Nginx |
| Cache / sessions / queue | Redis |
| CDN / WAF | Cloudflare or equivalent |
| Dependency management | Composer |

**Why not a framework?** The preferred approach is core PHP with a disciplined modular structure. This keeps the system lightweight and fully under your control. The trade-off is that conventions (routing, validation, migrations, testing) must be established early and followed strictly.

---

## 9. Customer Website — Functional Requirements

### 9.1 Pages

| Page | Functionality |
|------|---------------|
| Homepage | Announcement bar, hero banners, featured categories/collections, best sellers, new arrivals, deals, countdown, testimonials, newsletter |
| Shop | Full catalog, pagination/load more, sort and filter |
| Category | Products by category (parent/child) |
| Collection | Curated product groups |
| Deals | Discounted and promotional products |
| Product details | Gallery, variants, price, stock status, description, reviews, related products |
| Search | By name, SKU, category, tags; suggestions as you type |
| Cart | Quantities, variants, coupon entry, shipping estimate |
| Checkout | Contact, address, shipping method, payment method, order summary |
| Login / Register | Customer authentication, email verification, password reset |
| My Account | Profile, addresses, orders, wishlist, security |
| Order tracking | Status timeline and courier tracking link |
| Wishlist | Save favorites (guest via cookie, merged on login) |
| About Us | Business information |
| Contact Us | Contact form, business details, map, WhatsApp link |
| FAQs | Common questions (admin-managed) |
| Policies | Shipping, returns, refunds, privacy, terms |

### 9.2 Homepage Management (Admin-Controlled)

- Top announcement bar: text, link, enable/disable, schedule.
- Multiple promotional banners with **separate desktop and mobile images**.
- Hero section with heading, description, call-to-action.
- Featured categories and collections.
- Best-selling products and new arrivals (automatic or manual).
- Special offers and promotional countdown.
- Customer testimonials.
- Newsletter subscription.
- Footer content and social links.

Every section has **display order, visibility, start date and end date** where relevant.

### 9.3 Search and Filtering

- Search by name, SKU, category and tags with typo tolerance as a later enhancement (MySQL FULLTEXT initially; dedicated search engine later if required).
- Filters: category, collection, size, color, price range, availability, rating, on sale.
- Sort: newest, price low–high, price high–low, best selling, rating.
- Filter state reflected in the URL for shareable links.

### 9.4 Product Reviews

- Verified-purchase reviews with rating, text and optional photos.
- Admin moderation (approve/reject).
- Average rating and review count cached on the product.

### 9.5 Wishlist and Saved Cart

- Wishlist for logged-in users, stored in DB; guest wishlist in cookie/local storage and merged on login.
- Cart persists across sessions for logged-in users and via cookie for guests.

### 9.6 Back-in-Stock and Abandoned Cart

- Customers can subscribe to out-of-stock variants and are emailed when restocked.
- Abandoned carts are detected by a cron job; reminder emails are sent per configured rules.

---

## 10. Product Management

Embroidery products have multiple sizes, colors and designs; variant-level control is essential.

### 10.1 Product Fields

| Field | Details |
|-------|---------|
| Name | e.g., "Floral Embroidery Motif" |
| SKU | Unique identifier (product-level and variant-level) |
| Slug | SEO-friendly, unique URL |
| Category | Main category plus optional additional categories |
| Collections | One or many |
| Short and long description | Rich text with sanitization |
| Images | Multiple images, reorderable, alt text, primary image |
| Regular price / sale price | DECIMAL, with optional sale schedule |
| Sizes / colors | Attribute values |
| Variants | Each with its own SKU, price, stock, weight, image, availability |
| Inventory | Stock per variant, low-stock threshold, made-to-order flag |
| Weight / dimensions | For shipping calculations |
| Tags | For search and filtering |
| Status | Draft, Active, Archived |
| Featured | Show on homepage |
| SEO | Meta title, meta description, canonical URL, OG image |

### 10.2 Example Variants

| Size | Color | SKU | Price | Stock |
|------|-------|-----|-------|-------|
| Small | Golden | EM-001-SG | Rs. 350 | 100 |
| Medium | Golden | EM-001-MG | Rs. 500 | 70 |
| Large | Golden | EM-001-LG | Rs. 750 | 40 |
| Small | Silver | EM-001-SS | Rs. 350 | 60 |

### 10.3 Product Tools

- Product duplication.
- Bulk import/export via Excel (validation report with row-level errors).
- Bulk price updates (percentage or fixed, by category/collection/selection).
- Image reordering by drag-and-drop (with up/down buttons on mobile).
- Automatic image optimization, thumbnails and WebP/AVIF conversion.
- Related products and "frequently bought together".
- Low-stock notifications.
- Product-level visibility controls.
- Soft delete and archive.

### 10.4 Image Handling Rules

- Accept JPG, PNG, WebP; validate MIME type by content, not extension.
- Max upload size configurable (e.g., 8 MB), original kept privately, derivatives generated (thumb, small, medium, large, zoom).
- Random server-generated filenames; no executable uploads.
- Phone camera photos are auto-rotated using EXIF and have metadata (e.g., GPS) stripped.

---

## 11. Categories, Collections and Promotions

### 11.1 Categories

- Parent/child hierarchy (e.g., Floral Embroidery, Bridal Motifs, Neckline Designs, Sleeve Motifs, Traditional Patches, Modern Embroidery).
- Fields: name, slug, description, image, banner, SEO, display order, status.

### 11.2 Collections

- **Manual** (admin picks products) or **automatic** (rules, e.g., tag = "eid", created within 30 days).
- Examples: Eid Collection 2027, Bridal Collection, New Arrivals, Premium Collection, Best Sellers.
- Scheduling: start/end dates.

### 11.3 Deals, Discounts and Coupons

| Type | Description |
|------|-------------|
| Percentage discount | % off product, category, collection or entire order |
| Fixed amount discount | Rs. off |
| Product-specific | Applies to selected products/variants |
| Category / collection | Applies to all products inside |
| Coupon codes | Unique codes with rules |
| Minimum order amount | Threshold |
| Buy X Get Y | Quantity-based promotions |
| Date range | Start/expiry, automatic activation |
| Usage limits | Total uses and per customer |
| Customer-specific | Restricted to selected customers |
| First-order discount | New customers only |
| Free shipping offers | Conditional |

**Rules**

- All discounts are calculated and validated **on the server**.
- Define clear precedence (e.g., product sale price → automatic promotion → coupon) and stacking rules (stackable or exclusive).
- Order items store the **final applied discount** so history never changes.

---

## 12. Cart, Checkout and Pricing Rules

### 12.1 Cart

- Add/update/remove items by variant.
- Stock check on add and on checkout.
- Show price changes or availability issues clearly.
- Coupon entry with instant validation message.
- Shipping estimate by city/zone.

### 12.2 Checkout Flow

1. Contact information (email and phone; or login).
2. Shipping address.
3. Shipping method (rates based on zone, weight, order amount).
4. Payment method.
5. Review and place order.

### 12.3 Server-Side Validation

The server recalculates and verifies:

- Product/variant availability and status.
- Unit prices and sale prices.
- Quantities and stock.
- Discounts and coupon eligibility.
- Shipping charges.
- Taxes (if enabled).
- Order totals.

**Never trust prices or totals sent from the browser.**

### 12.4 Preventing Duplicate Orders and Overselling

- **Idempotency key** on checkout submission.
- **Database transaction** wrapping order creation, stock reservation and payment record creation.
- **Stock reservations** with expiry for online payments in progress.
- Row-level locking (`SELECT ... FOR UPDATE`) on stock rows during reservation.

### 12.5 Guest Checkout

- Allowed by default; guest orders are linked to an email/phone.
- After order placement, guests are offered to create an account (pre-filled).
- Guest order tracking via order number + phone/email, or secure tracking link.

---

## 13. Order Management

### 13.1 Order Lifecycle

1. **Order Received** — customer completes checkout; order number issued.
2. **Pending Confirmation** — admin verifies order, payment and stock.
3. **Confirmed** — accepted; stock reserved or deducted per policy.
4. **Processing** — products prepared and packed.
5. **Dispatched** — courier, tracking number and date recorded.
6. **Delivered** — delivery complete.

**Exceptional statuses:** Cancelled, On Hold, Payment Failed, Return Requested, Refunded.

Payment status is **independent** of order status.

### 13.2 Order Number Format

`EMB-YYYY-NNNNNN` (e.g., `EMB-2026-000001`), unique, generated safely under concurrency.

### 13.3 Order Management Features

- Customer information and addresses (billing and shipping).
- Items with variant details, quantity and historical name/price snapshot.
- Discounts and coupon details.
- Shipping charges and tax breakdown.
- Payment method and status.
- Admin notes (internal) and customer notes.
- **Order activity timeline** (every status change, who and when).
- Courier and tracking details.
- Partial and full refunds.
- Cancellation and return management.
- Reorder/duplicate order.
- Bulk status updates.
- Search by order number, customer name, phone, email, tracking number.
- Filters by status, payment status, date range, courier, city, payment method.

### 13.4 Order Printing

| Document | Purpose |
|----------|---------|
| A4 invoice | Customer invoice |
| Thermal receipt (optional) | Counter/COD receipts |
| Packing slip | Included in parcel |
| Shipping label | Stuck on parcel (courier format) |
| Picking list | Warehouse picking by location/SKU |
| Daily order summary | Operations summary |

Every document includes the store logo, business details, order ID, customer details, products, quantities and financial summary. PDFs are generated by Dompdf (queued for bulk), stored privately and served through authenticated download.

### 13.5 Notifications per Status

- Order received (customer + admin).
- Order confirmed.
- Dispatched (with tracking link).
- Delivered.
- Cancelled/refunded.
- Optional SMS/WhatsApp through approved providers.

---

## 14. Customer Account Management

### 14.1 Customer Portal

- Registration and login (email + password).
- Email verification and password reset (secure, expiring tokens).
- Profile editing.
- Multiple saved addresses (default billing/shipping).
- Order history and order details.
- Order tracking.
- Wishlist and saved cart.
- Reorder.
- Change password.
- Account deletion request (processed under privacy policy).

### 14.2 Admin Customer Portal

- Customer list and search (name, phone, email).
- Customer profile with order history, total spending, number of orders, creation date and status.
- Internal admin notes.
- Segmentation (e.g., VIP, repeat buyers, inactive).
- Block/unblock accounts.

### 14.3 Audit Trail

Record changes to important customer fields (email, phone, address), including **who changed it, previous value, new value and when**.

---

## 15. Shipping and Delivery

| Feature | Description |
|---------|-------------|
| Shipping zones | Pakistan (by city/province), international, selected regions |
| Shipping methods | Standard, express, local delivery, pickup |
| Flat rates | Fixed charges per zone/method |
| Weight-based | Slabs based on parcel weight |
| Order-value based | Different rates by order amount |
| Free shipping | Conditional (min amount, coupon, zone) |
| Courier integration | TCS, Leopards, PostEx and others via API where available |
| Tracking | Tracking number and link per shipment |
| Shipping labels | Printable (A4 or 4×6) |
| Delivery statuses | Shipped, in transit, out for delivery, delivered, failed, returned |
| COD handling | COD fee, COD reconciliation per courier |

**Phasing:** start with manual shipping configuration and manual tracking entry; add courier APIs once account access is available.

---

## 16. Payment Management

### 16.1 Supported Methods (configurable in admin)

- Cash on Delivery (with optional fee and city restrictions).
- Bank transfer (with receipt upload and manual verification).
- JazzCash.
- Easypaisa.
- Debit/credit cards through a supported gateway.
- Manual payment verification.

### 16.2 Payment Statuses

Pending → Authorized → Paid → Failed → Partially Refunded → Refunded.

### 16.3 Payment Rules

- Payment status is independent of order status.
- Online payments confirmed only through **gateway verification and signed callbacks/webhooks** — never from the browser alone.
- Store every gateway interaction in `payment_transactions` (request, response, reference IDs).
- **Never store raw card details.**
- Webhooks are idempotent and verified by signature.
- Refunds recorded as separate records tied to the payment.

---

## 17. Inventory, Suppliers and Purchasing

### 17.1 Inventory

- Variant-level stock quantity, reserved quantity, available quantity.
- Stock movement ledger: purchase received, sale, return, adjustment, damage, reservation, release.
- Manual stock adjustments with reason and user.
- Low-stock thresholds and alerts (email + dashboard).
- Made-to-order flag (unlimited availability with lead time shown to customers).

### 17.2 Suppliers and Purchasing

- Supplier directory (contacts, terms).
- Purchase orders and items; receive stock against PO (full or partial).
- Cost price tracking per variant for profit estimation.

---

## 18. Admin Software — Modules and Dashboard

| Module | Functionality |
|--------|---------------|
| Dashboard | Sales, orders, revenue, customers, alerts |
| Products | Products, variants, images, bulk tools |
| Categories / Collections | Hierarchy, manual/automatic collections |
| Promotions | Discounts, coupons, scheduled campaigns |
| Orders | Full lifecycle, printing, shipping |
| Customers | Profiles, segments, notes |
| Inventory | Stock, adjustments, movements |
| Suppliers / Purchasing | Suppliers, POs, receiving |
| Shipping | Zones, methods, couriers, labels |
| Payments | Methods, verification, refunds |
| Returns | Return requests and refunds |
| Reviews | Moderation |
| CMS | Banners, pages, homepage sections, menus |
| SEO | Meta tags, sitemap, structured data, redirects |
| Notifications | Templates, email logs, SMS/WhatsApp |
| Reports | Sales, products, customers, inventory, tax |
| Users & roles | Admin accounts and permissions |
| Activity logs | Who changed what and when |
| Settings | Store info, taxes, currency, email, policies |
| Backup & health | Backup status, failed jobs, errors |

### 18.1 Dashboard Widgets

| Widget | Information |
|--------|-------------|
| Total Sales | Selected period |
| Total Orders | All received orders |
| Pending Orders | Awaiting confirmation |
| Processing Orders | Being prepared |
| Delivered Orders | Successfully delivered |
| Total Customers | Registered customers |
| Low Stock | Items needing restock |
| Revenue Chart | Daily, weekly, monthly |
| Recent Orders | Latest purchases |
| Top Products | Best sellers |

Dashboard is fully responsive (see section 6.5).

---

## 19. Roles and Permissions

| Role | Access |
|------|--------|
| Super Admin | Everything |
| Store Manager | Orders, products, inventory, customers |
| Order Manager | Orders, printing, shipping |
| Product Manager | Products, categories, collections |
| Accountant | Payments, invoices, reports |
| Content Manager | Banners, pages, promotions, SEO |

**Rules**

- Permissions are **enforced in backend code on every request**, not only by hiding buttons.
- Permission model: roles → permissions (e.g., `orders.view`, `orders.update_status`, `products.edit`, `reports.view`).
- Optional per-user permission overrides.
- All sensitive actions are logged.
- Optional two-factor authentication (TOTP) for admins; recommended for Super Admin and Accountant.

---

## 20. CMS, SEO and Notifications

### 20.1 CMS

- Banners (desktop + mobile images, link, schedule).
- Static pages (About, Policies, FAQs) with a safe rich-text editor.
- Homepage sections: ordering, visibility, schedule.
- Menus (header, footer, mobile drawer).

### 20.2 SEO

- Meta title/description per product, category, collection and page.
- Clean URLs and canonical tags.
- XML sitemap (auto-generated) and robots.txt.
- Structured data (Product, Offer, BreadcrumbList, Organization, Review).
- Open Graph/Twitter cards for sharing (important for WhatsApp/Facebook).
- 301 redirect manager.

### 20.3 Notifications

- Email templates editable by admin (with variables).
- Queue-based sending with retries and logs.
- Optional SMS and WhatsApp (approved providers only).
- Admin alerts: new order, low stock, failed payment, failed job.

---

## 21. Reports and Analytics

- Sales by day/week/month/product/category/city/payment method.
- Orders by status and courier.
- Best/worst-selling products and variants.
- Customer metrics: new vs returning, lifetime value, top customers.
- Inventory valuation, low-stock and stock movement reports.
- Discount and coupon performance.
- Estimated profit (price − cost).
- Conversion funnel (visits → cart → checkout → order) via lightweight analytics.
- Export to Excel/CSV; scheduled email reports (later).

---

## 22. Security Requirements

| Area | Implementation |
|------|----------------|
| SQL injection | PDO prepared statements everywhere |
| XSS | Context-aware output escaping; sanitized rich text |
| CSRF | Tokens on all state-changing requests |
| Passwords | `password_hash()` / `password_verify()` (Argon2id or bcrypt) |
| Sessions | Secure, HttpOnly, SameSite cookies; session regeneration on login |
| Admin security | RBAC, optional 2FA, IP/device logging |
| Login protection | Rate limiting, temporary lockouts, generic error messages |
| File uploads | MIME validation by content, size limits, random names, non-executable storage |
| Payments | Server-side verification, signed webhooks, idempotency |
| APIs | Authentication, authorization, throttling, input validation |
| Database | Least-privilege accounts; no root usage by the app |
| Transport | HTTPS everywhere, HSTS |
| Headers | CSP, X-Content-Type-Options, X-Frame-Options/frame-ancestors, Referrer-Policy |
| Secrets | Kept outside web root (env/config files) |
| Errors | Logged privately; users see generic messages |
| Backups | Encrypted, scheduled, off-server |
| Audit logs | Sensitive admin actions recorded |

**Additional rules**

- Never store raw card data.
- Secure password reset tokens (random, hashed in DB, short expiry, single use).
- Prevent IDOR: customers can access only their own orders/addresses (check ownership server-side).
- Validate all prices, discounts, quantities and shipping on the server.
- Soft-delete important business records.
- Use DB transactions for checkout, payments and stock updates.
- Regular dependency updates and vulnerability scanning.
- Privacy readiness: data export, deletion requests, retention policy.

---

## 23. Scalability and Performance Architecture

> **Important:** "One million users" means different things — one million registered customers, one million monthly visitors, and one million *simultaneous* connections are very different workloads. The system is designed to be **horizontally scalable**; real capacity must be proven by load testing.

### 23.1 Strategy

- Start on a modest cloud VPS; keep the application stateless so servers can be added.
- Use CDN/WAF in front of the application.
- Move sessions, cache and queues to Redis.
- Store media in object storage with CDN delivery.
- Add MySQL read replicas for read-heavy traffic.
- Scale up and out based on monitoring and load-test results.

### 23.2 Performance Engineering

- Full-page caching for anonymous catalog/CMS pages; cache invalidation on admin changes.
- Redis caching for categories, menus, popular products and settings.
- Image optimization (WebP/AVIF), responsive `srcset`, lazy loading.
- Database indexes on frequent filters (status, category, SKU, order date, customer ID).
- Pagination everywhere; no unbounded queries.
- Background jobs for emails, PDFs, notifications, reports, image processing.
- Rate limiting separated for storefront, admin and API.
- Gzip/Brotli compression and HTTP caching headers.
- OPcache and tuned PHP-FPM.
- Monitoring: CPU, RAM, response time, DB connections, slow queries, queue depth, error rate.

### 23.3 Load Testing

- Define realistic scenarios (browse, search, add to cart, checkout) with tools such as k6 or JMeter.
- Test peak events (e.g., sales campaigns, Eid season).
- Document results and capacity per server tier.

---

## 24. Database Design

### 24.1 Table Groups

| Group | Tables |
|-------|--------|
| Users | users, roles, permissions, role_permissions |
| Customers | customers, customer_addresses, customer_sessions |
| Catalog | products, product_images, categories, product_categories |
| Variants | product_variants, variant_attributes, attributes, attribute_values |
| Collections | collections, collection_products |
| Promotions | coupons, promotions, promotion_products |
| Cart | carts, cart_items, wishlists, wishlist_items |
| Orders | orders, order_items, order_status_history |
| Payments | payments, payment_transactions, refunds |
| Shipping | shipping_zones, shipping_methods, shipments |
| Inventory | inventory, stock_movements, stock_reservations |
| Suppliers | suppliers, purchase_orders, purchase_order_items |
| CMS | banners, pages, homepage_sections, menus |
| Reviews | product_reviews |
| Notifications | notifications, email_logs |
| Business | invoices, tax_settings, store_settings |
| Security | activity_logs, login_attempts, password_resets |
| System | jobs, failed_jobs, backups |

### 24.2 Critical Database Rules

1. InnoDB with foreign keys.
2. `DECIMAL(12,2)` for money — never floats.
3. Unique indexes for SKUs, slugs, order numbers, customer emails.
4. Indexes on order dates, customer IDs, product status, variant IDs.
5. **Order items store snapshots** of product name, variant, SKU, price and discount so old invoices never change.
6. Payment transactions stored separately from orders.
7. Transactions + stock reservations to prevent overselling.
8. Store timestamps in UTC; display in store timezone (Asia/Karachi).
9. `created_at`, `updated_at`, and `deleted_at`/status where appropriate.
10. Idempotency keys for checkout and payment processing.
11. `utf8mb4` charset (supports Urdu and emoji).

### 24.3 Key Table Sketches

**products**

| Column | Type | Notes |
|--------|------|-------|
| id | BIGINT UNSIGNED PK | |
| name | VARCHAR(255) | |
| slug | VARCHAR(255) UNIQUE | |
| sku | VARCHAR(64) UNIQUE | Base SKU |
| category_id | BIGINT UNSIGNED FK | Primary category |
| short_description | VARCHAR(500) | |
| description | MEDIUMTEXT | Sanitized HTML |
| base_price | DECIMAL(12,2) | |
| sale_price | DECIMAL(12,2) NULL | |
| status | ENUM('draft','active','archived') | |
| is_featured | TINYINT(1) | |
| made_to_order | TINYINT(1) | |
| weight_grams | INT | |
| meta_title / meta_description | VARCHAR | |
| avg_rating / review_count | DECIMAL / INT | Cached |
| created_at / updated_at / deleted_at | DATETIME | UTC |

**product_variants**

| Column | Type | Notes |
|--------|------|-------|
| id | BIGINT UNSIGNED PK | |
| product_id | FK | |
| sku | VARCHAR(64) UNIQUE | |
| size / color (or attribute links) | | Via attribute tables |
| price | DECIMAL(12,2) | |
| sale_price | DECIMAL(12,2) NULL | |
| weight_grams | INT | |
| image_id | FK NULL | |
| is_active | TINYINT(1) | |

**inventory**

| Column | Type | Notes |
|--------|------|-------|
| variant_id | PK/FK | |
| quantity_on_hand | INT | |
| quantity_reserved | INT | |
| low_stock_threshold | INT | |

**orders**

| Column | Type | Notes |
|--------|------|-------|
| id | BIGINT UNSIGNED PK | |
| order_number | VARCHAR(32) UNIQUE | EMB-2026-000001 |
| customer_id | FK NULL | Null for guests |
| email / phone | VARCHAR | |
| status | VARCHAR(32) | |
| payment_status | VARCHAR(32) | |
| subtotal / discount_total / shipping_total / tax_total / grand_total | DECIMAL(12,2) | |
| currency | CHAR(3) | PKR |
| coupon_code | VARCHAR(64) NULL | |
| shipping_address / billing_address | JSON | Snapshot |
| customer_note / admin_note | TEXT | |
| idempotency_key | VARCHAR(64) UNIQUE | |
| placed_at | DATETIME | |

**order_items**

| Column | Type | Notes |
|--------|------|-------|
| id | PK | |
| order_id | FK | |
| variant_id | FK | |
| product_name / variant_label / sku | VARCHAR | Snapshot |
| unit_price | DECIMAL(12,2) | Snapshot |
| quantity | INT | |
| discount_amount | DECIMAL(12,2) | |
| line_total | DECIMAL(12,2) | |

### 24.4 Key Relationships

- products 1—N product_variants; product_variants 1—1 inventory.
- products N—N categories (product_categories) and N—N collections (collection_products).
- customers 1—N addresses, orders, wishlists.
- orders 1—N order_items, order_status_history, payments, shipments.
- payments 1—N payment_transactions, refunds.
- users N—N roles via role_permissions.

---

## 25. Folder Structure

```
embroidery-store/
├── public/                  # Only web-accessible folder
│   ├── index.php
│   ├── .htaccess
│   ├── assets/ (css, js, images, fonts)
│   └── uploads/             # Validated, non-executable media only
├── admin/                   # Admin portal (responsive)
│   ├── index.php, dashboard.php
│   └── products/ categories/ collections/ promotions/ orders/
│       customers/ inventory/ shipping/ payments/ suppliers/
│       reports/ cms/ users/ settings/ logs/
├── account/                 # Customer portal
├── modules/                 # Business logic (shared services)
│   └── auth/ products/ cart/ checkout/ orders/ customers/ payments/
│       shipping/ inventory/ promotions/ notifications/ seo/ reports/
├── includes/                # config, database, security, auth, csrf, helpers
├── api/                     # JSON endpoints + webhooks
├── cron/                    # abandoned-carts, promo-expiry, low-stock, cleanup
├── storage/                 # logs, cache, invoices, private (outside web root)
├── vendor/                  # Composer
├── database/                # schema.sql, seed.sql, migrations/
├── config/                  # app.php, mail.php, services.php
├── resources/
│   ├── tailwind/            # Tailwind source & config
│   └── templates/           # Email and PDF templates
└── tests/
```

**Rule:** `storage/`, `config/`, `database/`, `includes/`, `modules/` and `vendor/` must live **outside the public web root** in production.

---

## 26. API and Integration Overview

### 26.1 Internal JSON API

- Conventions: JSON in/out, consistent error format, HTTP status codes, CSRF/session auth for browser calls, token auth for integrations.
- Endpoints (examples): `/api/products`, `/api/cart`, `/api/checkout`, `/api/orders`, `/api/customers`, `/api/webhooks/{provider}`.
- Pagination, filtering and rate limits on all list endpoints.

### 26.2 External Integrations

| Integration | Purpose |
|-------------|---------|
| SMTP / PHPMailer | Transactional email |
| Payment gateways (JazzCash, Easypaisa, card) | Online payments |
| Couriers (TCS, Leopards, PostEx, etc.) | Booking, tracking, labels |
| WhatsApp Business / SMS provider | Notifications (optional) |
| Cloudflare | CDN, WAF, DDoS protection |
| Google Analytics / Meta Pixel (optional) | Marketing analytics (with consent) |

---

## 27. Non-Functional Requirements

| Category | Requirement |
|----------|-------------|
| Responsiveness | Works from 320 px to large desktop; mobile-first |
| Performance | Mobile LCP ≤ 2.5 s; API p95 ≤ 500 ms on normal load |
| Availability | ≥ 99.5% with monitoring and alerts |
| Browser support | Last 2 versions of Chrome, Safari, Firefox, Edge; Android Chrome; iOS Safari |
| Accessibility | WCAG 2.1 AA target |
| Maintainability | Modular code, PSR-style conventions, documented modules |
| Localization | English now; Urdu/RTL-ready |
| Time & currency | UTC storage, Asia/Karachi display, PKR |
| Data integrity | Transactions, foreign keys, audit logs |
| Observability | Error logging, health checks, queue monitoring |
| Privacy | Consent, data export/deletion, retention rules |

---

## 28. Testing and Quality Assurance

- **Unit tests** (PHPUnit) for pricing, discounts, shipping, stock logic.
- **Integration tests** for checkout, payment callbacks, order lifecycle.
- **Security testing:** SQLi/XSS/CSRF checks, permission tests, dependency scan, pre-launch penetration test.
- **Responsive/mobile testing:** device matrix (small Android, mid Android, iPhone, tablet), checklist from section 6.7.
- **Performance testing:** Lighthouse CI, k6 load tests.
- **User acceptance testing:** staff run real workflows (add product, process order, print label) on phone and desktop.
- **Regression checklist** before each release.

---

## 29. Deployment, Backup and Operations

- Environments: local → staging → production.
- Deployment via Git with a scripted process; database migrations versioned.
- HTTPS (Let's Encrypt or Cloudflare), firewall, SSH keys only, fail2ban.
- Automated daily database backups + file backups, **encrypted and stored off-server**; periodic restore tests.
- Cron jobs: abandoned carts, promo expiry, low-stock alerts, cleanup, scheduled reports.
- Queue workers supervised (systemd/Supervisor).
- Monitoring and alerting (uptime, errors, queue depth, disk space).
- Documented runbook: deploy, rollback, restore, incident response.

---

## 30. Development Roadmap (Phases)

| Phase | Name | Key Deliverables |
|-------|------|------------------|
| 1 | Foundation | DB schema, config, auth, RBAC, security utilities, shared responsive layouts & design system |
| 2 | Product management | Products, variants, categories, collections, images, inventory |
| 3 | Customer website | Homepage, navigation, catalog, product page, search/filters, **mobile polish** |
| 4 | Shopping system | Registration, cart, wishlist, guest checkout, addresses, shipping calc |
| 5 | Orders & payments | Order processing, payment integrations, printing, tracking, returns/refunds |
| 6 | Business management | Dashboard, promotions, customers, suppliers/purchasing, reports |
| 7 | Website management | Homepage builder, banners, CMS pages, SEO, notifications |
| 8 | Production readiness | Security audit, performance, tests, backups, load testing, deployment |

**Cross-cutting in every phase:** responsive acceptance checklist, security checklist, automated tests, documentation.

---

## 31. Risks and Mitigations

| Risk | Impact | Mitigation |
|------|--------|------------|
| Scope too large for one release | Delays | Deliver MVP first, then phases |
| Overselling due to concurrency | Lost trust | Transactions, reservations, row locks |
| Payment/courier API access delays | Blocked features | Start with COD/manual; integrate later |
| Poor mobile experience | Lost sales | Mobile-first design, device testing, acceptance criteria |
| Security flaws in custom code | Data breach | Secure-by-design utilities, reviews, pen test |
| Scalability expectations unrealistic | Outages | Stateless design, caching, load testing, scale gradually |
| Heavy images slow mobile | Slow pages | Auto-optimization, CDN, lazy-load |
| Single developer dependency | Maintainability risk | Documentation, conventions, tests |
| Data loss | Business loss | Automated off-server encrypted backups and restore drills |

---

## 32. Open Decisions (to Confirm)

| # | Question | Proposed default |
|---|----------|------------------|
| 1 | Target market | Pakistan first, international-ready |
| 2 | Checkout | Guest + registered |
| 3 | Inventory | Variant-level; made-to-order flag |
| 4 | Shipping | Manual first, courier APIs later |
| 5 | Payments at launch | COD + bank transfer (JazzCash/Easypaisa/card in a later phase) |
| 6 | Hosting | Cloud VPS, scalable |
| 7 | Tax / GST | Needs business confirmation |
| 8 | Which couriers have accounts/API access? | To be provided |
| 9 | WhatsApp/SMS provider | To be decided |
| 10 | Brand assets (logo, colors, fonts) | To be provided |
| 11 | Product data volume and import source | To be provided |
| 12 | PWA ("Add to Home Screen") at launch? | Optional, phase 3+ |

---

## 33. Deliverables and Next Steps

**Deliverables**

1. This Project Brief (approved).
2. MVP Scope Document (`PROJECT_MVP.md`).
3. Software Requirements Specification (SRS) with detailed table definitions, page specs, API contracts.
4. UI wireframes/prototypes for mobile and desktop (storefront and admin).
5. Database schema and migrations.
6. Working software delivered phase by phase.
7. Test reports, deployment guide and admin user manual.

**Next steps**

1. Confirm open decisions (section 32).
2. Approve the MVP scope.
3. Prepare the SRS and mobile-first wireframes.
4. Begin Phase 1 — Foundation.

---

## 34. Glossary

| Term | Meaning |
|------|---------|
| SKU | Stock Keeping Unit, unique product/variant code |
| Variant | A specific combination of options (e.g., size + color) |
| COD | Cash on Delivery |
| RBAC | Role-Based Access Control |
| CSRF / XSS | Common web security attacks |
| Idempotency key | Token ensuring a repeated request is processed only once |
| CDN | Content Delivery Network |
| WAF | Web Application Firewall |
| PWA | Progressive Web App |
| LCP | Largest Contentful Paint (loading speed metric) |
| MVP | Minimum Viable Product |
