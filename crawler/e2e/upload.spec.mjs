import { test, expect } from '@playwright/test';
import { fileURLToPath } from 'url';

const artwork = fileURLToPath(new URL('./fixtures/artwork.png', import.meta.url));

// Upload-your-artwork workflow (req 9).
test('upload workflow: place artwork then add to cart', async ({ page }) => {
    await page.goto('/product/standard-business-cards');
    await page.getByRole('button', { name: /upload your design/i }).first().click();
    await page.waitForURL('**/design/**');

    await expect(page.locator('canvas')).toHaveCount(2);
    // upload mode shows the drop prompt until artwork is placed
    await expect(page.getByText(/upload your artwork/i)).toBeVisible();

    // drive the hidden file input
    await page.locator('input[type="file"]').setInputFiles(artwork);

    // once placed, the prompt overlay is gone
    await expect(page.getByText(/upload your artwork/i)).toBeHidden();

    await page.getByRole('button', { name: /add to cart/i }).click();
    await page.waitForURL('**/cart');
    await expect(page.getByText(/free shipping/i).first()).toBeVisible();
    // the cart line carries a design preview
    await expect(page.locator('img').first()).toBeVisible();
});
