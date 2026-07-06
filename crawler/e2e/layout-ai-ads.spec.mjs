import { test, expect } from '@playwright/test';
import { clickContinue, reviewAndAdd } from './helpers.mjs';

// Layout.ai ad-credit step: the Review-time capture asks the engine for 8
// direct LLM ad canvases (pipeline_facebook_ad, template_count 8 — see
// SendPqsgCapture). upsell.spec.mjs only asserts the step EXISTS; this spec
// verifies the engine really generates all 8 and the widget shows them, so it
// must run against a public host the engine can fetch design URLs from:
//
//   APP_URL=https://runmyprint.com PQSG_ADS=1 npx playwright test e2e/layout-ai-ads.spec.mjs --project=desktop --workers=1
//
// Localhost captures are rejected (engine can't reach localhost URLs) and the
// canvases take minutes to paint, hence the gate and the long timeout.
test.skip(!process.env.PQSG_ADS, 'set PQSG_ADS=1 to run the (slow, engine-billed) Layout.ai ads check');

test('Layout.ai step: engine generates 8 ad canvases and the widget shows them', async ({ page }) => {
    test.setTimeout(600000);

    // A test design; a real website on the card enriches the capture (the
    // placeholder-design fallback would still capture via the review preview).
    await page.goto('/design/standard-business-cards?test=1');
    await page.waitForFunction(() => window.__rmpCanvas && window.__rmpCanvas.getObjects().length > 0);
    await page.evaluate(() => {
        const c = window.__rmpCanvas;
        c.getObjects().find((o) => o.rmpRole === 'url')?.set('text', 'example.com');
        c.requestRenderAll();
    });
    await reviewAndAdd(page); // registers the pqsg capture
    await page.waitForURL('**/upsell');

    // finalize → accessories → logo gallery → Layout.ai ads
    await clickContinue(page);
    await clickContinue(page);
    await clickContinue(page);
    await expect(page.getByText(/runmyprint × layout\.ai/i)).toBeVisible();

    // record the widget's own artifact list as its polling updates stream in
    await page.evaluate(() => {
        const el = document.getElementById('pqsg-widget');
        window.__adImages = [];
        const keep = (e) => { if (e?.detail?.images) window.__adImages = e.detail.images; };
        ['pqsg:update', 'pqsg:complete', 'pqsg:timeout'].forEach((n) => el.addEventListener(n, keep));
    });

    // SHOWN: all 8 canvases render inside the (shadow-DOM) widget. The step's
    // display-products allow-list hides everything but pipeline_facebook_ad,
    // so every rendered image is an ad canvas.
    await expect(page.locator('#pqsg-widget img')).toHaveCount(8, { timeout: 480000 });

    // ...and every one actually paints — an appended <img> is not yet a shown
    // ad. naturalWidth 0 after load would mean a broken/blank canvas URL.
    await expect
        .poll(() => page.locator('#pqsg-widget img').evaluateAll((imgs) => imgs.filter((i) => i.complete && i.naturalWidth > 0).length), { timeout: 120000 })
        .toBe(8);

    // GENERATED: the engine's artifact list carries exactly 8 facebook ads.
    // (Advisory skip if the run completed before our listener attached.)
    const ads = await page.evaluate(() => (window.__adImages || [])
        .filter((i) => i.product_key === 'pipeline_facebook_ad' || i.special_product_key === 'pipeline_facebook_ad'));
    console.log(`engine artifact list: ${ads.length} facebook-ad entries`);
    if (ads.length > 0) expect(ads.length).toBe(8);

    // 8 distinct canvas URLs — logged so a failure (or a human) can eyeball them
    const srcs = await page.locator('#pqsg-widget img').evaluateAll((imgs) => imgs.map((i) => i.currentSrc || i.src));
    srcs.forEach((s) => console.log(`ad: ${s}`));
    expect(new Set(srcs).size).toBe(8);

    // viewport shots (element shots scroll-stitch and bake the sticky header
    // in). The widget reveals tiles on scroll — give the animation a beat, or
    // the evidence shows grey tiles that a real scrolling user never sees.
    await page.locator('#pqsg-widget').scrollIntoViewIfNeeded();
    await page.waitForTimeout(1500);
    await page.screenshot({ path: 'test-results/layout-ai-ads-top.png' });
    await page.mouse.wheel(0, 700);
    await page.waitForTimeout(1500);
    await page.screenshot({ path: 'test-results/layout-ai-ads-bottom.png' });
});
