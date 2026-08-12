import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { expect, test, withImages } from '../fixtures.js';
import { gotoCms, toast } from '../helpers.js';
import { uniqueValue } from '../support/unique.js';

test.describe('Media', () => {
    test.beforeEach(async ({ page }) => {
        await gotoCms(page, '/cms/media');
    });

    const tile = (page, name) => page.locator('.cms-media-cell', { hasText: name });

    test('the library lists what has been uploaded', async ({ page }) => {
        await expect(page.locator('.cms-media-item').first()).toBeVisible();
        await expect(page.locator('.cms-media-item__name').first()).not.toBeEmpty();
    });

    /* Server-side, so it searches the whole library rather than the page on screen. */
    test('search reaches the whole library', async ({ page }) => {
        await page.getByPlaceholder('Search media').fill('rachel');

        await expect(page).toHaveURL(/q=rachel/);
        await expect(page.locator('.cms-media-item')).toHaveCount(1);

        await page.getByPlaceholder('Search media').fill('zzzznothing');
        await expect(page.locator('.cms-media-empty')).toContainText('No media matches that search');
    });

    test('a description and caption save on blur', async ({ page }) => {
        await page.locator('.cms-media-item').first().click();

        const panel = page.locator('.cms-media-side');
        await expect(panel).toBeVisible();

        const alt = uniqueValue('An advisor');
        await panel.getByPlaceholder('Advisor guiding a senior couple').fill(alt);
        await panel.getByPlaceholder('Advisor guiding a senior couple').blur();
        await expect(toast(page)).toContainText('Description saved');

        const caption = uniqueValue('Taken in');
        await panel.getByPlaceholder('Optional — shown under the image').fill(caption);
        await panel.getByPlaceholder('Optional — shown under the image').blur();
        await expect(toast(page)).toContainText('Caption saved');

        await page.reload({ waitUntil: 'domcontentloaded' });
        await page.locator('.cms-media-item').first().click();
        await expect(panel.getByPlaceholder('Advisor guiding a senior couple')).toHaveValue(alt);
    });

    /**
     * The address is shown for reference, and it used to say to paste it into an image field. That
     * instruction outlived the box: a block's image is chosen from the library now, and there is
     * nowhere to type an address at all — which is what stops one pointing at another site.
     */
    test('the detail panel shows where the image lives', async ({ page }) => {
        await page.locator('.cms-media-item').first().click();

        const address = page.locator('.cms-media-side input[readonly]');

        await expect(address).toHaveValue(/^\/media\//);
        await expect(page.locator('.cms-media-side')).toContainText('Where this image lives');
    });

    test('the panel closes again', async ({ page }) => {
        await page.locator('.cms-media-item').first().click();
        await expect(page.locator('.cms-media-side')).toBeVisible();

        await page.getByRole('button', { name: 'Close details' }).click();
        await expect(page.locator('.cms-media-side')).toHaveCount(0);
    });

    /**
     * Deleting checks what an image is used by first. Everything seeded is placed on a page, so
     * this exercises the branch where nothing can go — the one that protects a live page from
     * losing its picture.
     */
    test('an image in use cannot be deleted', async ({ page }) => {
        await page.locator('.cms-media-item__check').first().check();

        await expect(page.locator('.cms-media-bulk')).toContainText('1 selected');
        await page.locator('.cms-media-bulk').getByRole('button', { name: 'Delete' }).click();

        const modal = page.locator('.cms-modal');
        await expect(modal).toBeVisible();

        const title = await modal.locator('.cms-modal__title').innerText();

        if (title.includes('Nothing can be deleted')) {
            await expect(modal).toContainText('still being used');
            await modal.getByRole('button', { name: 'Close' }).click();
        } else {
            /* Unused after all — say so and leave it alone rather than deleting seed data. */
            await expect(modal).toContainText('cannot be undone');
            await modal.getByRole('button', { name: 'Cancel' }).click();
        }
    });

    test('bulk selection can be cleared', async ({ page }) => {
        await page.locator('.cms-media-item__check').first().check();
        await expect(page.locator('.cms-media-bulk')).toBeVisible();

        await page.locator('.cms-media-bulk').getByRole('button', { name: 'Clear' }).click();
        await expect(page.locator('.cms-media-bulk')).toHaveCount(0);
    });

    test('the upload modal opens and explains itself', async ({ page }) => {
        await page.getByRole('button', { name: 'Upload' }).click();

        const modal = page.locator('.cms-modal');
        await expect(modal).toContainText('Upload images');
        await expect(modal).toContainText('they keep uploading even if you close this');
        await expect(modal.locator('input[type="file"]')).toHaveCount(1);

        await modal.getByRole('button', { name: /Close|Done/ }).click();
        await expect(modal).toHaveCount(0);
    });

    /**
     * The one test that performs an upload, and the reason it exists.
     *
     * An upload does not travel through PHP: the browser is handed a signed URL and PUTs the bytes at
     * storage itself, cross-origin. `connect-src 'self'` therefore blocked every upload in every
     * environment from the day the content policy landed, and nothing noticed — the screen sweep in
     * `cross/security.spec.js` visits the library but never uploads, so it stayed green. The front end
     * reported "Could not reach storage. Is it running?", which reads as an outage rather than a
     * policy, and that is where the time went.
     *
     * So every step of the chain is asserted separately. A test that only checked the final button
     * could pass on a same-origin fallback that never touches the policy at all.
     */
    test('an upload completes every step of the round trip', async ({ page }) => {
        const violations = [];
        const seen = [];

        page.on('console', (m) => {
            if (/Content Security Policy|Refused to/i.test(m.text())) violations.push(m.text());
        });

        page.on('response', (r) => seen.push({
            url: r.url(),
            method: r.request().method(),
            status: r.status(),
        }));

        /* A blocked request never produces a response, so without this the PUT assertion below would
           report "no cross-origin PUT" and not say why. */
        page.on('requestfailed', (r) => seen.push({
            url: r.url(),
            method: r.method(),
            status: 0,
            failure: r.failure()?.errorText,
        }));

        /* Real JPEG bytes under a name no other run will hold: `dimensions()` in the browser and the
           optimiser on the server both read the picture, so an invented file would fail for reasons
           having nothing to do with the policy. The name is normalised the way `safeName()` does,
           so what is searched for below is exactly what was stored. */
        const bytes = readFileSync(resolve(import.meta.dirname, '../../resources/media/rachel.jpg'));
        const filename = `${uniqueValue('e2e-upload').replace(/[^A-Za-z0-9._-]/g, '-')}.jpg`;

        await page.getByRole('button', { name: 'Upload' }).click();

        const modal = page.locator('.cms-modal');
        await expect(modal).toContainText('Upload images');

        await modal.locator('input[type="file"]').setInputFiles({
            name: filename,
            mimeType: 'image/jpeg',
            buffer: bytes,
        });

        /* Waited on first: it only appears once the record call has returned, so the three network
           assertions below are reading a finished chain rather than racing it. */
        await expect(modal.getByRole('button', { name: 'Done — 1 added' })).toBeVisible();

        const origin = new URL(page.url()).origin;
        const put = seen.find((r) => r.method === 'PUT' && ! r.url.startsWith(origin));

        expect(seen.find((r) => r.url.endsWith('/cms/media/sign'))?.status, 'signing').toBe(200);
        expect(put, `no cross-origin PUT was made — ${JSON.stringify(seen.slice(-4))}`).toBeTruthy();
        expect(put.status, `PUT to ${put?.url} — ${put?.failure ?? ''}`).toBeGreaterThanOrEqual(200);
        expect(put.status).toBeLessThan(300);
        expect(seen.find((r) => r.method === 'POST' && r.url.endsWith('/cms/media'))?.status).toBe(201);

        /* A finished row is swept from the queue 1200ms later, so `— added` cannot be asserted without
           a race. A failed one is kept, which is what makes its absence worth asserting. */
        await expect(modal.locator('.cms-library__queue-name', { hasText: 'Could not reach storage' }))
            .toHaveCount(0);

        await modal.getByRole('button', { name: 'Done — 1 added' }).click();

        /* Server-side search, so this finds it wherever paging put it. */
        await page.reload({ waitUntil: 'domcontentloaded' });
        await page.getByPlaceholder('Search media').fill(filename);

        await expect(page.locator('.cms-media-item')).toHaveCount(1);
        await expect(page.locator('.cms-media-item__name')).toContainText(filename);

        expect(violations).toEqual([]);
    });

    test('a thumbnail is really fetched and drawn', async ({ page }) => {
        await withImages(page);
        await gotoCms(page, '/cms/media');

        const thumb = page.locator('.cms-media-item__thumb--img').first();

        await expect(thumb).toBeVisible();
        await expect(thumb).toHaveJSProperty('complete', true);
        await expect(thumb).not.toHaveJSProperty('naturalWidth', 0);
    });
});
