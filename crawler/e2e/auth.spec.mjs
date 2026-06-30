import { test, expect } from '@playwright/test';

// Customer accounts: checkout is gated, register/login work.
test('checkout requires an account (guest is redirected to login)', async ({ page }) => {
    await page.goto('/checkout');
    await page.waitForURL('**/login');
    await expect(page.getByRole('button', { name: /^sign in$/i })).toBeVisible();
    await expect(page.getByText(/create an account/i)).toBeVisible();
});

test('a customer can register, see their account, sign out and sign back in', async ({ page }) => {
    const email = `e2e${Date.now()}${Math.floor(Math.random() * 1e6)}@runmyprint.com`;

    await page.goto('/register');
    await page.fill('input[autocomplete=name]', 'E2E Tester');
    await page.fill('input[type=email]', email);
    await page.fill('input[autocomplete=new-password]', 'password123');
    await page.locator('input[type=password]').nth(1).fill('password123');
    await page.getByRole('button', { name: /create account/i }).click();
    await page.waitForURL('**/account');
    await expect(page.getByText(email)).toBeVisible();

    // sign out
    await page.getByRole('button', { name: /sign out/i }).first().click();
    await page.waitForTimeout(1200);

    // sign back in
    await page.goto('/login');
    await page.fill('input[type=email]', email);
    await page.fill('input[type=password]', 'password123');
    await page.getByRole('button', { name: /^sign in$/i }).click();
    await page.waitForURL('**/account');
    await expect(page.getByText(email)).toBeVisible();
});
