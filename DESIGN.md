# Embroidery Store — UI/UX Design Specification

**Version:** 1.0  
**Reference:** User-provided Shoply storefront screenshot  
**Design direction:** Premium editorial commerce, minimal, soft-neutral, rounded and image-led  
**Applies to:** Customer storefront and admin portal

---

# 1. Design Intent

The supplied reference image has a distinctive visual language:
- Soft warm-grey and pale sage backgrounds.
- Large rounded hero and promotional panels.
- Strong black typography with generous whitespace.
- Compact pill-shaped navigation.
- Editorial photography mixed with product cards.
- A large search panel overlapping the hero.
- Horizontal category tiles.
- A high-contrast black daily-deals section.
- Wide editorial promotional banners.
- A large typographic marquee.
- A dark, restrained footer.

For the embroidery store, preserve the layout rhythm and visual character but replace unrelated fashion categories and generic merchandise with embroidery motifs, patches, textile close-ups, thread details, garment applications and collection storytelling.

The reference is a direction, not a pixel-perfect template. The product catalog and store workflow take priority over decorative elements.

# 2. Brand and Visual Principles

1. Premium but approachable.
2. Product photography is the hero; UI chrome stays quiet.
3. Large editorial typography for campaigns, compact readable type for commerce data.
4. Rounded corners used consistently, not randomly.
5. Soft neutral surfaces create calm; black panels provide contrast.
6. Product cards show essential purchase information without clutter.
7. Mobile usability is designed intentionally, not treated as a squeezed desktop.
8. Accessibility and clarity outrank visual effects.

# 3. Color Tokens

| Token | Suggested value | Use |
|---|---|---|
| Canvas | `#F7F7F3` | Main page background |
| Surface | `#FFFFFF` | Cards, menus, forms |
| Soft sage | `#E7EAE2` | Hero/editorial panels |
| Pale stone | `#EFEFE9` | Category tiles and soft sections |
| Ink | `#111210` | Primary text |
| Muted ink | `#666A63` | Secondary text |
| Line | `#E3E4DE` | Borders/dividers |
| Dark panel | `#111210` | Deals and footer |
| Accent | `#C7D84A` | Small highlights, sale markers and focus details |
| Accent dark | `#30351B` | Accent text on pale accent |
| Success | `#26734A` | Success states |
| Warning | `#A66B16` | Pending/warning states |
| Danger | `#B33C36` | Errors/destructive actions |

Use accent sparingly. Product imagery should provide most of the color. Ensure text contrast is accessible; never use pale accent text on white for important information.

# 4. Typography

Suggested font pairing:
- Display/headings: `Manrope` or `Plus Jakarta Sans`.
- Body/UI: `Inter` or system sans-serif.
- Prices and numeric tables: use tabular numerals.

Scale:
- Hero title: clamp(2.5rem, 6vw, 5.5rem), tight line-height.
- Section heading: 1.5–2rem.
- Product title: 0.95–1.1rem.
- Body: 0.95–1rem.
- Helper/meta: 0.75–0.875rem.
- Admin tables: 0.85–0.95rem.

Use sentence case. Avoid all-caps for long labels. Keep line length comfortable.

# 5. Layout and Spacing

- Main content max width: approximately 1440px.
- Desktop page gutter: 32–48px.
- Tablet gutter: 24px.
- Mobile gutter: 16px.
- Section vertical spacing: 64–96px desktop; 40–56px mobile.
- Grid gap: 16–24px.
- Card radius: 18–26px.
- Large editorial panel radius: 32–48px.
- Buttons: 999px pill for primary storefront actions; modest radius for admin controls.
- Use a consistent 4/8px spacing rhythm.

# 6. Storefront Header

Desktop:
- Brand mark/wordmark at left.
- Center or right-aligned primary navigation in small pill controls.
- Search, account, wishlist and cart utilities.
- Sticky header may be enabled after scroll; avoid consuming excessive height.
- Announcement strip above header, optionally dismissible.

Mobile:
- Compact logo, search icon, cart icon and menu control.
- Navigation opens in an accessible drawer.
- Search is easy to reach.
- Cart count remains visible.
- No hover-only actions.

# 7. Homepage Structure Based on Reference

## Section 1 — Announcement strip
- Thin top strip with optional message and CTA.
- Admin controls content, URL, dates and visibility.
- Dismissal preference may be stored for the session.
- Avoid rotating messages too quickly.

## Section 2 — Editorial hero
- Large pale-sage rounded panel.
- Left: eyebrow label, oversized headline, short statement and primary CTA.
- Right: strong lifestyle/product photograph featuring embroidered patchwork or a close-up garment application.
- Optional floating mini product/collection card.
- Desktop height around 420–560px depending on content.
- Mobile: stack image and copy; do not crop the product focal point.

Suggested copy:
- Eyebrow: “Crafted details. Distinctive finishes.”
- Headline: “Embroidery that makes every detail matter.”
- Supporting line: “Explore patches and motifs made to bring your ideas to life.”
- CTA: “Explore the collection”

## Section 3 — Search discovery panel
- Large rounded white panel overlaps the bottom edge of hero on desktop.
- Search field with icon and clear label.
- Quick links: Recent, Popular motifs, New designs, View all.
- Search suggestions must be keyboard accessible.
- On mobile, panel follows hero instead of overlapping if overlap harms usability.

## Section 4 — Popular categories
- Heading: “Explore popular categories”.
- Horizontal category tiles with short names and portrait product imagery.
- Suggested categories: Floral Motifs, Neckline Designs, Bridal Patches, Sleeve Motifs, Traditional, Contemporary.
- Tiles link to category pages.
- Desktop: 4–6 visible tiles; mobile: horizontal swipe with visible partial next tile.
- Use actual category artwork, not generic fashion stock images.

## Section 5 — Daily deals / featured offers
- High-contrast black rounded panel, echoing the reference.
- Heading and “View all” link.
- Product cards with pale pastel backgrounds or neutral image frames.
- Show product image, name, current price, compare-at price where valid, discount label and quick add.
- Wishlist and cart actions must have accessible labels.
- Sale price must be accurate and server-calculated.
- Do not use fake countdown timers.

## Section 6 — Editorial brand/quality feature
- Wide soft-sage panel with product close-ups, embroidery texture or workshop imagery.
- Left: short brand proposition and CTA.
- Right: layered product/lifestyle imagery or a small feature list.
- Example: “Made for the details people notice.”
- Feature chips: “Carefully finished”, “Multiple sizes”, “Worldwide delivery” only if operationally true.

## Section 7 — New arrivals / collection tiles
- Editorial heading and compact image-led tiles.
- Possible groups: New Motifs, Bridal Edit, Floral Stories, Everyday Patches.
- Keep categories relevant to embroidery products.
- Link to a collection page.

## Section 8 — Typographic marquee
- Large repeated phrase with small circular arrow motif.
- Suggested copy: “DETAILS MAKE THE DESIGN” or “MADE TO BE NOTICED”.
- Decorative only; respect reduced-motion preference.
- Do not use a marquee as the only way to communicate important information.

## Section 9 — Large campaign banner
- Full-width rounded image with dark overlay.
- Campaign title, supporting copy, email/newsletter CTA or collection CTA.
- Image must have a mobile crop.
- Ensure text contrast and avoid placing text over visually busy areas.

## Section 10 — Footer
- Dark background.
- Brand statement, shop links, customer support links, policy links, newsletter, social links and contact.
- Include payment/shipping statements only when accurate.
- Compact copyright and legal links.

# 8. Storefront Components

## Product card
Required:
- Product image with consistent aspect ratio.
- Product title.
- Starting price or selected price, clearly labeled.
- Compare-at price only when legitimate.
- Sale badge where applicable.
- Optional quick-add when a default/variant selection is safe.
- Wishlist action.
- Stock/made-to-order indication where useful.

States:
- Default.
- Hover/focus.
- Out of stock.
- Made to order.
- On sale.
- Loading.
- Added to cart.
- Image unavailable.

## Product detail
- Breadcrumbs.
- Gallery with thumbnails.
- Product name and SKU (SKU may be visually secondary).
- Price and valid sale price.
- Variant controls with labels and availability.
- Quantity selector.
- Add-to-cart primary action.
- Stock/lead-time message.
- Description, care/specification and delivery details.
- Related products.
- Reviews only if enabled and moderated.

## Buttons
- Primary: ink background, white label, pill shape.
- Secondary: white/transparent with ink border.
- Text link: understated with arrow.
- Destructive: clear danger color and confirmation.
- Disabled: visibly disabled and non-interactive.
- Loading: preserve width and communicate progress.

## Forms
- Persistent labels, not placeholder-only.
- Inline validation.
- Clear error summary for long forms.
- Keyboard-friendly focus.
- Input types appropriate to content.
- Country-aware phone/address fields.

## Feedback
- Toast for low-impact actions.
- Inline error for form validation.
- Modal confirmation for destructive actions.
- Full page success state for order completion.
- Never rely only on color or animation.

# 9. Cart and Checkout Design

Cart:
- Clear item image, product name, variant label, quantity and price.
- Editable quantity and remove.
- Coupon field.
- Shipping estimate when possible.
- Clear subtotal, discount, shipping, tax and total.
- Empty cart illustration/CTA.

Checkout:
- Calm, low-distraction layout.
- Contact and address fields grouped logically.
- Shipping methods with cost and estimated range.
- Payment methods as selectable cards.
- Bank transfer instructions shown only when selected.
- Order summary remains visible on desktop; collapses on mobile.
- Guest/account-required behavior comes from store settings.
- Avoid surprise fees; display final payable amount before submit.
- Order success page shows order number and next steps.

# 10. Customer Account Design

Use a simple dashboard with:
- Welcome/summary.
- Recent order card.
- Order list and status chips.
- Address book.
- Profile/security.
- Wishlist.
- Order detail timeline.

Status chips must pair color with text:
- Pending confirmation.
- Confirmed.
- Processing.
- Dispatched.
- Delivered.
- Cancelled.
- On hold.

# 11. Admin Design Direction

The admin portal should share typography and neutral brand tokens, but prioritize density, clarity and speed over the editorial storefront aesthetic.

Recommended admin style:
- Light background `#F5F6F3`.
- White surfaces.
- Dark navy/ink text.
- Muted borders.
- Restrained green/olive accent.
- Fixed left sidebar on desktop.
- Compact top bar with search, notifications, profile and breadcrumb.
- Main content area with consistent max width.
- Tables with sticky headers where useful.
- Clear status badges.
- Responsive sidebar drawer on mobile/tablet.

## Admin shell
Sidebar:
- Overview
- Catalog
  - Products
  - Categories
  - Collections
- Inventory
- Orders
- Payments
- Shipping
- Promotions
- Customers
- Website
  - Homepage
  - Banners
  - Pages & menus
- Reports
- Team & permissions
- Activity log
- Settings

Top bar:
- Breadcrumb/title.
- Global search (optional MVP).
- Notification indicator.
- Help link.
- User menu.

## Dashboard
KPI cards:
- Sales in selected period.
- Orders.
- Pending confirmation.
- Average order value.
- Low-stock count.

Below:
- Sales trend chart.
- Recent orders table.
- Top products.
- Inventory alerts.

Every KPI must define what it counts and respect role permissions.

## Admin tables
- Search and filters above table.
- Sortable columns where supported.
- Pagination and result count.
- Bulk actions only for safe operations.
- Row action menu.
- Empty/loading/error states.
- Confirm destructive bulk changes.
- On mobile, convert rows into readable stacked cards or allow controlled horizontal scrolling.

## Product editor
Use tabs or clearly grouped panels:
1. General.
2. Media.
3. Pricing.
4. Variants.
5. Inventory.
6. Categories & collections.
7. Shipping.
8. SEO.
9. Publishing.

Show/hide fields based on inventory mode. Variant matrix should be understandable and support explicit valid combinations.

## Order detail
- Header with order number and status.
- Customer and delivery details.
- Item table.
- Payment summary.
- Shipping/dispatch panel.
- Activity timeline.
- Internal notes.
- Print actions.
- Status changes must be validated and confirmed.

## Homepage builder
MVP can use an ordered list of predefined section types rather than a full drag-and-drop page builder.
- Toggle visibility.
- Reorder sections.
- Edit section content.
- Select banner/image.
- Preview before publishing.
- Save draft and publish.

# 12. Responsive Breakpoints

Suggested Tailwind-aligned breakpoints:
- Small mobile: below 640px.
- Large mobile/small tablet: 640–767px.
- Tablet: 768–1023px.
- Desktop: 1024–1279px.
- Wide desktop: 1280px and above.

Behavior:
- Product grid: 2 columns mobile, 3 tablet, 4 desktop (adjust to image/card width).
- Admin sidebar: drawer below desktop breakpoint.
- Hero: stacked on mobile, split layout desktop.
- Category rail: horizontal scroll on mobile.
- Deals panel: horizontal scroll or stacked cards on mobile.
- Checkout summary: collapsible on mobile.
- Tables: card transformation or safe horizontal scroll.

# 13. Motion and Interaction

- Use subtle 150–250ms transitions.
- Small image zoom only where it improves product inspection.
- Avoid parallax that harms performance.
- Respect `prefers-reduced-motion`.
- No auto-playing video with sound.
- No essential information hidden behind hover.
- Skeleton loading only for content that genuinely loads asynchronously.
- Use optimistic UI only when rollback/error handling is reliable.

# 14. Image Direction and Asset Rules

Preferred photography:
- High-resolution embroidery close-ups.
- Macro thread texture.
- Patches photographed on neutral backgrounds.
- Motifs applied to fabric/garments.
- Flat lays with careful natural lighting.
- Consistent color balance and crop.
- Editorial workshop/craft imagery for campaign panels.

Image handling:
- Use separate product gallery images and marketing banners.
- Store original and optimized derivatives.
- Generate responsive sizes.
- Prefer WebP/AVIF with fallback.
- Add descriptive alt text.
- Use focal-point controls for banner crop if supported.
- Do not use unrelated stock categories from the reference.

# 15. Accessibility and Content Rules

- WCAG 2.2 AA should be the target.
- Keyboard access for menus, dialogs, variant controls and forms.
- Focus must remain visible.
- Images need appropriate alt text.
- Form errors must identify the field and explain correction.
- Buttons must have clear accessible names.
- Status must not be communicated by color alone.
- Avoid tiny text on mobile.
- Product dimensions and size labels must be unambiguous.
- Prices must show currency.
- Delivery estimates must not be presented as guarantees unless operationally guaranteed.

# 16. Design QA Checklist

- [ ] Reference's soft-neutral and editorial feel is recognizable.
- [ ] Embroidery-specific imagery replaces generic fashion products.
- [ ] Hero headline and CTA work at mobile and desktop.
- [ ] Search is keyboard accessible.
- [ ] Category tiles have useful labels and links.
- [ ] Deal cards show accurate pricing.
- [ ] Product variants are clear and selectable.
- [ ] Out-of-stock and made-to-order states are distinct.
- [ ] Cart and checkout show complete totals.
- [ ] Checkout policy matches admin setting.
- [ ] Admin tables are usable on narrow screens.
- [ ] Admin actions show permission and loading states.
- [ ] All images have appropriate crop/alt text.
- [ ] Reduced motion is respected.
- [ ] Contrast and focus states are checked.
- [ ] Empty, loading, success and error states are designed.

---

# 17. HTML Preview Mapping

The companion HTML preview demonstrates:
- Storefront hero and announcement strip.
- Search discovery panel.
- Embroidery category tiles.
- Daily deal product cards.
- Editorial feature and collection strip.
- Campaign banner and footer.
- Admin portal toggle.
- Admin dashboard KPI cards, order table and sidebar.
- Product editor concept with inventory mode selection.
- Order detail concept and status actions.

It is a static front-end concept using sample data. It does not connect to PHP, MySQL, real authentication, real payments or real inventory. It is intended to validate visual direction and information hierarchy before implementation.
