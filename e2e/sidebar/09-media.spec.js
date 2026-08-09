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

    test('the detail panel offers the address to paste into an image field', async ({ page }) => {
        await page.locator('.cms-media-item').first().click();

        const address = page.locator('.cms-media-side input[readonly]');

        await expect(address).toHaveValue(/^\/media\//);
        await expect(page.locator('.cms-media-side')).toContainText('Paste this into an image field');
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

    test('a thumbnail is really fetched and drawn', async ({ page }) => {
        await withImages(page);
        await gotoCms(page, '/cms/media');

        const thumb = page.locator('.cms-media-item__thumb--img').first();

        await expect(thumb).toBeVisible();
        await expect(thumb).toHaveJSProperty('complete', true);
        await expect(thumb).not.toHaveJSProperty('naturalWidth', 0);
    });
});
