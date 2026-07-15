import { test, expect } from '@playwright/test';
import { fileURLToPath } from 'url';
import fs from 'fs';
import { clickContinue, reviewAndAdd } from './helpers.mjs';

const outDir = fileURLToPath(new URL('../artifacts/website-offer', import.meta.url));

// "$10 website" upsell: a capture WITH a real logo but WITHOUT a website sees,
// on the ads step, the free-website offer (MacBook preview + $10 add-on) in
// place of the Layout.ai ad credit. Costs a full engine capture (mockups +
// website design), hence the gate:
//
//   APP_URL=... WEBSITE_OFFER=1 npx playwright test e2e/website-offer.spec.mjs --project=desktop --workers=1
test.skip(!process.env.WEBSITE_OFFER, 'engine-billed — set WEBSITE_OFFER=1 to run');

const logo = fileURLToPath(new URL('./fixtures/logos/logo-01.png', import.meta.url));

test('no-URL capture gets the $10 website offer with a generated preview', async ({ page }) => {
    test.setTimeout(420000);

    // --- designer: upload a real logo, leave the website placeholder alone ----
    await page.goto('/design/standard-business-cards?test=1');
    await page.waitForFunction(() => window.__rmpCanvas?.getObjects().some((o) => o.rmpRole === 'logo'));
    const [chooser] = await Promise.all([
        page.waitForEvent('filechooser'),
        page.getByRole('button', { name: /upload image/i }).click(),
    ]);
    await chooser.setFiles(logo);
    await page.waitForFunction(() => window.__rmpCanvas.getObjects()
        .filter((o) => o.rmpRole === 'logo' && !(o.getSrc?.() || '').includes('logo-placeholder')).length === 1);

    await reviewAndAdd(page);
    await page.waitForURL('**/upsell');
    await expect(page.getByText(/1 of 4/i)).toBeVisible();

    // --- walk: finalize → accessories → gallery → ads ------------------------
    // Streaming gallery cards shift the layout and can swallow a Continue click;
    // verify the step marker advanced after each click, retrying once.
    const toStep = async (n) => {
        for (let attempt = 0; attempt < 2; attempt++) {
            try {
                await clickContinue(page); // can itself time out when the click is swallowed (no POST fires)
                await expect(page.getByText(new RegExp(`${n} of 4`, 'i'))).toBeVisible({ timeout: 8000 });
                return;
            } catch { /* click swallowed — try once more */ }
        }
        await clickContinue(page);
        await expect(page.getByText(new RegExp(`${n} of 4`, 'i'))).toBeVisible({ timeout: 8000 });
    };
    await toStep(2); // accessories
    await toStep(3); // "your logo on more products"
    await toStep(4); // ads step (the website offer for no-URL)

    // the Layout.ai ad-credit pitch stays for everyone…
    await expect(page.getByText(/layout\.ai/i).first()).toBeVisible();
    await expect(page.getByText(/\$250/).first()).toBeVisible();
    // …and the website offer takes the place of the (site-less) ad examples
    await expect(page.getByText(/free website/i).first()).toBeVisible();
    await expect(page.getByText(/lifetime hosting/i).first()).toBeVisible();
    expect(await page.locator('svg[data-mac]').count()).toBe(1);
    // the display-ads AND search-ads example sections need a site — both gone
    await expect(page.getByText(/your google display ads/i)).toHaveCount(0);
    await expect(page.getByText(/your google search ads/i)).toHaveCount(0);

    // the generated homepage streams into the MacBook screen (flash model, ~15s;
    // generous margin for queue depth)
    await expect(page.locator('svg[data-mac] image')).toHaveCount(1, { timeout: 180000 });
    fs.mkdirSync(outDir, { recursive: true });
    await page.waitForTimeout(1200); // let the screen image paint
    await page.screenshot({ path: `${outDir}/offer.png`, fullPage: true });

    // --- the $10 add-on lands in the cart -------------------------------------
    await page.getByRole('button', { name: /add my website/i }).click();
    await expect(page.getByText(/✓ added to your order — \$10/i)).toBeVisible();

    try {
        await clickContinue(page);
    } catch { await clickContinue(page); } // same swallowed-click retry on the way out
    await page.waitForURL('**/cart');
    await expect(page.getByText(/free \.com domain/i).first()).toBeVisible();
    await expect(page.getByText('$10.00').first()).toBeVisible();
});
