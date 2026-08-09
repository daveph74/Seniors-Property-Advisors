import { expect, test } from '../fixtures.js';

test.describe.configure({ mode: 'serial' });

const QUESTION = 'Does the end-to-end suite reach the FAQ screen?';

test('a question is added, hidden, edited and deleted', async ({ page }) => {
    await page.goto('/cms/faqs');

    await page.getByRole('button', { name: 'Add a question' }).click();
    await page.locator('.cms-modal input, .cms-modal textarea').first().fill(QUESTION);
    await page.locator('.cms-modal textarea').first().fill('It does.');
    await page.getByRole('button', { name: 'Save', exact: true }).click();

    const row = page.locator('.cms-faq-row', { hasText: QUESTION });
    await expect(row).toBeVisible();

    await row.locator('.cms-toggle, input[type="checkbox"]').first().click();
    await expect(row.getByText('Hidden')).toBeVisible();

    await row.getByRole('button', { name: 'Delete' }).click();
    await page.getByRole('button', { name: /^(Delete|Yes, delete)/ }).last().click();

    await expect(page.locator('.cms-faq-row', { hasText: QUESTION })).toHaveCount(0);
});

test('a category can be added', async ({ page }) => {
    await page.goto('/cms/faqs');

    const rows = page.locator('.cms-cat-row');
    const before = await rows.count();

    await page.getByPlaceholder('e.g. Downsizing').fill('E2E category');
    await page.getByPlaceholder('e.g. Downsizing').press('Enter');

    await expect(rows).toHaveCount(before + 1);
    await expect(page.getByRole('button', { name: 'Delete E2E category' })).toBeVisible();
});
