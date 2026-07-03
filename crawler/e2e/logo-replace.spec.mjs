import { test, expect } from '@playwright/test';
import { fileURLToPath } from 'url';

const artwork = fileURLToPath(new URL('./fixtures/artwork.png', import.meta.url));

// Clicking the seeded logo placeholder opens the replace popup; picking a file
// swaps the placeholder in place (same spot, same box) instead of stacking a
// second image on the canvas.

async function clickLogoOnCanvas(page) {
    // The logo's centre in CSS pixels relative to the fabric upper-canvas.
    const pos = await page.evaluate(() => {
        const c = window.__rmpCanvas;
        const o = c.getObjects().find((x) => x.rmpRole === 'logo');
        if (!o) return null;
        const p = o.getCenterPoint();
        const z = c.getZoom();
        return { x: p.x * z, y: p.y * z };
    });
    expect(pos).not.toBeNull();
    const box = await page.locator('canvas.upper-canvas').boundingBox();
    await page.mouse.click(box.x + pos.x, box.y + pos.y);
}

test('clicking the logo placeholder opens the replace popup and swaps the logo in place', async ({ page }) => {
    await page.goto('/design/matte-business-cards?test=1');
    await page.waitForFunction(() => window.__rmpCanvas?.getObjects().some((o) => o.rmpRole === 'logo'));

    const before = await page.evaluate(() => {
        const o = window.__rmpCanvas.getObjects().find((x) => x.rmpRole === 'logo');
        const p = o.getCenterPoint();
        return { x: p.x, y: p.y, count: window.__rmpCanvas.getObjects().length };
    });

    await clickLogoOnCanvas(page);
    await expect(page.getByRole('heading', { name: /add your logo/i })).toBeVisible();

    const [chooser] = await Promise.all([
        page.waitForEvent('filechooser'),
        page.getByRole('button', { name: /upload your logo/i }).click(),
    ]);
    await chooser.setFiles(artwork);

    // placeholder replaced — still exactly one logo, real image, same centre
    await page.waitForFunction(() => {
        const os = window.__rmpCanvas.getObjects().filter((o) => o.rmpRole === 'logo');
        return os.length === 1 && !(os[0].getSrc?.() || '').includes('logo-placeholder');
    });
    const after = await page.evaluate(() => {
        const o = window.__rmpCanvas.getObjects().find((x) => x.rmpRole === 'logo');
        const p = o.getCenterPoint();
        return { x: p.x, y: p.y, count: window.__rmpCanvas.getObjects().length };
    });
    expect(after.count).toBe(before.count); // swapped, not added
    expect(Math.abs(after.x - before.x)).toBeLessThan(1.5);
    expect(Math.abs(after.y - before.y)).toBeLessThan(1.5);
    await expect(page.getByRole('heading', { name: /add your logo/i })).toBeHidden();
});

test('clicking an uploaded logo re-opens the popup with replace + remove', async ({ page }) => {
    await page.goto('/design/matte-business-cards?test=1');
    await page.waitForFunction(() => window.__rmpCanvas?.getObjects().some((o) => o.rmpRole === 'logo'));

    // replace the placeholder first
    await clickLogoOnCanvas(page);
    const [chooser] = await Promise.all([
        page.waitForEvent('filechooser'),
        page.getByRole('button', { name: /upload your logo/i }).click(),
    ]);
    await chooser.setFiles(artwork);
    await page.waitForFunction(() => {
        const os = window.__rmpCanvas.getObjects().filter((o) => o.rmpRole === 'logo');
        return os.length === 1 && !(os[0].getSrc?.() || '').includes('logo-placeholder');
    });

    // clicking the real logo now offers replace/remove
    await page.mouse.click(10, 10); // somewhere neutral first: header, clears state
    await clickLogoOnCanvas(page);
    await expect(page.getByRole('heading', { name: /replace your logo/i })).toBeVisible();
    await expect(page.getByRole('button', { name: /replace logo/i })).toBeVisible();

    // remove deletes the logo and closes the popup
    await page.getByRole('button', { name: /remove logo/i }).click();
    await page.waitForFunction(() => !window.__rmpCanvas.getObjects().some((o) => o.rmpRole === 'logo'));
    await expect(page.getByRole('heading', { name: /replace your logo/i })).toBeHidden();
});

test('dragging the logo placeholder does not open the popup', async ({ page }) => {
    await page.goto('/design/matte-business-cards?test=1');
    await page.waitForFunction(() => window.__rmpCanvas?.getObjects().some((o) => o.rmpRole === 'logo'));

    const pos = await page.evaluate(() => {
        const c = window.__rmpCanvas;
        const o = c.getObjects().find((x) => x.rmpRole === 'logo');
        const p = o.getCenterPoint();
        const z = c.getZoom();
        return { x: p.x * z, y: p.y * z };
    });
    const box = await page.locator('canvas.upper-canvas').boundingBox();
    await page.mouse.move(box.x + pos.x, box.y + pos.y);
    await page.mouse.down();
    await page.mouse.move(box.x + pos.x - 60, box.y + pos.y + 40, { steps: 8 });
    await page.mouse.up();

    await expect(page.getByRole('heading', { name: /add your logo/i })).toBeHidden();
});
