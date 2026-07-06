import { test, expect } from '@playwright/test';

// AI logo maker: standalone page + online-designer integration. Every
// generation is a real Replicate call (~15 s, costs money), so the suite is
// env-gated like the logo funnel:
//
//   LOGO_AI=1 npx playwright test e2e/logo-maker.spec.mjs --project=desktop --workers=1
//
// Works against the seeded and crawled catalogues (standard-business-cards
// exists in both). On public hosts the post-download gallery also receives
// real engine mockups; on localhost the engine can't fetch the logo URL, so
// only the section itself is asserted.
test.skip(!process.env.LOGO_AI, 'set LOGO_AI=1 to run the (paid) logo generation suite');

const BASE = process.env.APP_URL || 'http://localhost:8080';
const isLocal = BASE.includes('localhost');

async function fillBrief(page, company = 'Atlas Coffee', industry = 'specialty coffee roastery') {
    await page.getByPlaceholder(/harbor & co/i).last().fill(company);
    await page.getByPlaceholder(/coffee roastery, law firm/i).last().fill(industry);
}

test('standalone page: content, validation, generation, refine, downloads, gallery', async ({ page }) => {
    test.setTimeout(420000);
    await page.goto(`${BASE}/logo-maker`);

    // --- content is all there -------------------------------------------------
    await expect(page.getByRole('heading', { level: 1 })).toContainText(/designed by ai/i);
    await expect(page.getByText(/unlimited/i).first()).toBeVisible();                  // stats strip
    await expect(page.locator('img[src*="logo-samples"]')).not.toHaveCount(0);         // industry gallery
    expect(await page.locator('details').count()).toBeGreaterThanOrEqual(6);           // FAQ
    const ld = await page.locator('script[type="application/ld+json"]').first().textContent();
    expect(ld).toContain('FAQPage');

    // --- validation: no generate until the brief is complete -------------------
    const generateBtn = page.getByRole('button', { name: /generate my logo/i });
    await expect(generateBtn).toBeDisabled();
    await fillBrief(page);
    await expect(generateBtn).toBeEnabled();

    // --- generate two concepts -------------------------------------------------
    await generateBtn.click();
    await expect(page.locator('img[src*="/storage/logos/"]')).toHaveCount(2, { timeout: 120000 });

    // --- "more like this" iterates on a concept ---------------------------------
    const card = page.locator('img[src*="/storage/logos/"]').first().locator('..').locator('..');
    await card.hover();
    await card.getByRole('button', { name: /more like this/i }).click({ force: true });
    await expect(page.locator('img[src*="/storage/logos/"]')).toHaveCount(4, { timeout: 120000 });

    // --- download bundle: SVG fires immediately, PNG on demand ------------------
    const svgDl = page.waitForEvent('download', { timeout: 15000 });
    await page.getByRole('button', { name: /download & continue/i }).first().click();
    expect((await svgDl).suggestedFilename()).toBe('logo.svg');

    await expect(page.locator('#logo-gallery')).toBeVisible();
    const pngDl = page.waitForEvent('download', { timeout: 30000 });
    await page.getByRole('button', { name: /↓ png/i }).click();
    expect((await pngDl).suggestedFilename()).toBe('logo.png');

    // --- the upsell engine puts the logo on products (public hosts only) --------
    if (!isLocal) {
        await page.locator('#pqsg-widget img').first().waitFor({ timeout: 120000 });
        expect(await page.locator('#pqsg-widget img').count()).toBeGreaterThan(0);
    }
});

test('designer: logo placeholder popup offers AI and swaps the placeholder', async ({ page }) => {
    test.setTimeout(300000);
    await page.goto(`${BASE}/design/standard-business-cards?test=1`);
    await page.waitForFunction(() => window.__rmpCanvas && window.__rmpCanvas.getObjects().length > 0);

    // click the placeholder through fabric's event bus (screen-coord clicks are
    // brittle against canvas scaling)
    await page.evaluate(() => {
        const c = window.__rmpCanvas;
        const logo = c.getObjects().find((o) => o.rmpRole === 'logo');
        c.fire('mouse:down', { target: logo });
        c.fire('mouse:up', { target: logo });
    });
    await expect(page.getByRole('heading', { name: /add your logo|replace your logo/i })).toBeVisible();

    await page.getByRole('button', { name: /create one with ai/i }).click();
    await expect(page.getByRole('heading', { name: /ai logo builder/i })).toBeVisible();

    await fillBrief(page, 'Northarc Advisory', 'management consulting');
    await page.getByRole('button', { name: /generate my logo/i }).click();
    await page.locator('img[src*="/storage/logos/"]').first().waitFor({ timeout: 120000 });

    const before = await page.evaluate(() => window.__rmpCanvas.getObjects().length);
    await page.getByRole('button', { name: /place on my design/i }).first().click();
    await expect(page.getByRole('heading', { name: /ai logo builder/i })).toBeHidden({ timeout: 30000 });

    // the placeholder was REPLACED (same object count) by a rasterised PNG
    const state = await page.evaluate(() => {
        const c = window.__rmpCanvas;
        const logo = c.getObjects().find((o) => o.rmpRole === 'logo');
        return { count: c.getObjects().length, src: (logo?.getSrc?.() || '').slice(0, 22) };
    });
    expect(state.count).toBe(before);
    expect(state.src).toContain('data:image/png');
});

test('designer: toolbar AI Logo button inserts a fresh logo object', async ({ page }) => {
    test.setTimeout(300000);
    await page.goto(`${BASE}/design/standard-business-cards?test=1`);
    await page.waitForFunction(() => window.__rmpCanvas && window.__rmpCanvas.getObjects().length > 0);

    await page.getByRole('button', { name: /ai logo/i }).click();
    await expect(page.getByRole('heading', { name: /ai logo builder/i })).toBeVisible();

    await fillBrief(page, 'Copper Kettle', 'craft brewery');
    await page.getByRole('button', { name: /generate my logo/i }).click();
    await page.locator('img[src*="/storage/logos/"]').first().waitFor({ timeout: 120000 });

    const before = await page.evaluate(() => window.__rmpCanvas.getObjects().length);
    await page.getByRole('button', { name: /place on my design/i }).first().click();
    await expect(page.getByRole('heading', { name: /ai logo builder/i })).toBeHidden({ timeout: 30000 });

    const after = await page.evaluate(() => {
        const c = window.__rmpCanvas;
        const logos = c.getObjects().filter((o) => o.rmpRole === 'logo');
        return { count: c.getObjects().length, logos: logos.length, active: c.getActiveObject()?.rmpRole };
    });
    expect(after.count).toBe(before + 1);
    expect(after.active).toBe('logo');
});
