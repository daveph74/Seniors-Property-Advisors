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

    test('an unknown page id is refused rather than invented', async ({ page }) => {
        const response = await page.goto('/cms/pages/99999/edit', { waitUntil: 'commit' });

        expect(response.status()).toBe(404);
    });
});
