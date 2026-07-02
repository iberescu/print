import { test, expect } from '@playwright/test';

// Exercise modifying a design in the online editor (req: designer must work).
test('designer: add/edit text, change font+size+bold, delete, apply template, flip', async ({ page }) => {
    await page.goto('/product/matte-business-cards');
    await page.getByRole('button', { name: /design online/i }).first().click();
    await page.waitForURL('**/design/**');
    await expect(page.locator('canvas')).toHaveCount(2);

    // add text -> auto-selected -> the text toolbar appears
    await page.getByRole('button', { name: 'Text' }).click();
    const edit = page.getByPlaceholder('Edit text');
    await expect(edit).toBeVisible();
    await edit.fill('Acme Co');
    await expect(edit).toHaveValue('Acme Co');

    // font + size selects are present and changeable
    const selects = page.locator('div.bg-ink select');
    await expect(selects).toHaveCount(2);
    await selects.nth(0).selectOption('Oswald');
    await selects.nth(1).selectOption('48');

    // bold toggle
    await page.getByRole('button', { name: 'B', exact: true }).click();

    // delete the selected element -> text toolbar disappears
    await page.getByRole('button', { name: /Delete/ }).click();
    await expect(edit).toBeHidden();

    // apply a template
    await page.getByRole('button', { name: /Templates/i }).click();
    await expect(page.getByRole('heading', { name: /choose a template/i })).toBeVisible();
    await page.locator('.fixed button:has(img)').first().click();
    await expect(page.getByRole('heading', { name: /choose a template/i })).toBeHidden();

    // flip back + front, canvas stays intact
    await page.getByRole('button', { name: 'Back', exact: true }).click();
    await page.waitForTimeout(300);
    await page.getByRole('button', { name: 'Front', exact: true }).click();
    await expect(page.locator('canvas')).toHaveCount(2);
});
