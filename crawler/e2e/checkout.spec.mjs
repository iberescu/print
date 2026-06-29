import { test, expect } from '@playwright/test';

// Full funnel: design → cart → checkout → paid (req 14 / 15).
test('full funnel: design → cart → checkout → paid', async ({ page }) => {
    await page.goto('/product/standard-business-cards');
    await page.getByRole('button', { name: /design online/i }).first().click();
    await page.waitForURL('**/design/**');
    await expect(page.locator('canvas')).toHaveCount(2);

    await page.getByRole('button', { name: /add to cart/i }).click();
    await page.waitForURL('**/cart');
    await expect(page.getByText(/free shipping/i).first()).toBeVisible();

    await page.getByRole('link', { name: /proceed to checkout/i }).click();
    await page.waitForURL('**/checkout');

    const f = page.locator('form').first().locator('input');
    await f.nth(0).fill('e2e@runmyprint.com');
    await f.nth(1).fill('E2E Tester');
    await f.nth(2).fill('1 Test Street');
    await f.nth(3).fill('Austin');
    await f.nth(4).fill('78701');

    await page.getByRole('button', { name: /pay/i }).click();
    await page.waitForURL('**/checkout/success**');
    await expect(page.getByRole('heading', { name: /order confirmed/i })).toBeVisible();
});
