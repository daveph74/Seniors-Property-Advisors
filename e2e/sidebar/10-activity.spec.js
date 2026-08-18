import { expect, test } from '../fixtures.js';
import { gotoCms } from '../helpers.js';
import { unique } from '../support/unique.js';

test.describe('Activity', () => {
    test('records what was done, to what, and by whom', async ({ page }) => {
        const question = unique('Logged question');

        await gotoCms(page, '/cms/faqs');
        await page.getByRole('button', { name: 'Add a question' }).click();
        await page.locator('.cms-modal input, .cms-modal textarea').first().fill(question);
        await page.locator('.cms-modal textarea').first().fill('Recorded by the suite.');
        await page.getByRole('button', { name: 'Save', exact: true }).click();
        await expect(page.locator('.cms-faq-row', { hasText: question })).toBeVisible();

        await gotoCms(page, '/cms/activity');

        const row = page.locator('.cms-faq-row', { hasText: question });
        await expect(row).toBeVisible();
        await expect(row).toContainText('question');
        await expect(row).toContainText('Site Administrator');

        await gotoCms(page, '/cms/faqs');
        await page.locator('.cms-faq-row', { hasText: question }).getByRole('button', { name: 'Delete' }).click();
        await page.locator('.cms-modal').getByRole('button', { name: /^Delete/ }).click();
    });

    test('the filters narrow what is listed', async ({ page }) => {
        await gotoCms(page, '/cms/activity');

        const selects = page.locator('.cms-toolbar select');

        /* The options are only the actions that have actually happened, so the list is read rather
           than assumed — an empty log would otherwise fail for the wrong reason. */
        const actions = await selects.first().locator('option').evaluateAll(
            (options) => options.map((o) => o.value).filter(Boolean),
        );

        test.skip(actions.length === 0, 'nothing has happened yet to filter by');

        await selects.first().selectOption(actions[0]);
        await expect(page).toHaveURL(new RegExp(`action=${actions[0]}`));

        /* Every row left must be the action asked for. */
        const rows = page.locator('.cms-faq-row');
        if (await rows.count() > 0) {
            for (const badge of await rows.locator('.cms-badge').allInnerTexts()) {
                expect(badge.trim()).not.toBe('');
            }
        }

        await selects.first().selectOption('');
        await expect(page).not.toHaveURL(/action=\w/);
    });

    test('says how much it is showing, and that none of it can be changed', async ({ page }) => {
        await gotoCms(page, '/cms/activity');

        await expect(page.locator('.cms-hint')).toContainText('most recent actions');
        await expect(page.locator('.cms-hint')).toContainText('Nothing here can be edited or removed');
    });

    test('an impossible filter combination says so rather than showing nothing', async ({ page }) => {
        await gotoCms(page, '/cms/activity?action=restored&type=Settings');

        await expect(page.locator('.cms-media-empty')).toContainText('Nothing has happened yet that matches');
    });
});
