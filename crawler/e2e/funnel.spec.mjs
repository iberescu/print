import { test, expect } from '@playwright/test';

test('storefront pages load', async ({ page }) => {
    await page.goto('/');
    await expect(page).toHaveTitle(/RunMyPrint/);

    await page.goto('/category/business-cards');
    await expect(page.getByRole('heading', { name: 'Business Cards', exact: true })).toBeVisible();

    await page.goto('/product/standard-business-cards');
    await expect(page.getByRole('button', { name: /design online/i })).toBeVisible();
});

test('full funnel: design → cart → checkout → paid', async ({ page }) => {
    await page.goto('/product/standard-business-cards');
    await page.getByRole('button', { name: /design online/i }).first().click();
    await page.waitForURL('**/design/**');
    await expect(page.locator('canvas')).toHaveCount(2); // fabric.js lower+upper canvas

    await page.getByRole('button', { name: /add to cart/i }).click();
    await page.waitForURL('**/cart');
    await expect(page.getByText(/free shipping/i).first()).toBeVisible(); // req 7
    await expect(page.getByRole('heading', { name: /put your brand on more/i })).toBeVisible(); // req 11

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
    await expect(page.getByText(/paid/i).first()).toBeVisible();
});

test('marketing feeds are valid', async ({ request }) => {
    const g = await request.get('/feed/google.xml');
    expect(g.ok()).toBeTruthy();
    expect(await g.text()).toContain('<rss');

    const r = await request.get('/feed/rtbhouse.xml');
    expect(r.ok()).toBeTruthy();
    expect(await r.text()).toContain('<products>');
});
