import { test, expect } from '@playwright/test';

test('home page loads with brand, nav and products', async ({ page }) => {
    await page.goto('/');
    await expect(page).toHaveTitle(/RunMyPrint/);
    // free-shipping promise in the utility bar (req 7)
    await expect(page.getByText(/free shipping on orders over/i).first()).toBeVisible();
    // category nav + at least one product link
    await expect(page.getByRole('link', { name: 'Business Cards' }).first()).toBeVisible();
    await expect(page.locator('a[href^="/product/"]').first()).toBeVisible();
});

test('category page shows a product grid', async ({ page }) => {
    await page.goto('/category/business-cards');
    await expect(page.getByRole('heading', { name: 'Business Cards', exact: true })).toBeVisible();
    await expect(page.locator('a[href^="/product/"]').first()).toBeVisible();
});

test('product page: CTAs, free-shipping note and live price by quantity', async ({ page }) => {
    await page.goto('/product/standard-business-cards');

    await expect(page.getByRole('button', { name: /design online/i })).toBeVisible();
    await expect(page.getByRole('button', { name: /upload your design/i })).toBeVisible();
    await expect(page.getByText(/free shipping/i).first()).toBeVisible();

    // changing the quantity tier recomputes the headline price
    const price = page.locator('span.text-4xl').first();
    const qty = page.locator('.grid.grid-cols-2 > button');
    const count = await qty.count();
    expect(count).toBeGreaterThan(1);

    await qty.first().click();
    const lo = (await price.textContent())?.trim();
    await qty.nth(count - 1).click();
    const hi = (await price.textContent())?.trim();
    expect(lo).not.toBe(hi); // different tiers ⇒ different totals
});
