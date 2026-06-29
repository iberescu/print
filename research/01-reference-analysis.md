# Reference analysis & research plan

## A. Editor reference (from `editor_ideas/`)
The two screenshots are MOO's business-card editor — the exact UX target for our fabric.js designer.

**Layout to replicate (RunMyPrint-branded):**
- **Top step bar:** `1 Front · 2 Backs · 3 Review & Purchase` with a green primary CTA top-right ("Design backs →").
- **Top context bar:** logo · product name ("Standard size Business Cards") · template name · `Change pack · Save · PDF Proof`.
- **Toolbar (dark):** font-family dropdown · font-size dropdown (9, 9.5, 10 … scrollable) · color swatch · **B** · *I* · align (left/center/right).
- **Canvas:** centered card, live-editable text objects (name / title / phone / email) + graphic; "Flip over" affordance.
- **Bottom bar:** `Corners · Layout · Background` poppers.

→ Maps directly to fabric.js: editable `IText` objects for placeholders, `Rect`/background fill, image objects for logo/graphic, front/back canvases, toolbar bound to `activeObject` props.

## B. Confirmed Gemini models (verified live against the API key)
| Purpose | Model ID | Req |
|---|---|---|
| Hero / product imagery (high quality) | `gemini-3-pro-image` ("nano banana 2 / Pro") | 6, 11 |
| Bulk / fast imagery | `gemini-3.1-flash-image` | 6 |
| Template JSON generation | `gemini-3.5-flash` | 17 |
| Vision quality scoring of rendered templates | `gemini-3.5-flash` (multimodal) | 10 |
| (alt) photoreal product shots | `imagen-4.0-generate-001` | 6 |

API base: `https://generativelanguage.googleapis.com/v1beta/models/{model}:generateContent?key=...`

## C. Vistaprint research plan (Phase 1)
Goal: understand flow + extract top-20 products w/ options & prices; build a parity baseline for tests (req 1, 3, 19).

**Screenshot tour:** home → business-cards landing → product/config page → editor → upsell (post-add) → cart.
**Crawl targets per product:** title, URL, category, option axes (format/size, paper/finish, color sides, page count where relevant, quantity tiers) and price-per-quantity.

**Risk:** Vistaprint runs Akamai bot protection. Mitigations, in order:
1. Realistic Chromium context (UA, viewport, locale, human-like waits).
2. If walled → use the public sitemap / category JSON endpoints.
3. Fallback → assemble a representative catalog from observable product structure (clearly labelled as modeled, not scraped) so the rest of the build is unblocked.

**Parity testing (req 19):** the captured screenshots become golden references; Playwright e2e walks our equivalent flow and we compare step-by-step (presence of step bar, options, free-shipping nudge, upsell, cart) — iterate until the workflow matches.
