# Embroidery E-Commerce — MVP Scope

**Purpose:** Define the smallest version of the platform that can sell products, take real orders and be run daily by staff — **fully responsive and easy to use on mobile phones**.
**Companion document:** `PROJECT_BRIEF.md` (full vision)
**Stack:** Core PHP 8.3+, MySQL 8, Tailwind CSS (mobile-first), Vanilla JS, PDO

---

## 1. MVP Goal

Launch a working online store where customers can browse embroidery products, choose size/color, order via **Cash on Delivery or bank transfer**, and track their order — while staff manage products, stock and orders from an admin panel that works well on a **phone as well as a desktop**.

**MVP success criteria**

- A customer can complete an order on a phone in under 2 minutes.
- Staff can add a product with variants and photos from a phone.
- Staff can confirm, pack and dispatch an order (with tracking number) from a phone.
- No overselling; prices and totals are always calculated on the server.
- Mobile Lighthouse performance ≥ 80 and no horizontal scrolling at 360 px.

---

## 2. Mobile & Responsive Requirements (Mandatory for MVP)

> Every MVP screen — storefront and admin — must be responsive and easy to use on a phone. A screen is not "done" until it passes this checklist.

### 2.1 Rules

| Rule | Requirement |
|------|-------------|
| Approach | Mobile-first Tailwind CSS; design at 360 px first, then scale up |
| Tap targets | ≥ 44 × 44 px; ≥ 8 px spacing |
| Font size | Body ≥ 16 px on mobile |
| Layout | Single column on phones; 2-column product grid; no horizontal page scroll |
| Forms | One column, correct input types (`tel`, `email`, `numeric`), visible labels, inline errors |
| Tables (admin) | Become stacked cards on phones |
| Images | Responsive (`srcset`), lazy-loaded, WebP with JPG fallback |
| Primary actions | Sticky/bottom placement (Add to Cart, Place Order, Save) |
| Navigation | Storefront: compact header + slide-in menu; Admin: hamburger drawer + bottom shortcuts |
| Accessibility | Sufficient color contrast, labels, focus states, alt text |
| Pinch zoom | Never disabled |

### 2.2 Mobile Acceptance Checklist (per screen)

- [ ] Works at 360 px and 390 px width without horizontal scrolling
- [ ] All buttons and links are easily tappable
- [ ] Text readable without zooming
- [ ] Primary action reachable with one hand
- [ ] Tables converted to cards or contained scroll
- [ ] Tested on a real Android phone and an iPhone
- [ ] Loads acceptably on a 4G connection

### 2.3 Key Mobile Screens in the MVP

| Screen | Mobile behavior |
|--------|-----------------|
| Homepage | Swipeable banner, 2-column product grid, categories as horizontal chips |
| Shop | Filter/sort in a bottom sheet, "Load more" button |
| Product page | Swipeable gallery, large size/color chips, **sticky Add to Cart bar** |
| Cart | Card list, quantity steppers, sticky Checkout button |
| Checkout | One-column flow, sticky Place Order button, collapsible order summary |
| Admin dashboard | KPI cards stacked/2×2, recent orders as cards |
| Admin orders | Card list, one-tap status change, call/WhatsApp customer links |
| Admin product form | Collapsible sections, image upload from camera/gallery, variants as expandable cards |

---

## 3. MVP Scope — Included Features

### 3.1 Customer Website

| Feature | MVP Details |
|---------|-------------|
| Homepage | Announcement bar, hero banner(s), featured categories, new arrivals, featured products |
| Shop & categories | Product grid, parent/child categories, pagination/load more |
| Search | Simple search by name, SKU, tags |
| Filters & sort | Category, price range, size, color; sort by newest/price |
| Product details | Gallery, description, size/color variants, price, stock status |
| Cart | Add/update/remove, variant-aware, stock validation |
| Checkout | Guest and registered; address form; shipping method; payment method |
| Customer accounts | Register, login, logout, password reset, profile, saved addresses |
| Order history & tracking | List of orders, status timeline, tracking number |
| Static pages | About, Contact (form), FAQs, Shipping/Returns/Privacy/Terms |
| Basic SEO | Meta title/description, clean URLs, sitemap.xml, robots.txt |
| WhatsApp contact button | Click-to-chat on mobile |

### 3.2 Admin Software

| Module | MVP Details |
|--------|-------------|
| Authentication | Secure login, logout, session security, login rate limiting |
| Roles | Super Admin, Store Manager, Order Manager (backend-enforced permissions) |
| Dashboard | Today/period sales, total/pending/processing/delivered orders, low stock, recent orders |
| Products | Create/edit/archive, multiple images (reorder), variants (size × color) with own SKU, price, stock |
| Categories | Hierarchy, image, status, order |
| Collections | Manual collections (simple) |
| Inventory | Stock per variant, manual adjustments with reason, stock movement log, low-stock list |
| Orders | List, search, filter, detail view, status updates, admin notes, tracking number, activity timeline |
| Order printing | A4 invoice, packing slip, shipping label (PDF via Dompdf) |
| Customers | List, search, profile with order history, block/unblock |
| Shipping | Zones (Pakistan cities/provinces), flat-rate and free-over-amount rules, manual tracking entry |
| Payments | COD and bank transfer with manual "Mark as Paid" and receipt upload |
| Coupons | Percentage/fixed coupons with minimum amount, expiry, usage limit |
| CMS (basic) | Banners (desktop + mobile images), announcement bar, static pages |
| Settings | Store info, logo, currency (PKR), contact details, email settings, order prefix |
| Activity log | Who changed what and when (orders, products, prices, settings) |
| Email notifications | Order received (customer + admin), dispatched, cancelled |

### 3.3 Platform Foundations (non-negotiable even in MVP)

- PDO prepared statements, output escaping, CSRF protection.
- `password_hash()` for all passwords; secure cookies.
- Server-side price, discount, shipping and stock validation.
- DB transactions for checkout and stock updates; idempotency key on order submission.
- Order items store product name/price snapshots.
- Private storage outside the web root; safe file uploads.
- Soft deletion for key records.
- Automated daily database backup (off-server copy).
- Error logging without exposing details to users.
- HTTPS.

---

## 4. Out of MVP Scope (Planned Later)

| Feature | Planned Phase |
|---------|---------------|
| Online payment gateways (JazzCash, Easypaisa, cards) | Post-MVP Phase A |
| Courier API integrations (TCS, Leopards, PostEx) | Post-MVP Phase A |
| Product reviews and ratings | Post-MVP Phase B |
| Wishlist | Post-MVP Phase B |
| Bulk Excel import/export, bulk price updates | Post-MVP Phase B |
| Automatic collections, Buy X Get Y, customer-specific promotions | Post-MVP Phase B |
| Abandoned cart recovery, back-in-stock alerts | Post-MVP Phase B |
| Suppliers and purchase orders | Post-MVP Phase C |
| Returns and refunds workflow (full) | Post-MVP Phase C |
| Advanced reports and analytics, Excel exports | Post-MVP Phase C |
| Homepage builder (drag-and-drop sections), menu manager | Post-MVP Phase C |
| SMS / WhatsApp automation | Post-MVP Phase C |
| Two-factor authentication for admins | Post-MVP Phase C |
| Redis queue, read replicas, CDN tuning, load testing at scale | Scale phase |
| Multi-currency, Urdu/RTL | Future |
| PWA (Add to Home Screen, offline assets) | Optional enhancement |

> Even though some items are deferred, the **database schema should anticipate them** (e.g., nullable fields, tables for reviews/wishlists can be created early) to avoid painful migrations later.

---

## 5. MVP User Flows

### 5.1 Customer: Buy a Product (Mobile)

1. Open site from a social/WhatsApp link → homepage loads fast.
2. Tap a category chip or search → product grid (2 columns).
3. Open a product → swipe images → select size and color chips → price updates.
4. Tap sticky **Add to Cart** → mini-cart appears.
5. Tap **Checkout** → enter phone, name, address (guest or login).
6. Choose shipping and payment (COD / bank transfer).
7. Review summary → tap sticky **Place Order**.
8. See confirmation with order number; receive email.
9. Later: track the order via account or order lookup.

### 5.2 Staff: Process an Order (Mobile)

1. Receive new-order email → open admin on phone.
2. Orders list (cards) → open order.
3. Verify items, address, payment → tap **Confirm**.
4. Print/share packing slip and label (PDF).
5. Pack → tap **Processing**.
6. Hand to courier → enter courier name and tracking number → tap **Dispatched** (customer is emailed).
7. When delivered (and COD collected) → **Delivered** + **Mark as Paid**.

### 5.3 Staff: Add a Product (Mobile)

1. Admin → Products → floating **+** button.
2. Enter name, category, description.
3. Upload photos from camera/gallery; reorder.
4. Add variants as expandable cards (size, color, SKU, price, stock).
5. Set status Active → Save → product appears on storefront immediately.

---

## 6. MVP Order & Payment Rules

- **Order statuses:** Pending Confirmation → Confirmed → Processing → Dispatched → Delivered; plus Cancelled and On Hold.
- **Payment statuses:** Pending, Paid, Failed (independent of order status).
- **Stock policy:** Stock is reserved at order placement and deducted/committed at confirmation; released on cancellation. (Policy configurable later.)
- **COD:** Payment marked Paid by staff after collection.
- **Bank transfer:** Customer uploads receipt (optional); staff verifies and marks Paid.
- **Order number:** `EMB-YYYY-NNNNNN`.

---

## 7. MVP Database Tables

| Group | Tables |
|-------|--------|
| Admin access | users, roles, permissions, role_permissions |
| Customers | customers, customer_addresses |
| Catalog | products, product_images, categories, product_categories, attributes, attribute_values, product_variants, collections, collection_products |
| Inventory | inventory, stock_movements, stock_reservations |
| Cart | carts, cart_items |
| Orders | orders, order_items, order_status_history |
| Payments | payments (with manual verification fields) |
| Shipping | shipping_zones, shipping_methods, shipments |
| Promotions | coupons |
| CMS | banners, pages, store_settings |
| Security/System | activity_logs, login_attempts, password_resets, email_logs |

Rules: InnoDB, `DECIMAL(12,2)` for money, unique SKU/order number/email, `utf8mb4`, UTC timestamps, snapshots in order items.

---

## 8. MVP Build Plan

Suggested timeline for one full-time developer (adjust to team size): **~10–12 weeks**.

| Sprint | Weeks | Focus | Deliverables |
|--------|-------|-------|--------------|
| 0 | 0.5 | Setup | Repo, environments, folder structure, Tailwind build, design tokens (mobile-first) |
| 1 | 1–2 | Foundation | Schema, config, PDO layer, auth, RBAC, CSRF, layouts (store + admin shells, responsive nav) |
| 2 | 3–4 | Catalog (admin) | Categories, products, variants, images, inventory, collections |
| 3 | 5–6 | Storefront | Homepage, shop, filters, search, product page (mobile-polished) |
| 4 | 7 | Cart & accounts | Cart, registration/login, saved addresses |
| 5 | 8–9 | Checkout & orders | Shipping rules, coupons, checkout, order creation (transactions), emails, order admin |
| 6 | 10 | Printing & dashboard | Invoice, packing slip, label PDFs; dashboard; activity log |
| 7 | 11–12 | Hardening & launch | CMS basics, SEO basics, security review, mobile device testing, backups, deployment, UAT |

**Definition of Done (per feature)**

- Functionality works and has tests for business logic.
- Permission checks enforced on the server.
- Passes the mobile acceptance checklist (section 2.2).
- Errors handled and logged; no sensitive data exposed.
- Documented briefly.

---

## 9. MVP Infrastructure

- Single cloud VPS (e.g., 2–4 vCPU, 4–8 GB RAM) with Nginx/Apache, PHP-FPM + OPcache, MySQL.
- Cloudflare in front (free tier acceptable) for CDN and basic protection.
- Daily encrypted off-server backups.
- Optional Redis for cache/sessions (can be added without redesign).
- Uptime monitoring and error alerts.

> The MVP is built to be **horizontally scalable later** (stateless app, config via environment, media separable). It is not sized for one million simultaneous users at launch.

---

## 10. MVP Quality Gates Before Launch

| Gate | Requirement |
|------|-------------|
| Security | No SQLi/XSS/CSRF findings; permissions verified for each role; uploads validated |
| Checkout integrity | Concurrency test shows no overselling or duplicate orders |
| Mobile | Checklist passed on real devices; Lighthouse mobile ≥ 80 (performance) and ≥ 90 (accessibility) |
| Data | Backup and restore tested |
| Content | Policies, contact info, logo, initial products loaded |
| UAT | Owner and staff complete real order flows on phone and desktop |

---

## 11. Assumptions and Open Items for the MVP

| # | Item | Default assumption |
|---|------|--------------------|
| 1 | Market | Pakistan only at launch |
| 2 | Payments | COD + bank transfer only |
| 3 | Shipping | Manual courier handling, flat rates by zone |
| 4 | Tax | None at launch (confirm) |
| 5 | Products | Variant-level stock; initial catalog loaded manually or via a one-time import script |
| 6 | Brand assets | Logo, colors, fonts to be supplied |
| 7 | Email | SMTP account available |
| 8 | Hosting | Cloud VPS provided/approved |

---

## 12. After the MVP — Recommended Order

1. **Phase A:** Online payments + courier APIs.
2. **Phase B:** Reviews, wishlist, bulk import/export, advanced promotions, abandoned cart.
3. **Phase C:** Suppliers/purchasing, returns/refunds, advanced reports, homepage builder, 2FA, notifications.
4. **Scale phase:** Redis queues, read replicas, CDN tuning, formal load testing and capacity planning.

---

## 13. MVP Summary

| Area | MVP Outcome |
|------|-------------|
| Storefront | Fast, mobile-first store with catalog, variants, cart, guest/registered checkout, order tracking |
| Admin | Mobile-friendly management of products, stock, orders, customers, coupons, shipping, content |
| Payments | COD and bank transfer with manual verification |
| Operations | Invoices, packing slips, labels, email notifications, activity log |
| Security | Secure-by-design foundations from day one |
| Experience | **Responsive on every screen and easy to use on phones** |
