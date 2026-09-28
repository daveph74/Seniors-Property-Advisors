import { expect, test } from '../fixtures.js';
import { gotoCms } from '../helpers.js';
import { uniqueValue } from '../support/unique.js';

test.describe('SEO', () => {
    /* The screen has two segmented strips — the Overview/Defaults tabs and the filter row — and the
       sidebar carries a "Pages" link of its own. An unscoped locator matches more than one of them,
       which Playwright reports as a strict-mode violation rather than as the wrong thing being
       clicked, so each is scoped to the strip it belongs to. */
    const filters = (page) => page.locator('.cms-segmented[aria-label="Which addresses to show"]');

    /* `--seo` is the grid modifier and the head row wears it too, so a data row is the one that
       also has `.cms-table__row`. `.first()` on the modifier alone resolves to the header, which
       fails as "the row does not contain the text" and reads as a broken save. */
    const rows = (page) => page.locator('.cms-table__row.cms-table__row--seo');

    test.beforeEach(async ({ page }) => {
        await gotoCms(page, '/cms/seo');
    });

    test('every public address is listed with whether it is in the sitemap', async ({ page }) => {
        await expect(rows(page).first()).toBeVisible();
        expect(await rows(page).count()).toBeGreaterThan(1);

        /* The column an SEO specialist comes for: every row says yes or says why not. */
        await expect(page.locator('.cms-table__row--seo .cms-badge').first()).toBeVisible();
    });

    /**
     * The XML is what Google reads, so reaching it must not mean knowing the address by heart.
     * The link is followed rather than pattern-matched: a href of the right shape proves nothing
     * about whether the route answers.
     */
    test('the sitemap a crawler reads is one click away and really answers', async ({ page }) => {
        const view = page.getByRole('link', { name: 'View sitemap.xml' });
        await expect(view).toBeVisible();

        const href = await view.getAttribute('href');
        const response = await page.request.get(href);

        expect(response.status()).toBe(200);
        expect(response.headers()['content-type']).toContain('xml');
        expect(await response.text()).toContain('<urlset');

        await expect(page.getByRole('link', { name: 'Download' })).toHaveAttribute('download', 'sitemap.xml');
    });

    test('a filter changes the address and what is listed', async ({ page }) => {
        const all = await rows(page).count();

        await filters(page).getByRole('link', { name: /^Pages/ }).click();

        await expect(page).toHaveURL(/show=pages/);
        await expect(rows(page).first()).toBeVisible();

        for (const sub of await page.locator('.cms-table__cell-sub').allInnerTexts()) {
            expect(sub).not.toContain('Article');
        }

        expect(await rows(page).count()).toBeLessThanOrEqual(all);
    });

    /**
     * An un-allowlisted value used to fall through to "everything" while the control showed nothing
     * chosen, which reads as a broken screen rather than a bad address.
     */
    test('a nonsense filter still lights a tab', async ({ page }) => {
        await gotoCms(page, '/cms/seo?show=nonsense');

        await expect(filters(page).locator('.cms-segmented__btn--active')).toHaveText(/All addresses/);
    });

    test('a description can be fixed from the list and comes back after a reload', async ({ page }) => {
        const written = uniqueValue('Written from the SEO screen');

        await page.locator('.cms-table__cell-title button').first().click();

        const editor = page.locator('.cms-seo-editor');
        await expect(editor).toBeVisible();

        await editor.locator('textarea').fill(written);
        await editor.getByRole('button', { name: 'Save' }).click();

        await expect(editor).toBeHidden();

        await gotoCms(page, '/cms/seo?q='.concat(encodeURIComponent(written.slice(0, 20))));

        await expect(rows(page).first()).toContainText(written.slice(0, 20));
    });

    /**
     * How the bug was found: earlier specs delete articles, so on a full run the newest rows here
     * are deleted ones — the address was clickable, the editor opened, and its save answered 404
     * while the panel sat there looking busy.
     *
     * It makes its own deleted article rather than hoping one exists. Skipping when the database
     * happens to be fresh would have reported "no deleted content" on the run that most needed the
     * answer, and a standing skip is a standing question.
     */
    test('a deleted address is listed but offers nothing to edit', async ({ page }) => {
        const title = uniqueValue('Bound for the bin');

        await gotoCms(page, '/cms/blog');
        await page.getByRole('link', { name: 'New article' }).click();
        await page.locator('.cms-field', { hasText: 'Title' }).first().locator('input').fill(title);
        await page.getByLabel('Article content').fill('Written only to be deleted.');
        await page.getByRole('button', { name: 'Create article' }).click();

        await page.locator('.cms-btn--danger-outline', { hasText: 'Delete' }).first().click();
        await page.locator('.cms-modal').getByRole('button', { name: 'Delete', exact: true }).click();

        await gotoCms(page, '/cms/seo');

        const deleted = rows(page).filter({ hasText: 'deleted, restore it first' });

        await expect(deleted.first()).toBeVisible();
        await expect(deleted.first().locator('button')).toHaveCount(0);
        await expect(deleted.first().locator('.cms-seo-editor')).toHaveCount(0);
    });

    /**
     * The canonical stays in the builder deliberately, so the row's editor must offer exactly two
     * controls — a description and one switch. A third would mean something moved here that was
     * decided against.
     */
    test('the row editor offers the description and the switch, and nothing else', async ({ page }) => {
        await page.locator('.cms-table__cell-title button').first().click();

        const editor = page.locator('.cms-seo-editor');

        await expect(editor.locator('textarea')).toHaveCount(1);
        await expect(editor.locator('input')).toHaveCount(0);
        await expect(editor.getByRole('switch')).toHaveCount(1);
    });

    /* Moved from the Settings spec with the field itself. */
    test('the description counter tracks what is typed', async ({ page }) => {
        await page.getByRole('button', { name: 'Defaults' }).click();

        const text = uniqueValue('A description');
        await page.getByLabel('Default description').fill(text);

        await expect(page.locator('.cms-hint').filter({ hasText: 'of 320' }))
            .toContainText(String(text.length));
    });

    test('the defaults tab saves the site-wide description', async ({ page }) => {
        await page.getByRole('button', { name: 'Defaults' }).click();

        const description = page.locator('textarea').first();
        await expect(description).toBeVisible();

        const written = uniqueValue('Independent property advice');
        await description.fill(written);

        await page.getByRole('button', { name: 'Save defaults' }).click();

        await gotoCms(page, '/cms/seo');
        await page.getByRole('button', { name: 'Defaults' }).click();

        await expect(page.locator('textarea').first()).toHaveValue(written);
    });
});
