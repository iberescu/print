# Workflow parity vs Vistaprint (req 19)

Our purchase workflow mirrors Vistaprint's, asserted by the Playwright e2e suite
(`crawler/e2e/funnel.spec.mjs` — all green).

| Step | Vistaprint | RunMyPrint | Parity |
|---|---|---|---|
| Home | promo hero, category grid, featured | hero + category grid + bestsellers | ✅ |
| Product | image gallery + Paper Stock/Corners/Quantity + price-per-unit + Browse-templates/Upload | gallery + same option axes + live price-per-unit + Design-online/Upload | ✅ |
| Designer | front/back, text toolbar, templates | fabric.js front/back, font/size/color/B-I-align toolbar, template picker | ✅ |
| After add-to-cart | "added" + free-shipping msg + "match your design on…" + related accessories | flash + free-shipping bar + "Put your design on more" + "Reach free shipping with" | ✅ |
| Cart | items, subtotal, shipping | items, subtotal, free/$4.99 shipping, total | ✅ |
| Checkout | contact/shipping + card | contact/shipping form + Stripe Checkout | ✅ |

**Intentional differences:** brand = RunMyPrint (own assets, not VP's); free-shipping threshold **$50** (VP uses $100);
warmer "print atelier" aesthetic (emerald + Fraunces) rather than VP's corporate blue.

**Design quality:** storefront, designer and templates were reviewed against the captured VP screenshots
(`vistaprint_screens/`, `research/screenshots/`); AI templates are scored by Gemini vision in a fix-loop toward ≥9.

**Pending:** pixel/visual-diff against golden VP screenshots; broader e2e coverage (upload flow, mobile).
