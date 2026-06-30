// After add-to-cart the buyer is routed through forced upsell steps before the
// cart. Click "Continue" on each step until we land on the cart.
export async function completeUpsell(page) {
    for (let i = 0; i < 6; i++) {
        if (new URL(page.url()).pathname === '/cart') return;
        const cont = page.getByRole('button', { name: /continue/i });
        if (!(await cont.count())) break;
        await cont.first().click();
        await page.waitForLoadState('networkidle');
    }
}
