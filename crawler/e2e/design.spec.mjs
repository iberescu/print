import { test, expect } from '@playwright/test';
import { completeUpsell } from './helpers.mjs';

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

// req 11 now lives as a forced upsell step rather than a cart section.
test('designer → add routes into the brand upsell step, then on to the cart', async ({ page }) => {
    await page.goto('/product/standard-business-cards');
    await page.getByRole('button', { name: /design online/i }).first().click();
    await page.waitForURL('**/design/**');
    await page.getByRole('button', { name: 'Text' }).click();

    await page.getByRole('button', { name: /add to cart/i }).click();
    await page.waitForURL('**/upsell');
    await expect(page.getByRole('heading', { name: /put your brand on more/i })).toBeVisible();

    // add one branded surface, then walk the steps to the cart
    await page.getByRole('button', { name: /add to order/i }).first().click();
    await completeUpsell(page);
    await page.waitForURL('**/cart');
    await expect(page.getByText(/free shipping/i).first()).toBeVisible();
});
