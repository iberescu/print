import { test, expect } from '@playwright/test';
import { completeUpsell, clickContinue, reviewAndAdd } from './helpers.mjs';

// Forced multi-step upsell before the cart (req 3) + related-product step (req 4).
async function addBusinessCard(page) {
    await page.goto('/product/standard-business-cards');
    await page.getByRole('button', { name: /design online/i }).first().click();
    await page.waitForURL('**/design/**');
    await reviewAndAdd(page); // design → review → approve → add
    await page.waitForURL('**/upsell');
}

test('cart is gated until the upsell steps are completed', async ({ page }) => {
    await addBusinessCard(page);
    // jumping straight to the cart bounces back into the upsell flow
    await page.goto('/cart');
    await expect(page).toHaveURL(/\/upsell/);
    // placeholder designs now register an image_url capture (the review preview),
    // so the gallery + ads steps join finalize + accessories
    await expect(page.getByText(/1 of 4/i)).toBeVisible();
});

test('business-card upsell offers a non-personalised card holder', async ({ page }) => {
    await addBusinessCard(page);
    // step 1 = the final step (quantity/material); step 2 = accessories
    await expect(page.getByRole('heading', { name: /final step/i })).toBeVisible();
    await clickContinue(page);

    await expect(page.getByRole('heading', { name: /complete your order/i })).toBeVisible();
    await expect(page.getByText(/card holder/i).first()).toBeVisible();
    await expect(page.getByText(/not personalised/i).first()).toBeVisible();

    // adding the holder works, then continue through to the cart. The accessory
    // pool has many products now — add the CARD HOLDER's card specifically. Wait
    // for the add to finish before continuing — otherwise the next visit cancels it.
    const holderCard = page.locator('div.rounded-2xl').filter({ hasText: /card holder/i }).first();
    await Promise.all([
        page.waitForResponse((r) => /\/upsell\/add\//.test(r.url())),
        holderCard.getByRole('button', { name: /add to order/i }).click(),
    ]);
    await expect(holderCard.getByRole('button', { name: /added/i })).toBeVisible();
    await completeUpsell(page);
    await page.waitForURL('**/cart');
    await expect(page.getByText(/card holder/i).first()).toBeVisible(); // it's in the cart
});

// A real brand (non-placeholder website) adds the third-party gallery step:
// review → accessories → pqsg step (widget) → cart.
test('designer brand adds the third-party upsell step', async ({ page }) => {
    await page.goto('/design/standard-business-cards?test=1');
    await page.waitForFunction(() => window.__rmpCanvas && window.__rmpCanvas.getObjects().length > 0);
    await page.evaluate(() => {
        const c = window.__rmpCanvas;
        c.getObjects().find((o) => o.rmpRole === 'url')?.set('text', 'example.com');
        c.requestRenderAll();
    });
    await reviewAndAdd(page);
    await page.waitForURL('**/upsell');

    await expect(page.getByText(/1 of 4/i)).toBeVisible();          // final step first
    await clickContinue(page);                                            // then accessories
    await clickContinue(page);
    await expect(page.getByRole('heading', { name: /your logo on more products/i })).toBeVisible();
    // internal engine renders native cards: a "generating" placeholder first,
    // then the "Fresh from your design" grid streams in (no #pqsg-widget any more)
    await expect(page.getByText(/generating ideas with your logo|fresh from your design/i).first()).toBeVisible();

    // Layout.ai ad-credit step ($250 of Google Display ads for $29)
    await clickContinue(page);
    await expect(page.getByText(/layout\.ai/i).first()).toBeVisible();
    await expect(page.getByText(/\$250/).first()).toBeVisible();

    await completeUpsell(page);
    await page.waitForURL('**/cart');
});
