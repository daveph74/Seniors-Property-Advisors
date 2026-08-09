import { expect, test } from '../fixtures.js';

test.describe.configure({ mode: 'serial' });

async function openBuilder(page, title) {
    await page.goto('/cms/pages');
    await page.getByRole('button', { name: title, exact: true }).first().click();

    await expect(page).toHaveURL(/\/cms\/pages\/\d+\/edit$/);
    await expect(page.getByRole('button', { name: 'Save draft' })).toBeVisible();
}

test('a page opens in the builder and its draft saves', async ({ page }) => {
    await openBuilder(page, 'Contact');

    await page.getByRole('button', { name: 'Save draft' }).click();

    await expect(page.getByText('Draft saved')).toBeVisible();
});

test('publishing an unchanged page reports it live', async ({ page }) => {
    await openBuilder(page, 'How it works');

    await page.getByRole('button', { name: /^Publish$/ }).first().click();
    await expect(page.getByText('Publish this page?')).toBeVisible();
    await page.getByRole('button', { name: 'Publish now' }).click();

    await expect(page.getByText(/is now live/)).toBeVisible();
});

test('the draft preview renders the page', async ({ page }) => {
    await openBuilder(page, 'Contact');

    const id = page.url().match(/pages\/(\d+)\/edit/)[1];

    const response = await page.goto(`/cms/pages/${id}/preview`);
    expect(response.status()).toBe(200);
    await expect(page.locator('h1').first()).toBeVisible();
});
