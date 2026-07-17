import { test, expect } from '@playwright/test';
import { clickContinue, reviewAndAdd } from './helpers.mjs';

// The ads-step offer A/B: paid29 ($29 → $250 display ads) vs free500 (a $500
// Google Ads credit, free on orders ≥ $100). `?ab_ads=…` pins the variant
// (forced sessions are excluded from the experiment stats server-side).
// Placeholder design → no engine cost; the ads step renders for any capture.

async function toAdsStep(page, variant) {
    await page.goto('/design/standard-business-cards?test=1');
    await page.waitForFunction(() => window.__rmpCanvas && window.__rmpCanvas.getObjects().length > 0);
    await reviewAndAdd(page);
    await page.waitForURL('**/upsell');
    await page.goto(`/upsell?ab_ads=${variant}`);
    for (let i = 0; i < 5; i++) {
        if (await page.getByText(/4 of 4/i).count()) break;
        await clickContinue(page);
    }
    await expect(page.getByText(/4 of 4/i)).toBeVisible();
}

test('variant B: free $500 credit pitch, no $29 CTA, live qualification', async ({ page }) => {
    test.setTimeout(180000);
    await toAdsStep(page, 'free500');

    await expect(page.getByText(/FREE — \$500 in Google Ads credit/i).first()).toBeVisible();
    await expect(page.getByText(/orders over \$100/i).first()).toBeVisible();
    // nothing to buy: the $29 CTA must not exist in this variant
    await expect(page.getByRole('button', { name: /\$29/ })).toHaveCount(0);
    // a small starter cart doesn't qualify yet — the progress line shows the gap
    await expect(page.getByText(/more to unlock the free \$500 credit/i)).toBeVisible();

    // the cart mirrors the threshold offer
    for (let i = 0; i < 2; i++) {
        try { await clickContinue(page); } catch { /* redirect race */ }
        if (new URL(page.url()).pathname === '/cart') break;
    }
    await page.waitForURL('**/cart');
    await expect(page.getByText(/unlocks a FREE \$500 Google Ads credit/i)).toBeVisible();
});

test('variant A: the $29 for $250 pitch is intact', async ({ page }) => {
    test.setTimeout(180000);
    await toAdsStep(page, 'paid29');

    await expect(page.getByText(/Pay \$29, get/i).first()).toBeVisible();
    await expect(page.getByText(/\$250/).first()).toBeVisible();
    await expect(page.getByRole('button', { name: /add to my order — \$29/i })).toBeVisible();
    await expect(page.getByText(/\$500 Google Ads credit/i)).toHaveCount(0);
});
