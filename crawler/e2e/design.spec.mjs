import { test, expect } from '@playwright/test';
import { clickContinue, completeUpsell, reviewAndAdd } from './helpers.mjs';

// Online designer workflow (req 8 / 9 / 17 / 18).
test('online designer loads, opens the template picker and applies a template', async ({ page }) => {
    await page.goto('/product/standard-business-cards');
    await page.getByRole('button', { name: /design online/i }).first().click();
    await page.waitForURL('**/design/**');

    await expect(page.locator('canvas')).toHaveCount(2);     // fabric lower + upper canvas
    await expect(page.getByRole('button', { name: 'Text' })).toBeVisible();

    await page.getByRole('button', { name: /Templates/i }).click();
    await expect(page.getByRole('heading', { name: /choose a template/i })).toBeVisible();
    await page.locator('.fixed button:has(img)').first().click();

    await expect(page.getByRole('heading', { name: /choose a template/i })).toBeHidden();
    await expect(page.locator('canvas')).toHaveCount(2);
});

// req: a review step between the editor and the cart.
test('review step shows the design and gates add-to-cart behind approval', async ({ page }) => {
    await page.goto('/product/standard-business-cards');
    await page.getByRole('button', { name: /design online/i }).first().click();
    await page.waitForURL('**/design/**');

    await page.getByRole('button', { name: /^review/i }).click();
    await page.waitForURL('**/review');
    await expect(page.getByRole('heading', { name: /review your design/i })).toBeVisible();
    await expect(page.getByText(/double-check the following details/i)).toBeVisible();
    await expect(page.locator('img[alt$="preview"]')).toBeVisible();

    const add = page.getByRole('button', { name: /add to cart/i });
    await expect(add).toBeDisabled();
    await page.getByRole('checkbox').first().check();
    await expect(add).toBeEnabled();
});

// Funnel: review → final step (qty/material) → accessories → (third-party
// gallery when a real brand was captured) → cart. Placeholder designs get the
// final-step + accessories steps only.
test('designer → add routes into the upsell flow, then on to the cart', async ({ page }) => {
    await page.goto('/product/standard-business-cards');
    await page.getByRole('button', { name: /design online/i }).first().click();
    await page.waitForURL('**/design/**');
    await page.getByRole('button', { name: 'Text' }).click();

    await reviewAndAdd(page); // design → review → approve → add
    await page.waitForURL('**/upsell');
    await expect(page.getByRole('heading', { name: /final step/i })).toBeVisible();
    await clickContinue(page);
    await expect(page.getByRole('heading', { name: /complete your order/i })).toBeVisible();

    // add one accessory, then walk the steps to the cart
    await page.getByRole('button', { name: /add to order/i }).first().click();
    await completeUpsell(page);
    await page.waitForURL('**/cart');
    await expect(page.getByText(/free shipping/i).first()).toBeVisible();
});
