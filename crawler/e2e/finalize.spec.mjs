import { test, expect } from '@playwright/test';
import { reviewAndAdd, completeUpsell } from './helpers.mjs';

// Final step of the funnel (after Review, before accessories): the buyer can
// still change quantity and the options that don't affect the design surface
// (paper stock, finish, …). Surface-bound picks (size/corners) stay locked.
// Catalog-agnostic: drives whatever business-card product the category lists
// first, so it runs against both the seeded and the crawled catalogue. Some
// catalogues bake the material into the product (e.g. crawled "Matte Business
// Cards") — set FINALIZE_PRODUCT=<slug> to target one with open material
// groups, e.g. FINALIZE_PRODUCT=standard-business-cards on prod.

async function addBusinessCard(page) {
    if (process.env.FINALIZE_PRODUCT) {
        await page.goto(`/product/${process.env.FINALIZE_PRODUCT}`);
    } else {
        await page.goto('/category/business-cards');
        await page.locator('a[href*="/product/"]').first().click();
        await page.waitForURL('**/product/**');
    }
    await page.getByRole('button', { name: /design online/i }).first().click();
    await page.waitForURL('**/design/**');
    await reviewAndAdd(page); // design → review → approve → add
    await page.waitForURL('**/upsell');
}

const summaryTotal = (page) => page.locator('aside p.font-display').first().innerText();

test('review lands on the final step: quantity + material, surface options locked', async ({ page }) => {
    await addBusinessCard(page);

    await expect(page.getByRole('heading', { name: /final step/i })).toBeVisible();
    await expect(page.getByText(/1 of \d/i)).toBeVisible();
    await expect(page.getByRole('heading', { name: /^quantity$/i })).toBeVisible();

    // surface-bound options are shown as locked, not editable
    await expect(page.getByText(/locked to your approved design/i)).toBeVisible();

    // changing the quantity re-quotes the line server-side and updates the summary
    const before = await summaryTotal(page);
    const tiles = page.locator('section', { has: page.getByRole('heading', { name: /^quantity$/i }) })
        .getByRole('button');
    await Promise.all([
        page.waitForResponse((r) => /\/upsell\/finalize\b/.test(r.url())),
        tiles.last().click(),
    ]);
    await expect.poll(() => summaryTotal(page)).not.toBe(before);
});

test('changing the material re-prices and survives into the cart', async ({ page }) => {
    await addBusinessCard(page);

    // some catalogues bake the material into the product — nothing to change
    const groupCount = await page.locator('.grid.md\\:grid-cols-3').count();
    test.skip(!groupCount, 'no changeable material groups on this product — set FINALIZE_PRODUCT');

    // pick the last value of the first changeable option group (has a +delta in
    // both catalogues); the summary echoes the new label once the server agrees
    const group = page.locator('section').filter({ has: page.locator('.grid.md\\:grid-cols-3') }).first();
    const groupName = await group.getByRole('heading').innerText();
    const pick = group.getByRole('button').last();
    const pickedLabel = (await pick.locator('span.truncate').innerText()).trim();

    await Promise.all([
        page.waitForResponse((r) => /\/upsell\/finalize\b/.test(r.url())),
        pick.click(),
    ]);
    const row = page.locator('aside dl div', { hasText: groupName }).first();
    await expect(row).toContainText(pickedLabel);
    await page.waitForTimeout(800); // let the re-quote's follow-up GET commit (same convention as clickContinue)

    // the re-priced selection is what lands in the cart
    await completeUpsell(page);
    await page.waitForURL('**/cart');
    await expect(page.getByText(new RegExp(`${groupName}: ${pickedLabel}`))).toBeVisible();
});

test('material cards carry generated previews when available', async ({ page }) => {
    await addBusinessCard(page);
    const previews = page.locator('img[src*="option-previews"]');
    // seeded + generated catalogues have them; if none were generated yet the
    // cards fall back to textured placeholders — only assert when present
    if (await previews.count()) {
        await expect(previews.first()).toBeVisible();
    }
});
