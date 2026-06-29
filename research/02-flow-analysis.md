# Vistaprint flow analysis → RunMyPrint parity baseline (req 19)

Captured from `vistaprint_screens/` (user-provided) + crawler screenshots. This is the
golden workflow our Playwright tests assert against and our UX mirrors.

## The full purchase workflow
```
Home
 └─ Category (e.g. Business Cards)
     └─ Product page  ............................. [vista1]
         options: Paper Stock · Corners · Quantity (price/unit)
         CTAs: "Browse our templates"  |  "Upload your design"  |  "Let us design it"
         ├─ Browse templates → Online Designer (fabric.js)  [editor_ideas/, vista2]
         └─ Upload your design → upload artwork
     └─ Editor / "Final Steps"  ................... [vista2]
         Front/Back preview · Quantity · Paper Thickness (Standard / Premium ★Recommended / Premium Plus)
     └─ Add to cart
         └─ "Added to cart" confirmation  ......... [vista4]
             • "Your order qualifies for free shipping!"           (req 7)
             • "Match your design on…" Flyers/Postcards/Notepads   (req 11 — design-on-product upsell)
             • "Continue to cart"
         └─ "Related Accessories" cross-sell  ..... [vista3]   (req 15 — reach free-shipping threshold)
     └─ Cart → Checkout (Stripe)                                  (req 14)
```

## Per-screen detail

### Product page (vista1)
- **Layout:** left = image gallery (hero + thumbnail strip + favorite heart); right = buy panel.
- **Buy panel:** promo line · description · delivery estimates (date + price, "Free on orders over $X") ·
  **price + price-per-unit** · option dropdowns (**Paper Stock**, **Corners**, **Quantity**) ·
  primary CTAs **Browse templates** / **Upload your design** / **Let us design it**.
- **Build note:** product options are axes with price deltas; quantity drives unit price (tiered pricing).

### Editor / Final Steps (vista2)
- Card canvas with **Front / Back** toggle; right rail "Final Steps".
- **Quantity** dropdown; **Paper Thickness** as radio-cards: Standard (−$5.99), **Premium (★Recommended, "increases the price")**, Premium Plus (+$7.00) — each w/ thumbnail + blurb + price delta.
- **Build note:** the "Recommended" upsell badge is a conversion pattern to replicate.

### Added-to-cart (vista4)
- Category mega-nav across top; black promo bar ("free economy shipping on all orders $100+").
- Confirmation: "✓ Added to cart — <product>" · 🚚 free-shipping qualification · **Continue to cart**.
- **"More to explore: match your design on…"** — the SAME design rendered onto other products
  (2-Day Cards, Flyers, Postcards, Notepads). **← This is req 11**: composite the user's
  logo/design onto product mockups (we'll do it with SVG + the extracted/uploaded logo).

### Related Accessories (vista3)
- "Your order qualifies for free shipping!" persistent.
- Cross-sell grid (card holders) w/ image, title, blurb, From-price, Quantity, Add. (req 15)

## Catalog signals (for seeding, Phase 2)
- Standard Business Cards: **$10.00** = $0.20 ea × 50 (promo); paper stocks Matte/…; Corners option; qty tiers.
- Accessories priced "From $8.99 / $11.99 / $16.99". Flyers From $44.99, Postcards From $21.99, Notepads From $18.99, 2-Day Cards From $15.99.
- Categories: Business Cards · Postcards & Print Advertising · Signs/Banners/Posters · Stickers & Labels ·
  Clothing & Bags · Promotional Products · Packaging · Invitations/Gifts/Stationery · Wedding · Logo/Websites/Social · Design Services.

## Differences for RunMyPrint
- Free-shipping threshold **$50** (not $100).  • Brand: RunMyPrint (own assets, not VP's).
- Editor reference = MOO-style toolbar from `editor_ideas/` (cleaner than VP's).
