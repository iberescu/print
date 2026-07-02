import { test, expect } from '@playwright/test';

// Mobile-only checks (run under the Pixel 5 project).
test.describe('mobile', () => {
    test.beforeEach(({}, testInfo) => {
        test.skip(testInfo.project.name !== 'mobile', 'mobile-only');
    });

    test('hamburger opens the menu and navigates to a category', async ({ page }) => {
        await page.goto('/');
        const burger = page.getByRole('button', { name: /open menu/i });
        await expect(burger).toBeVisible();
        await burger.click();

        // category links live in the slide-out drawer (the nav containing "Shop")
        const drawer = page.locator('nav').filter({ hasText: 'Shop' });
        await drawer.getByRole('link', { name: 'Stickers & Labels' }).click();
        await page.waitForURL('**/category/stickers-labels');
        await expect(page.getByRole('heading', { name: 'Stickers & Labels', exact: true })).toBeVisible();
    });

    test('online designer fits the screen and text edits via the on-screen input', async ({ page }) => {
        await page.goto('/product/matte-business-cards');
        await page.getByRole('button', { name: /design online/i }).first().click();
        await page.waitForURL('**/design/**');
        await expect(page.locator('canvas')).toHaveCount(2);

        // the canvas is scaled to fit the viewport (no horizontal overflow)
        const box = await page.locator('canvas').first().boundingBox();
        const vw = page.viewportSize().width;
        expect(box.width).toBeLessThanOrEqual(vw + 1);

        // adding text selects it → the mobile-friendly "Edit text" input appears
        await page.getByRole('button', { name: 'Text' }).click();
        const edit = page.getByPlaceholder('Edit text');
        await expect(edit).toBeVisible();
        await edit.fill('Hello Mobile');
        await expect(edit).toHaveValue('Hello Mobile');
    });
});
