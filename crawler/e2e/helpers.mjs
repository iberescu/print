// The buyer is routed through forced upsell steps before the cart. Click the
// main "Continue" CTA and wait for the server round-trip so the next step (or
// the cart) has actually rendered before the test continues.
export async function clickContinue(page) {
    // Centre the CTA first: late-loading product images shift the layout and can
    // push the button under the sticky header, which then swallows the click.
    const btn = page.getByRole('button', { name: /continue/i }).last(); // bottom main CTA
    await btn.evaluate((el) => el.scrollIntoView({ block: 'center' }));
    await page.waitForTimeout(250);
    await Promise.all([
        page.waitForResponse((r) => /\/upsell\/next\b/.test(r.url()) || /\/cart\b/.test(r.url())),
        btn.click(),
    ]);
    // Let Inertia commit the redirect's follow-up GET. networkidle is unusable
    // here: the gallery widget polls continuously, and the idle gap between the
    // POST response and the GET used to let callers advance on a stale page.
    await page.waitForTimeout(800);
}

// Editor → Review step → approve → add to cart.
export async function reviewAndAdd(page) {
    await page.getByRole('button', { name: /^review/i }).click();
    await page.waitForURL('**/review');
    await page.getByRole('checkbox').first().check();
    await page.getByRole('button', { name: /add to cart/i }).click();
}

export async function completeUpsell(page) {
    for (let i = 0; i < 6; i++) {
        if (new URL(page.url()).pathname === '/cart') return;
        if (!(await page.getByRole('button', { name: /continue/i }).count())) break;
        await clickContinue(page);
    }
}
