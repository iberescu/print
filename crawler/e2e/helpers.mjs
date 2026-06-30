// The buyer is routed through forced upsell steps before the cart. Click the
// main "Continue" CTA and wait for the server round-trip so the next step (or
// the cart) has actually rendered before the test continues.
export async function clickContinue(page) {
    await Promise.all([
        page.waitForResponse((r) => /\/upsell\/next\b/.test(r.url()) || /\/cart\b/.test(r.url())),
        page.getByRole('button', { name: /continue/i }).last().click(), // bottom main CTA
    ]);
    await page.waitForLoadState('networkidle');
}

export async function completeUpsell(page) {
    for (let i = 0; i < 6; i++) {
        if (new URL(page.url()).pathname === '/cart') return;
        if (!(await page.getByRole('button', { name: /continue/i }).count())) break;
        await clickContinue(page);
    }
}
