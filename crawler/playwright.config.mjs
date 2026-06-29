import { defineConfig } from '@playwright/test';

export default defineConfig({
    testDir: './e2e',
    timeout: 60000,
    expect: { timeout: 15000 },
    use: {
        baseURL: process.env.APP_URL || 'http://localhost:8080',
        viewport: { width: 1440, height: 900 },
    },
    reporter: 'list',
});
