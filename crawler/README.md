# RunMyPrint research crawler

Playwright-based competitive research. **Research/observation only** — outputs feed our own
catalog modeling and Playwright parity tests; we do not reuse competitor assets/branding.

## Setup
```powershell
npm install
npx playwright install chromium
```

## Run
```powershell
npm run screenshots   # captures the flow tour -> ../research/screenshots/
npm run crawl         # extracts top-20 products + options + prices -> ../research/data/
```
