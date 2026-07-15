import { test, expect } from '@playwright/test';
import { completeUpsell, reviewAndAdd } from './helpers.mjs';

// PROD-SAFE checkout check for hosts with LIVE Stripe keys. Mirrors
// checkout.spec.mjs up to the Pay click, then asserts the redirect to a real
// Stripe Checkout Session and STOPS — no card is entered, nothing is charged.
// Leaves behind: one throwaway e2e…@runmyprint.com account and one `pending`
// order (its Stripe session expires by itself after 24 h).
//
//   APP_URL=https://www.runmyprint.com LIVE_STRIPE=1 npx playwright test e2e/checkout-live.spec.mjs --project=desktop --workers=1
test.skip(!process.env.LIVE_STRIPE, 'explicit live-Stripe smoke — set LIVE_STRIPE=1 to run');

test('live checkout: funnel → checkout → Pay redirects to a Stripe session (no charge)', async ({ page }) => {
    test.slow();
    const email = `e2e${Date.now()}${Math.floor(Math.random() * 1e6)}@runmyprint.com`;
    await page.goto('/register');
    await page.fill('input[autocomplete=name]', 'E2E Tester');
    await page.fill('input[type=email]', email);
    await page.fill('input[autocomplete=new-password]', 'password123');
    await page.locator('input[type=password]').nth(1).fill('password123');
    await page.getByRole('button', { name: /create account/i }).click();
    await page.waitForURL('**/account');

    await page.goto('/product/standard-business-cards');
    await page.getByRole('button', { name: /design online/i }).first().click();
    await page.waitForURL('**/design/**');
    await expect(page.locator('canvas')).toHaveCount(2);

    await reviewAndAdd(page);
    await page.waitForURL('**/upsell');
    await completeUpsell(page);
    await page.waitForURL('**/cart');

    await page.getByRole('link', { name: /proceed to checkout/i }).click();
    await page.waitForURL('**/checkout');

    // label-wrapped fields; email/name prefill from the account, country from the form default
    await page.getByLabel(/^email/i).fill(email);
    await page.getByLabel(/full name/i).fill('E2E Tester');
    await page.getByLabel(/^address/i).fill('1 Test Street');
    await page.getByLabel(/^city/i).fill('Austin');
    await page.getByLabel(/^state/i).selectOption({ index: 1 }); // first real state (index 0 = "Select…")
    await page.getByLabel(/postal code/i).fill('78701');

    // the order total shown on our checkout page must reappear on Stripe's
    const totals = (await page.locator('body').innerText()).match(/\$\d+(?:,\d{3})*\.\d{2}/g) || [];
    const total = totals[totals.length - 1]; // summary total is the last money figure
    expect(total, 'checkout page shows a money total').toBeTruthy();

    await page.getByRole('button', { name: /pay/i }).click();
    await page.waitForURL(/checkout\.stripe\.com/, { timeout: 30000 });
    await page.waitForLoadState('domcontentloaded');
    await page.waitForTimeout(3000); // Stripe renders the summary client-side

    const stripeText = await page.locator('body').innerText();
    console.log(`checkout total ${total} → Stripe session URL ok; Stripe shows: ${(stripeText.match(/\$\d+(?:,\d{3})*\.\d{2}/g) || []).slice(0, 3).join(' ')}`);
    expect(stripeText).toContain(total);
    await page.screenshot({ path: 'artifacts/live-checkout-stripe.png', fullPage: false });
    // STOP HERE — never fill card details on a live session.
});
