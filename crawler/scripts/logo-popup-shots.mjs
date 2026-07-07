// One-off visual capture of the replace-logo popup (local verification).
import { chromium } from '@playwright/test';
import { fileURLToPath } from 'url';
import fs from 'fs';

const base = process.env.APP_URL || 'http://localhost:8080';
const outDir = fileURLToPath(new URL('../artifacts/logo-popup', import.meta.url));
const logo = fileURLToPath(new URL('../e2e/fixtures/logos/logo-01.png', import.meta.url));
fs.mkdirSync(outDir, { recursive: true });

const browser = await chromium.launch();
const page = await browser.newPage({ viewport: { width: 1440, height: 900 } });

await page.goto(`${base}/design/standard-business-cards?test=1`);
await page.waitForFunction(() => window.__rmpCanvas?.getObjects().some((o) => o.rmpRole === 'logo'));
await page.waitForTimeout(1200); // fonts settle
await page.screenshot({ path: `${outDir}/1-editor-placeholder.png` });

// click the placeholder centre
const pos = await page.evaluate(() => {
    const c = window.__rmpCanvas;
    const o = c.getObjects().find((x) => x.rmpRole === 'logo');
    const p = o.getCenterPoint();
    const z = c.getZoom();
    return { x: p.x * z, y: p.y * z };
});
const box = await page.locator('canvas.upper-canvas').boundingBox();
await page.mouse.click(box.x + pos.x, box.y + pos.y);
await page.getByRole('heading', { name: /add your logo/i }).waitFor();
await page.screenshot({ path: `${outDir}/2-popup-open.png` });

// replace with a real logo
const [chooser] = await Promise.all([
    page.waitForEvent('filechooser'),
    page.getByRole('button', { name: /upload your logo/i }).click(),
]);
await chooser.setFiles(logo);
await page.waitForFunction(() => {
    const os = window.__rmpCanvas.getObjects().filter((o) => o.rmpRole === 'logo');
    return os.length === 1 && !(os[0].getSrc?.() || '').includes('logo-placeholder');
});
await page.waitForTimeout(400);
await page.screenshot({ path: `${outDir}/3-logo-replaced.png` });

// click the new logo → popup should offer replace/remove
const pos2 = await page.evaluate(() => {
    const c = window.__rmpCanvas;
    const o = c.getObjects().find((x) => x.rmpRole === 'logo');
    const p = o.getCenterPoint();
    const z = c.getZoom();
    return { x: p.x * z, y: p.y * z };
});
await page.mouse.click(box.x + pos2.x, box.y + pos2.y);
await page.getByRole('heading', { name: /replace your logo/i }).waitFor();
await page.screenshot({ path: `${outDir}/4-popup-replace.png` });

await browser.close();
console.log('done →', outDir);
