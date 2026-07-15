import { test, expect } from '@playwright/test';
import { fileURLToPath } from 'url';
import fs from 'fs';

// 10-logo upsell-funnel smoke: upload a logo in the designer, Review (wait),
// Add to cart (wait), Continue to the "Your logo on more products" step and
// wait for the pqSmartGenerator gallery to render real mockups.
//
// The third-party engine must be able to FETCH our logo URL, so this only
// produces results against a publicly reachable host:
//   APP_URL=https://runmyprint.com LOGO_FUNNEL=1 npx playwright test e2e/logo-funnel-10.spec.mjs --project=desktop --workers=1
test.skip(!process.env.LOGO_FUNNEL, 'explicit funnel smoke — set LOGO_FUNNEL=1 to run');

const outRoot = fileURLToPath(new URL('../artifacts/logo-funnel', import.meta.url));

const logos = Array.from({ length: 10 }, (_, i) => {
    const n = String(i + 1).padStart(2, '0');
    return { n, file: fileURLToPath(new URL(`./fixtures/logos/logo-${n}.png`, import.meta.url)) };
});

for (const { n, file } of logos) {
    test(`logo ${n}: editor → review → accessories → pqsg gallery renders results`, async ({ page }) => {
        test.setTimeout(420000);
        const dir = `${outRoot}/logo-${n}`;
        fs.mkdirSync(dir, { recursive: true });

        // --- editor: upload the logo -----------------------------------------
        await page.goto('/design/standard-business-cards?test=1');
        await page.waitForFunction(() => window.__rmpCanvas?.getObjects().some((o) => o.rmpRole === 'logo'));

        const [chooser] = await Promise.all([
            page.waitForEvent('filechooser'),
            page.getByRole('button', { name: /upload image/i }).click(),
        ]);
        await chooser.setFiles(file);
        // uploaded logo joins the canvas next to the seeded placeholder
        await page.waitForFunction(() => window.__rmpCanvas.getObjects()
            .filter((o) => o.rmpRole === 'logo' && !(o.getSrc?.() || '').includes('logo-placeholder')).length === 1);

        // a real customer removes the "YOUR LOGO HERE" placeholder — do the same
        // (select it, then the toolbar Delete). Keeps the review preview clean.
        await page.evaluate(() => {
            const c = window.__rmpCanvas;
            const ph = c.getObjects().find((o) => (o.getSrc?.() || '').includes('logo-placeholder'));
            if (ph) { c.setActiveObject(ph); c.requestRenderAll(); }
        });
        await page.getByRole('button', { name: /delete/i }).click();
        await page.waitForFunction(() => !window.__rmpCanvas.getObjects()
            .some((o) => (o.getSrc?.() || '').includes('logo-placeholder')));
        await page.waitForTimeout(800); // fonts/render settle
        await page.screenshot({ path: `${dir}/1-editor.png` });

        // --- review (wait 10 s, per test brief) -------------------------------
        await page.getByRole('button', { name: /^review/i }).click();
        await page.waitForURL('**/review');
        await page.waitForTimeout(10000);
        await page.screenshot({ path: `${dir}/2-review.png`, fullPage: true });

        // --- continue → upsell step 1 (final step: qty/material) -------------
        await page.getByRole('checkbox').first().check();
        await page.getByRole('button', { name: /add to cart/i }).click();
        await page.waitForURL('**/upsell');
        await expect(page.getByText(/1 of 4/i)).toBeVisible();
        await page.screenshot({ path: `${dir}/3a-upsell-finalize.png`, fullPage: true });
        await page.getByRole('button', { name: /continue/i }).last().click();

        // --- step 2 (accessories), wait 10 s ----------------------------------
        await expect(page.getByText(/2 of 4/i)).toBeVisible();
        await page.waitForTimeout(10000);
        await page.screenshot({ path: `${dir}/3-upsell-accessories.png`, fullPage: true });

        // --- continue → "Your logo on more products" (engine gallery) ---------
        // The internal engine (UPSELL_ENGINE=internal) renders native cards that
        // stream in from the feed proxy — no #pqsg-widget element, no client init.
        await page.getByRole('button', { name: /continue/i }).last().click();
        await expect(page.getByRole('heading', { name: /your logo on more products/i })).toBeVisible();
        await page.screenshot({ path: `${dir}/4-gallery-waiting.png`, fullPage: true });

        // the "generating" placeholder yields to the card grid once the first
        // mockups land (engine generation runs on the prod queue — allow minutes)
        await expect(page.getByText(/fresh from your design/i)).toBeVisible({ timeout: 240000 });
        // mockups stream in as the engine finishes tasks — wait until the count
        // stops growing (two quiet checks) or 60 s, then capture the gallery
        let imgs = 0;
        for (let quiet = 0, i = 0; quiet < 2 && i < 12; i++) {
            await page.waitForTimeout(5000);
            // the TransitionGroup gallery grid (grid-cols-2 → lg:grid-cols-4 cards)
            const now = await page.locator('div.grid.grid-cols-2 img').count();
            quiet = now === imgs ? quiet + 1 : 0;
            imgs = now;
        }
        console.log(`logo ${n}: engine gallery rendered ${imgs} image(s)`);
        await page.screenshot({ path: `${dir}/5-gallery-results.png`, fullPage: true });
        expect(imgs).toBeGreaterThan(0);
    });
}
