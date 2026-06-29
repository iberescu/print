import { test, expect } from '@playwright/test';

// Online designer workflow (req 8 / 9 / 17 / 18).
test('online designer loads, opens the template picker and applies a template', async ({ page }) => {
    await page.goto('/product/standard-business-cards');
    await page.getByRole('button', { name: /design online/i }).first().click();
    await page.waitForURL('**/design/**');

    // fabric.js mounts a lower + upper canvas
    await expect(page.locator('canvas')).toHaveCount(2);
    await expect(page.getByRole('button', { name: 'Text' })).toBeVisible(); // "+ Text" tool

    // open the templates drawer and apply the first design
    await page.getByRole('button', { name: /Templates/i }).click();
    await expect(page.getByRole('heading', { name: /choose a template/i })).toBeVisible();
    await page.locator('.fixed button:has(img)').first().click();

    // drawer closes only when loadFromJSON succeeds
    await expect(page.getByRole('heading', { name: /choose a template/i })).toBeHidden();
    await expect(page.locator('canvas')).toHaveCount(2);
});

test('designer → add to cart shows free shipping + brand mockups', async ({ page }) => {
    await page.goto('/product/standard-business-cards');
    await page.getByRole('button', { name: /design online/i }).first().click();
    await page.waitForURL('**/design/**');
    await expect(page.locator('canvas')).toHaveCount(2);

    // default design seeds brand placeholders; add one more text element
    await page.getByRole('button', { name: 'Text' }).click();

    await page.getByRole('button', { name: /add to cart/i }).click();
    await page.waitForURL('**/cart');

    // req 7: free-shipping messaging after add-to-cart
    await expect(page.getByText(/free shipping/i).first()).toBeVisible();
    // req 11: the user's brand laid into other product mockups
    await expect(page.getByRole('heading', { name: /put your brand on more/i })).toBeVisible();
});
