import { test, expect } from '@playwright/test';
import { fileURLToPath } from 'url';
import { completeUpsell, reviewAndAdd } from './helpers.mjs';

const artwork = fileURLToPath(new URL('./fixtures/artwork.png', import.meta.url));

// Upload-your-artwork workflow (req 9).
test('upload workflow: place artwork → upsell → cart', async ({ page }) => {
    test.slow(); // longest funnel chain — needs headroom under full-suite parallel load
    await page.goto('/product/matte-business-cards');
    await page.getByRole('button', { name: /upload your design/i }).first().click();
    await page.waitForURL('**/design/**');

    await expect(page.locator('canvas')).toHaveCount(2);
    await expect(page.getByText(/upload your artwork/i)).toBeVisible();

    await page.locator('input[type="file"]').setInputFiles(artwork);
    await expect(page.getByText(/upload your artwork/i)).toBeHidden();

    await reviewAndAdd(page); // design → review → approve → add
    await page.waitForURL('**/upsell');
    await completeUpsell(page);
    await page.waitForURL('**/cart');
    await expect(page.getByText(/free shipping/i).first()).toBeVisible();
    await expect(page.locator('img').first()).toBeVisible();
});
