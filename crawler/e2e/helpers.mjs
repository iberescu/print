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
