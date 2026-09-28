import { readFileSync } from 'node:fs';
import { expect, test } from '../fixtures.js';
import { gotoCms } from '../helpers.js';
import { unique } from '../support/unique.js';

test.describe('Pages', () => {
    test.beforeEach(async ({ page }) => {
        await gotoCms(page, '/cms/pages');
    });

    const row = (page, title) => page.locator('.cms-table__row, .cms-page-row', { hasText: title }).first();

    test('lists the real pages with their addresses', async ({ page }) => {
        await expect(page.getByRole('button', { name: 'Contact', exact: true })).toBeVisible();
        await expect(page.getByText('/contact')).toBeVisible();
    });

    test('search narrows the list', async ({ page }) => {
        await page.getByPlaceholder('Search pages').fill('compare');

        await expect(page.getByRole('button', { name: 'Compare agents', exact: true })).toBeVisible();
        await expect(page.getByRole('button', { name: 'Contact', exact: true })).toHaveCount(0);

        await page.getByPlaceholder('Search pages').fill('');
    });

    test('the status filter offers what the pages actually are', async ({ page }) => {
        const filter = page.locator('.cms-toolbar select').first();

        await expect(filter).toContainText('All statuses');
        await expect(filter).toContainText('Published');
        await expect(filter).toContainText('Unpublished changes');

        await filter.selectOption({ label: 'Published' });
        await expect(page.locator('.cms-badge', { hasText: 'Draft' })).toHaveCount(0);

        await filter.selectOption({ label: 'All statuses' });
    });

    /* `exact` matters: a loose "List" also matches the page called "Selling checklist". */
    test('the two views both render', async ({ page }) => {
        await page.getByRole('button', { name: 'Site tree', exact: true }).click();
        await expect(page.locator('.cms-tree')).toBeVisible();
        await expect(page.locator('.cms-tree-row').first()).toBeVisible();

        await page.getByRole('button', { name: 'List', exact: true }).click();
        await expect(page.locator('.cms-tree')).toHaveCount(0);
        await expect(page.getByRole('button', { name: 'Contact', exact: true })).toBeVisible();
    });

    /**
     * A page is never deleted, only archived — so the fixture it creates is archived rather than
     * removed, and the archived filter is how we prove it went there.
     *
     * The row menu is portalled out of the row, so its items are found on the page rather than
     * inside it, and they are plain buttons rather than menu items.
     */
    test('a page is created and archived', async ({ page }) => {
        const title = unique('Guide');

        await page.getByRole('button', { name: 'New page' }).click();

        const modal = page.locator('.cms-modal');
        await expect(modal).toBeVisible();
        await modal.locator('input').first().fill(title);
        await modal.getByRole('button', { name: /Create|Save|Add/ }).click();

        await expect(page).toHaveURL(/\/cms\/pages\/\d+\/edit$/);
        await expect(page.getByRole('button', { name: 'Save draft' })).toBeVisible();

        await gotoCms(page, '/cms/pages');
        await expect(page.getByRole('button', { name: title, exact: true })).toBeVisible();

        await row(page, title).locator('.cms-table__cell-menu button').first().click();
        await page.locator('.cms-menu__item', { hasText: 'Archive' }).first().click();

        const confirm = page.locator('.cms-modal');
        if (await confirm.count() > 0) {
            await confirm.getByRole('button', { name: /Archive/ }).click();
        }

        await expect(page.getByRole('button', { name: title, exact: true })).toHaveCount(0);

        /* By value, not label: the option carries a live count — "Archived (1)". */
        await page.locator('.cms-toolbar select').first().selectOption('archived');
        await expect(page.getByRole('button', { name: title, exact: true })).toBeVisible();
    });

    /**
     * The round trip a page takes between two sites, on one site: download it, upload the same file
     * (refused, because the address is taken), then upload it under a new address (a draft).
     */
    test('a page downloads as a file and imports back as a draft', async ({ page }) => {
        await row(page, '/contact').locator('.cms-table__cell-menu button').first().click();

        const download = page.waitForEvent('download');
        await page.locator('.cms-menu__item', { hasText: 'Download as file' }).first().click();
        const file = await download;

        expect(file.suggestedFilename()).toBe('contact.page.json');
        const document = JSON.parse(readFileSync(await file.path(), 'utf8'));
        expect(document.format).toBe('spa-page');
        expect(document).not.toHaveProperty('cmsId');

        const importFile = async (contents) => {
            await page.getByRole('button', { name: 'Import page' }).click();
            const modal = page.locator('.cms-modal');
            await modal.locator('input[type=file]').setInputFiles({
                name: 'page.page.json', mimeType: 'application/json', buffer: Buffer.from(JSON.stringify(contents)),
            });
            await modal.getByRole('button', { name: 'Import as a draft' }).click();
            return modal;
        };

        const refused = await importFile(document);
        await expect(refused.locator('.cms-field-error')).toContainText('A page at /contact already exists');
        await refused.getByRole('button', { name: 'Cancel' }).click();

        const slug = unique('imported').toLowerCase().replace(/[^a-z0-9]+/g, '-');
        const title = unique('Imported contact');
        await importFile({ ...document, slug, title });

        await expect(page.locator('.cms-modal')).toHaveCount(0);
        await expect(row(page, title)).toBeVisible();
        await expect(row(page, title).locator('.cms-badge')).toHaveText('Draft');
    });

    test('an unknown page id is refused rather than invented', async ({ page }) => {
        const response = await page.goto('/cms/pages/99999/edit', { waitUntil: 'commit' });

        expect(response.status()).toBe(404);
    });
});
