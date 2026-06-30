import { test, expect } from '@playwright/test';

// Admin area is gated: guests are bounced to the login screen.
test('admin dashboard requires login', async ({ page }) => {
    await page.goto('/admin');
    await page.waitForURL('**/admin/login');
    await expect(page.getByRole('button', { name: /sign in/i })).toBeVisible();
    await expect(page.locator('input[type=email]')).toBeVisible();
    await expect(page.locator('input[type=password]')).toBeVisible();
});

test('admin login rejects bad credentials', async ({ page }) => {
    await page.goto('/admin/login');
    await page.fill('input[type=email]', 'nobody@example.com');
    await page.fill('input[type=password]', 'wrong-password');
    await page.getByRole('button', { name: /sign in/i }).click();
    await expect(page.getByText(/do not match/i)).toBeVisible();
});
