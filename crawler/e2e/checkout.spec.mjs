import { test, expect } from '@playwright/test';
import { completeUpsell } from './helpers.mjs';

// Full funnel: design → forced upsell → cart → checkout → paid (req 3 / 14 / 15).
test('full funnel: design → upsell → cart → checkout → paid', async ({ page }) => {
    // checkout now requires an account — register a fresh one first
    const email = `e2e${Date.now()}${Math.floor(Math.random() * 1e6)}@runmyprint.com`;
    await page.goto('/register');
    await page.fill('input[autocomplete=name]', 'E2E Tester');
    await page.fill('input[type=email]', email);
    await page.fill('input[autocomplete=new-password]', 'password123');
    await page.locator('input[type=password]').nth(1).fill('password123');
    await page.getByRole('button', { name: /create account/i }).click();
    await page.waitForURL('**/account');

    await page.goto('/product/standard-business-cards');
    await page.getByRole('button', { name: /design online/i }).first().click();
    await page.waitForURL('**/design/**');
    await expect(page.locator('canvas')).toHaveCount(2);

    await page.getByRole('button', { name: /add to cart/i }).click();
    await page.waitForURL('**/upsell');     // upsell comes before the cart
    await completeUpsell(page);
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
