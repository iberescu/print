import { defineConfig, devices } from '@playwright/test';

// e2e for the RunMyPrint storefront. Runs every spec on BOTH a desktop and a
// mobile (Pixel 5) profile, so the upload + online-designer workflows are
// verified responsively. Point at a different env with APP_URL=... .
export default defineConfig({
    testDir: './e2e',
    timeout: 90000,
    expect: { timeout: 15000 },
    fullyParallel: false,
    workers: 2,
    retries: 0,
    use: {
        baseURL: process.env.APP_URL || 'http://localhost:8080',
        trace: 'retain-on-failure',
        screenshot: 'only-on-failure',
        actionTimeout: 20000,
    },
    reporter: [['list']],
    projects: [
        {
            name: 'desktop',
            use: { ...devices['Desktop Chrome'], viewport: { width: 1440, height: 900 } },
        },
        {
            name: 'mobile',
            use: { ...devices['Pixel 5'] }, // chromium-based, 393×851, touch
        },
    ],
});
