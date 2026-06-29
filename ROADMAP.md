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

## Phase 5 — Cart, shipping & upsell  ✅ (core)  *(req 7, 11, 15)*
- ✅ Session cart + server-side pricing (quantity tier + option deltas)
- ✅ Free-shipping bar / "$X more for free shipping" after add-to-cart  *(req 7)*
- ✅ Upsell to reach $50 — "Reach free shipping with" recommended products  *(req 15)*
- ✅ "Put your design on more" — design composited on other products  *(req 11, first cut)*
- ✅ Header cart badge; funnel verified product→design→add→cart (Playwright)
- ⬜ Later: true SVG/perspective mockups + Gemini logo-extraction from uploaded artwork (full req 11)

## Phase 6 — Checkout & payments  ✅ (core)  *(req 14)*
- ✅ Checkout page (contact + shipping) + order summary
- ✅ Orders table; order created with free-shipping rule applied
- ✅ Stripe Checkout Session integration (`stripe/stripe-php`) + webhook to mark paid
- ✅ Demo-mode fallback when no Stripe keys → order completes + success page
- ⬜ Add Stripe TEST keys to `backend/.env` to enable live card payment

## Phase 7 — Marketing feeds  ✅  *(req 20)*
- ✅ Google Shopping feed: `/feed/google.xml` (RSS 2.0 + g: namespace, 20 products)
- ✅ RTB House feed: `/feed/rtbhouse.xml` (generic product XML)
- ✅ Absolute URLs + images; well-formed XML verified

## Phase 8 — Testing & parity  ✅ (core)  *(req 13, 19)*
- ✅ Playwright e2e suite (`crawler/e2e/funnel.spec.mjs`): storefront, full funnel (design→cart→checkout→paid), feeds — all green
- ✅ Workflow parity vs Vistaprint documented (`research/03-parity.md`)
- ⬜ Later: pixel visual-diff vs VP golden screenshots; broaden coverage (upload, mobile)

## Phase 9 — Deployment  ✅  *(req 2)*
- ✅ DigitalOcean Droplet (Ubuntu 24.04, 2vCPU/4GB, Docker) provisioned via API + SSH deploy
- ✅ **Live at http://174.138.35.202** — full funnel e2e-verified on prod (3/3 green)
- ✅ Deploy script `deploy/droplet-deploy.sh` (clone → prod .env → build → migrate → seed → serve :80)
- ⬜ Later: domain + HTTPS (runmyprint.com), managed DB, CI/CD redeploy

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
