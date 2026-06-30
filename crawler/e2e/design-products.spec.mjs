import { test, expect } from '@playwright/test';

// Item 3/4: the online designer must open for many products and show the right
// print format/orientation per product (A4 flyer → portrait, business card →
// landscape, banners → tall, square stickers, …).

async function openDesigner(page, slug) {
    await page.goto(`/product/${slug}`);
    await page.getByRole('button', { name: /design online/i }).first().click();
    await page.waitForURL('**/design/**');
    await page.waitForSelector('.canvas-container canvas');
    await page.waitForTimeout(400); // let fitCanvas settle
}

function orientation(w, h) {
    const r = w / h;
    if (r > 1.1) return 'landscape';
    if (r < 0.91) return 'portrait';
    return 'square';
}

// slug → expected canvas orientation
const PRODUCTS = [
    ['standard-business-cards', 'landscape'],
    ['rounded-corner-business-cards', 'landscape'],
    ['flyers', 'portrait'],
    ['posters', 'portrait'],
    ['brochures', 'landscape'],
    ['roll-up-banner', 'portrait'],
    ['vinyl-banner', 'landscape'],
    ['yard-signs', 'landscape'],
    ['custom-stickers', 'square'],
    ['window-decals', 'square'],
    ['letterhead', 'portrait'],
    ['envelopes', 'landscape'],
    ['notepads', 'portrait'],
    ['custom-t-shirts', 'portrait'],
    ['tote-bags', 'portrait'],
];

for (const [slug, expected] of PRODUCTS) {
    test(`designer opens for ${slug} with a ${expected} canvas`, async ({ page }) => {
        await openDesigner(page, slug);
        await expect(page.locator('canvas')).toHaveCount(2); // fabric lower + upper
        const { w, h } = await page.$eval('.canvas-container canvas', (el) => ({ w: el.width, h: el.height }));
        expect(orientation(w, h)).toBe(expected);
    });
}

// Item 4: choosing the A4 size on a flyer must give an A4 (portrait) canvas + label.
test('flyer with A4 selected shows the A4 format', async ({ page }, testInfo) => {
    await page.goto('/product/flyers');
    await page.getByRole('button', { name: /^A4/ }).first().click();
    await page.getByRole('button', { name: /design online/i }).first().click();
    await page.waitForURL('**/design/**');
    await page.waitForSelector('.canvas-container canvas');
    await page.waitForTimeout(400);

    const { w, h } = await page.$eval('.canvas-container canvas', (el) => ({ w: el.width, h: el.height }));
    expect(orientation(w, h)).toBe('portrait');

    // the format badge is desktop-only (hidden on mobile)
    if (testInfo.project.name === 'desktop') {
        await expect(page.locator('header').getByText(/·\s*A4/)).toBeVisible();
    }
});

// Surface manager: fold lines and no-print zones render in the designer.
test('a folded product shows a fold line in the designer', async ({ page }) => {
    await page.goto('/design/folded-business-cards');
    await page.waitForSelector('.canvas-container canvas');
    await expect(page.getByText(/fold line/i)).toBeVisible();
    expect(await page.locator('line[stroke="#9333ea"]').count()).toBeGreaterThan(0);
});

test('a banner surface shows a no-print zone in the designer', async ({ page }) => {
    await page.goto('/design/roll-up-banner');
    await page.waitForSelector('.canvas-container canvas');
    await expect(page.getByText(/no-print area/i)).toBeVisible();
    expect(await page.locator('rect[fill="rgba(15,23,42,0.42)"]').count()).toBeGreaterThan(0);
});

// Item 2: the template gallery appears BEFORE the editor and a pick opens the editor.
test('templates gallery appears before the editor and applies a pick', async ({ page }) => {
    await page.goto('/product/standard-business-cards');
    await page.getByRole('button', { name: /browse .* templates/i }).first().click();
    await page.waitForURL('**/design/standard-business-cards/templates*');

    await expect(page.getByRole('heading', { name: /choose a template/i })).toBeVisible();
    await expect(page.getByRole('button', { name: /start from scratch/i })).toBeVisible();
    expect(await page.locator('button:has(img)').count()).toBeGreaterThan(0);

    await page.locator('button:has(img)').first().click();
    await page.waitForURL(/\/design\/standard-business-cards\?.*template=/);
    await expect(page.locator('canvas')).toHaveCount(2);
});

// Print guides: bleed band + trim line + safe area render and can be toggled.
test('designer shows bleed / trim / safe-area guides and toggles them', async ({ page }) => {
    await page.goto('/design/standard-business-cards');
    await page.waitForSelector('.canvas-container canvas');

    await expect(page.locator('path[fill-rule="evenodd"]')).toHaveCount(1); // the bleed band
    await expect(page.getByText(/bleed — trimmed off/i)).toBeVisible();
    await expect(page.getByText(/safe area/i)).toBeVisible();

    await page.getByRole('button', { name: /Guides/i }).click();
    await expect(page.locator('path[fill-rule="evenodd"]')).toHaveCount(0);
});

test('templates gallery: start from scratch opens a blank editor', async ({ page }) => {
    await page.goto('/design/standard-business-cards/templates');
    await page.getByRole('button', { name: /start from scratch/i }).click();
    await page.waitForURL(/\/design\/standard-business-cards(\?|$)/);
    await expect(page.locator('canvas')).toHaveCount(2);
});
