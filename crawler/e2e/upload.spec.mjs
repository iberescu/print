import { test, expect } from '@playwright/test';
import { fileURLToPath } from 'url';
import { completeUpsell } from './helpers.mjs';

const artwork = fileURLToPath(new URL('./fixtures/artwork.png', import.meta.url));

// Upload-your-artwork workflow (req 9).
test('upload workflow: place artwork → upsell → cart', async ({ page }) => {
    await page.goto('/product/standard-business-cards');
    await page.getByRole('button', { name: /upload your design/i }).first().click();
    await page.waitForURL('**/design/**');

    await expect(page.locator('canvas')).toHaveCount(2);
    await expect(page.getByText(/upload your artwork/i)).toBeVisible();

    await page.locator('input[type="file"]').setInputFiles(artwork);
    await expect(page.getByText(/upload your artwork/i)).toBeHidden();

    await page.getByRole('button', { name: /add to cart/i }).click();
    await page.waitForURL('**/upsell');
    await completeUpsell(page);
    await page.waitForURL('**/cart');
    await expect(page.getByText(/free shipping/i).first()).toBeVisible();
    await expect(page.locator('img').first()).toBeVisible();
});
