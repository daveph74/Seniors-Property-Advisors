import { expect, test } from '../fixtures.js';
import { gotoCms } from '../helpers.js';
import { uniqueValue } from '../support/unique.js';

test.describe('Global content', () => {
    test.beforeEach(async ({ page }) => {
        await gotoCms(page, '/cms/global-content');
    });

    /* It says so itself, and it is the reason this screen has no draft. */
    test('warns that a change here is live immediately', async ({ page }) => {
        await expect(page.locator('.cms-impact-banner')).toContainText('appears on every page');
        await expect(page.locator('.cms-impact-banner')).toContainText('no draft to publish');
    });

    test('the save button is inert until something changes', async ({ page }) => {
        await expect(page.getByRole('button', { name: 'Save changes' })).toBeDisabled();

        await page.getByPlaceholder(/Free guide/i).fill(uniqueValue('Notice'));

        await expect(page.getByRole('button', { name: 'Save changes' })).toBeEnabled();
    });

    test('the announcement wording saves and comes back', async ({ page }) => {
        const wording = uniqueValue('Notice');
        const original = await page.getByPlaceholder(/Free guide/i).inputValue();

        await page.getByPlaceholder(/Free guide/i).fill(wording);
        await page.getByRole('button', { name: 'Save changes' }).click();
        await expect(page.getByText('Global content saved')).toBeVisible();

        await page.reload({ waitUntil: 'domcontentloaded' });
        await expect(page.getByPlaceholder(/Free guide/i)).toHaveValue(wording);

        await page.getByPlaceholder(/Free guide/i).fill(original);
        await page.getByRole('button', { name: 'Save changes' }).click();
        await expect(page.getByText('Global content saved')).toBeVisible();
    });

    test('the announcement bar can be switched off and back on', async ({ page }) => {
        const toggle = page.getByRole('switch', { name: 'Show the announcement bar' });
        const was = await toggle.getAttribute('aria-checked');

        await toggle.click();
        await expect(toggle).toHaveAttribute('aria-checked', was === 'true' ? 'false' : 'true');

        await page.getByRole('button', { name: 'Save changes' }).click();
        await expect(page.getByText('Global content saved')).toBeVisible();

        await page.reload({ waitUntil: 'domcontentloaded' });
        await expect(page.getByRole('switch', { name: 'Show the announcement bar' }))
            .toHaveAttribute('aria-checked', was === 'true' ? 'false' : 'true');

        await page.getByRole('switch', { name: 'Show the announcement bar' }).click();
        await page.getByRole('button', { name: 'Save changes' }).click();
        await expect(page.getByText('Global content saved')).toBeVisible();
    });

    test('every group the screen promises is present', async ({ page }) => {
        for (const group of ['Announcement bar', 'Header', 'Logo', 'Footer']) {
            await expect(page.locator('.cms-card__title', { hasText: group }), group).toBeVisible();
        }

        await expect(page.getByLabel('Phone number')).toBeVisible();
        await expect(page.getByLabel('Button wording')).toBeVisible();
        await expect(page.getByLabel('Describe the logo')).toBeVisible();
        await expect(page.getByLabel('Copyright line')).toBeVisible();
    });
});
