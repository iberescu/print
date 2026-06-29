# RunMyPrint — Build Roadmap

Living plan mapping the 20 requirements to phases. Updated as work progresses.
Legend: ✅ done · 🟡 in progress · ⬜ pending

> **Scope note:** This is a full e-commerce platform + AI tooling + crawler + deploy.
> Built incrementally, foundation-first. Each phase produces something runnable.

---

## Phase 0 — Foundation & tooling  ✅
- ✅ Docker Compose: app (PHP 8.4) · nginx · MySQL 8 · Redis · node/Vite  *(req 1)*
- ✅ Laravel 13.8 install (PHP 8.4 required; fpm runs as root for Windows bind-mount writes)
- ✅ Inertia v3 + Vue 3 + Tailwind v4 + Vite — verified rendering at http://localhost:8080  *(req 12)*
- ✅ Brand wiring: RunMyPrint (APP_NAME, green theme placeholder)  *(req 16)*
- ✅ `config/shop.php` (free-shipping threshold, Gemini + Stripe config); secrets in `.env`

## Phase 1 — Competitive research  🟡  *(req 1, 3, 19)*
- ✅ Playwright screenshot tour: home + business-cards landing captured (editor behind Cloudflare)
- ✅ Flow analysis from user screenshots → parity baseline (`research/02-flow-analysis.md`)
- ✅ Crawler (Chromium + Gemini vision): top-20 products + options + per-qty prices → JSON/CSV (`crawl.mjs`)
- ✅ Deep stealth crawler (`crawl-deep.mjs`): realistic UA + jitter + persistent profile + headed Cloudflare wait (human solves CAPTCHA), enumerates options × quantities

## Phase 2 — Catalog & storefront  ✅  *(req 4, 5, 16)*
- ✅ Schema: categories, products, options/values (price deltas + badges), quantity tiers
- ✅ Seed catalog — 6 categories, **20 products** with options + tiered pricing
- ✅ **27 Gemini images** (hero + categories + products) via `gemini-3-pro-image`  *(req 6)*
- ✅ Home / Category / Product pages — "print atelier" design (Fraunces + emerald), Inertia+Vue+Tailwind  *(req 5)*
- ✅ Product buy panel: Paper Stock/Corners/Quantity, live price, dual CTA (Design / Upload)  *(req 9 entry point)*
- ✅ Dynamic free-shipping nudge toward the $50 threshold
- Note: catalog is **modeled** from flow-analysis signals; live price-crawl (req 3) deferred (Cloudflare) — optional later

## Phase 3 — Online designer & upload  ✅ (core)  *(req 8, 9, 18)*
- ✅ fabric.js v6 editor: step bar, text toolbar (font/size/color/B/I/align), Front/Back, background swatches, +Text / Upload / Delete
- ✅ "Upload artwork" vs "Design online" branch  *(req 9)*
- ✅ Save design (front+back JSON + preview) → cart (session)
- ✅ **Google Fonts only** in designer/templates (per project rule)
- ⬜ Later: show Phase-4 templates in editor; Corners/Layout panels; true cart-item persistence

## Phase 4 — Business-card templates  ⬜  *(req 10, 17)*
- ⬜ Generate 200 B2B templates as fabric.js JSON via `gemini-3.5-flash`  *(req 17)*
      placeholders: logo, company name, email, phone
- ⬜ Render each → `gemini-3.5-flash` vision quality score → auto-fix loop until ≥ 9  *(req 10)*
- ⬜ Expose templates in the designer

## Phase 5 — Cart, shipping & upsell  ⬜  *(req 7, 11, 15)*
- ⬜ Cart
- ⬜ Free shipping over $50, surfaced after add-to-cart  *(req 7)*
- ⬜ Upsell to reach $50 ("Related Accessories" + "match your design on…")  *(req 15)*
- ⬜ SVG mockups: user's logo/design composited on product photos;
      logo extracted from uploaded artwork via Gemini  *(req 11)*

## Phase 6 — Checkout & payments  ⬜  *(req 14)*
- ⬜ Stripe checkout, order creation, free-shipping rule applied

## Phase 7 — Marketing feeds  ⬜  *(req 20)*
- ⬜ Product feed: Google Shopping (XML) + RTB House

## Phase 8 — Testing & parity  ⬜  *(req 13, 19)*
- ⬜ Playwright e2e for the full purchase workflow
- ⬜ Compare workflow + design quality vs Vistaprint screenshots; iterate

## Phase 9 — Deployment  ⬜  *(req 2)*
- ⬜ DigitalOcean deploy (Docker), using DO token

---

### Confirmed technical facts
- **Gemini models** (verified live): image `gemini-3-pro-image` ("nano banana 2"), fast image
  `gemini-3.1-flash-image`, text/vision `gemini-3.5-flash`.
- **Stack:** Laravel 13.8 / PHP 8.4 · Inertia 3 + Vue 3 + Tailwind 4 · MySQL 8 · Redis 7.
- **Local run:** `docker compose up -d` → http://localhost:8080 ; Vite dev: `docker compose --profile dev up node`.

### Risks / notes
- Vistaprint editor + deep pages behind Cloudflare → crawler uses fallbacks; product data may be modeled.
- "100% similar" (req 19) = UX flow & quality parity, not copyrighted assets/branding.
- Pasted API keys (DO, Gemini) were exposed in chat → **rotate after build**.
