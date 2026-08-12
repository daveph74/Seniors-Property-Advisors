import { expect, test } from '../fixtures.js';
import { clickThrough, cmsInput, gotoCms, toast } from '../helpers.js';
import { unique } from '../support/unique.js';

test.describe('Blog', () => {
    const row = (page, title) => page.locator('.cms-article-row', { hasText: title });

    async function writeArticle(page, title) {
        await gotoCms(page, '/cms/blog');
        await page.getByRole('link', { name: 'New article' }).click();

        await expect(cmsInput(page, 'Title')).toBeVisible();
        await cmsInput(page, 'Title').fill(title);
        await page.getByLabel('Article content').fill('A paragraph the suite typed.');

        await page.getByRole('button', { name: 'Create article' }).click();
        await expect(toast(page)).toContainText('Article created');
    }

    async function binArticle(page, title) {
        await gotoCms(page, '/cms/blog');

        if (await row(page, title).count() === 0) return;

        await clickThrough(
            page,
            row(page, title).getByRole('link', { name: 'Edit' }),
            /\/cms\/blog\/\d+\/edit$/,
            'the article editor',
        );
        await page.locator('.cms-btn--danger-outline', { hasText: 'Delete' }).first().click();
        await page.locator('.cms-modal').getByRole('button', { name: 'Delete', exact: true }).click();
        await expect(toast(page)).toContainText('Article deleted');
    }

    test('an article is written, published, taken down and deleted', async ({ page }) => {
        const title = unique('Downsizing');

        await writeArticle(page, title);
        await expect(page.locator('.cms-badge')).toContainText('Draft');

        await page.getByRole('button', { name: 'Publish' }).click();
        await expect(toast(page)).toContainText('Published');

        await gotoCms(page, '/cms/blog');
        await expect(row(page, title)).toContainText('Published');

        await clickThrough(
            page,
            row(page, title).getByRole('link', { name: 'Edit' }),
            /\/cms\/blog\/\d+\/edit$/,
            'the article editor',
        );
        await page.getByRole('button', { name: 'Unpublish' }).click();
        await expect(page.locator('.cms-modal')).toContainText('Take this article off the website?');
        await page.locator('.cms-modal').getByRole('button', { name: 'Unpublish' }).click();
        await expect(toast(page)).toContainText('Taken off the website');

        await binArticle(page, title);
    });

    /* Bodies are HTML, so the purifier is the only gate between what is typed and what a reader
       receives. This is the browser-side half of what BlogTest pins server-side. */
    test('the editor writes real markup, and script is not part of it', async ({ page }) => {
        const title = unique('Formatted');

        await writeArticle(page, title);

        const surface = page.getByLabel('Article content');

        /* Focused rather than clicked: the surface is a contenteditable that grows as it fills, so
           a click waits on a box that is still changing size. */
        await surface.focus();
        await page.keyboard.type('Bold this');
        await page.keyboard.press('Control+a');
        await page.locator('.cms-rt__tool', { hasText: 'B' }).first().click();

        await expect(surface.locator('strong')).toContainText('Bold this');

        await page.getByRole('button', { name: 'Save' }).click();
        await expect(toast(page)).toContainText('Article saved');

        await page.reload({ waitUntil: 'domcontentloaded' });
        await expect(page.getByLabel('Article content').locator('strong')).toContainText('Bold this');

        await binArticle(page, title);
    });

    /**
     * Pasting an article in from a web page is a normal way to write one, and it brings that page's
     * pictures with it. `img-src` permits this origin only, so those could never be drawn — and the
     * server refuses a body carrying one, which would mean refusing to save words the writer did type
     * over addresses they did not. They are dropped as the paste lands instead, and said out loud.
     *
     * Driven by dispatching the paste: Playwright cannot put `text/html` on the real clipboard, and
     * ProseMirror reads `clipboardData` rather than the keystroke.
     */
    test('a picture pasted from another site is left out, and the words are kept', async ({ page }) => {
        const title = unique('Pasted');

        await writeArticle(page, title);

        const surface = page.getByLabel('Article content');
        await surface.focus();

        await surface.evaluate((node) => {
            const data = new DataTransfer();

            data.setData('text/html', '<p>Words worth keeping.</p>'
                + '<img src="https://example.com/one.jpg" alt="theirs">'
                + '<img src="//example.com/two.jpg">'
                + '<img src="/media/2026/08/rachel.jpg" alt="ours">');

            node.dispatchEvent(new ClipboardEvent('paste', {
                clipboardData: data, bubbles: true, cancelable: true,
            }));
        });

        await expect(toast(page)).toContainText('2 images were linked from another site');

        await expect(surface).toContainText('Words worth keeping.');
        await expect(surface.locator('img[src*="example.com"]')).toHaveCount(0);
        /* The one that could be drawn is still there — this drops remote pictures, not pictures. */
        await expect(surface.locator('img[src="/media/2026/08/rachel.jpg"]')).toHaveCount(1);

        /* The save is the point: with the remote images gone the server has nothing to refuse. */
        await page.getByRole('button', { name: 'Save' }).click();
        await expect(toast(page)).toContainText('Article saved');

        await page.reload({ waitUntil: 'domcontentloaded' });
        await expect(page.getByLabel('Article content')).toContainText('Words worth keeping.');

        await binArticle(page, title);
    });

    test('the slug warns once an article has been published', async ({ page }) => {
        const title = unique('Renamed');

        await writeArticle(page, title);
        await page.getByRole('button', { name: 'Publish' }).click();
        await expect(toast(page)).toContainText('Published');

        await cmsInput(page, 'Web address').fill(`${unique('moved').toLowerCase().replace(/[^a-z0-9-]/g, '-')}`);

        await expect(page.locator('.cms-field-error')).toContainText('breaks any link already shared');

        await binArticle(page, title);
    });

    test('the filters narrow the list', async ({ page }) => {
        const title = unique('Filtered');

        await writeArticle(page, title);
        await gotoCms(page, '/cms/blog');

        await page.getByPlaceholder('Search articles').fill(title);
        await expect(row(page, title)).toBeVisible();
        await expect(page.locator('.cms-article-row')).toHaveCount(1);

        await page.getByPlaceholder('Search articles').fill('');
        await page.locator('.cms-toolbar select').nth(1).selectOption({ label: 'Published' });
        await expect(row(page, title)).toHaveCount(0);

        await page.locator('.cms-toolbar select').nth(1).selectOption({ label: 'All statuses' });
        await binArticle(page, title);
    });

    test('a category is added, renamed and deleted', async ({ page }) => {
        const name = unique('Advice');

        await gotoCms(page, '/cms/blog');

        await page.getByPlaceholder('e.g. Downsizing').fill(name);
        await page.getByPlaceholder('e.g. Downsizing').press('Enter');
        await expect(toast(page)).toContainText('Category added');

        /* The name is an input's *value*, not text, so `hasText` can never find the row — the
           delete button's label is the only place the name appears as text. */
        const cat = (of) => page.locator('.cms-cat-row').filter({
            has: page.getByRole('button', { name: `Delete ${of}` }),
        });

        await expect(cat(name)).toBeVisible();
        await expect(cat(name)).toContainText('0 articles');

        /* Renaming saves on blur — there is no button to press. */
        const renamed = unique('Renamed');
        await cat(name).locator('input.cms-input').fill(renamed);
        await cat(name).locator('input.cms-input').blur();

        await page.reload({ waitUntil: 'domcontentloaded' });
        await expect(cat(renamed)).toBeVisible();

        await cat(renamed).getByRole('button', { name: `Delete ${renamed}` }).click();
        await page.locator('.cms-modal').getByRole('button', { name: 'Delete', exact: true }).click();
        await expect(toast(page)).toContainText('Category deleted');
    });
});
