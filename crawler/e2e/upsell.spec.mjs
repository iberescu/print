import { test, expect } from '@playwright/test';
import { completeUpsell, clickContinue, reviewAndAdd } from './helpers.mjs';

// Forced multi-step upsell before the cart (req 3) + related-product step (req 4).
async function addBusinessCard(page) {
    await page.goto('/product/matte-business-cards');
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
    await expect(page.getByText(/step 1 of 2/i)).toBeVisible();
});

test('business-card upsell offers a non-personalised card holder', async ({ page }) => {
    await addBusinessCard(page);
    // step 1 = brand; advance to the related-products step
    await clickContinue(page);

    await expect(page.getByRole('heading', { name: /complete your order/i })).toBeVisible();
    await expect(page.getByText(/card holder/i).first()).toBeVisible();
    await expect(page.getByText(/not personalised/i).first()).toBeVisible();

    // adding the holder works, then continue through to the cart. Wait for the
    // add to finish before continuing — otherwise the next visit cancels it.
    await Promise.all([
        page.waitForResponse((r) => /\/upsell\/add\//.test(r.url())),
        page.getByRole('button', { name: /add to order/i }).first().click(),
    ]);
    await expect(page.getByRole('button', { name: /added/i }).first()).toBeVisible();
    await completeUpsell(page);
    await page.waitForURL('**/cart');
    await expect(page.getByText(/card holder/i).first()).toBeVisible(); // it's in the cart
});
