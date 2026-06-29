import { test, expect } from '@playwright/test';

// Product feeds for retargeting / shopping (req 20).
test('marketing feeds are valid xml', async ({ request }) => {
    const g = await request.get('/feed/google.xml');
    expect(g.ok()).toBeTruthy();
    const gx = await g.text();
    expect(gx).toContain('<rss');
    expect(gx).toContain('g:price');

    const r = await request.get('/feed/rtbhouse.xml');
    expect(r.ok()).toBeTruthy();
    expect(await r.text()).toContain('<products>');
});
