import { expect, test } from '../fixtures.js';
import { gotoCms } from '../helpers.js';
import { uniqueValue } from '../support/unique.js';

test.describe('Settings', () => {
    test.beforeEach(async ({ page }) => {
        await gotoCms(page, '/cms/settings');
    });

    const save = (page) => page.getByRole('button', { name: 'Save settings' });
    const tab = (page, name) => page.locator('.cms-settings-tab', { hasText: name });

    test('warns that a change here is live immediately', async ({ page }) => {
        await expect(page.locator('.cms-page .cms-impact-banner')).toContainText('affect every page');
    });

    test('each tab reveals its own fields', async ({ page }) => {
        await expect(page.getByLabel('Website name')).toBeVisible();

        await tab(page, 'SEO defaults').click();
        await expect(page.getByLabel('Title pattern')).toBeVisible();
        await expect(tab(page, 'SEO defaults')).toHaveClass(/cms-settings-tab--active/);

        await tab(page, 'Tracking').click();
        await expect(page.getByPlaceholder('G-XXXXXXXXXX')).toBeVisible();

        await tab(page, 'Legal').click();
        await expect(page.getByLabel('Footer disclaimer')).toBeVisible();

        await tab(page, 'General').click();
        await expect(page.getByLabel('Website name')).toBeVisible();
    });

    test('the save button is inert until something changes', async ({ page }) => {
        await expect(save(page)).toBeDisabled();

        await page.getByLabel('Website name').fill(uniqueValue('Site'));

        await expect(save(page)).toBeEnabled();
    });

    /* Both ids are format-checked, because they are printed inside a <script> where escaping
       would not help. */
    test('a malformed analytics id is refused and a good one saves', async ({ page }) => {
        await tab(page, 'Tracking').click();

        const original = await page.getByPlaceholder('G-XXXXXXXXXX').inputValue();

        await page.getByPlaceholder('G-XXXXXXXXXX').fill('not-an-id');
        await save(page).click();
        await expect(page.locator('.cms-field-error').first()).toBeVisible();

        /* A well-formed one goes through. Note it must differ from what was there, or the form is
           not dirty and Save stays disabled. */
        await page.getByPlaceholder('G-XXXXXXXXXX').fill('G-E2E1234567');
        await save(page).click();
        await expect(page.getByText('Settings saved')).toBeVisible();

        await page.getByPlaceholder('G-XXXXXXXXXX').fill(original);
        await save(page).click();
        await expect(page.getByText('Settings saved')).toBeVisible();
    });

    test('the description counter tracks what is typed', async ({ page }) => {
        await tab(page, 'SEO defaults').click();

        const text = uniqueValue('A description');
        await page.getByLabel('Default description').fill(text);

        await expect(page.locator('.cms-field-count, .cms-hint').filter({ hasText: 'of 320' }))
            .toContainText(String(text.length));
    });

    test('the website name saves and comes back', async ({ page }) => {
        const original = await page.getByLabel('Website name').inputValue();
        const name = uniqueValue('Seniors');

        await page.getByLabel('Website name').fill(name);
        await save(page).click();
        await expect(page.getByText('Settings saved')).toBeVisible();

        await page.reload({ waitUntil: 'domcontentloaded' });
        await expect(page.getByLabel('Website name')).toHaveValue(name);

        await page.getByLabel('Website name').fill(original);
        await save(page).click();
        await expect(page.getByText('Settings saved')).toBeVisible();
    });
});
