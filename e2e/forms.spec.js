import { expect, test } from '@playwright/test';
import { CLIENT_ADMIN, SUPER_ADMIN } from './helpers.js';

test('the global content form saves', async ({ page }) => {
    await page.goto('/cms/global-content');

    const notice = page.getByPlaceholder(/Free guide/i);
    await notice.fill('Edited by the end-to-end suite');

    await page.getByRole('button', { name: 'Save changes' }).click();

    await expect(page.getByText(/saved/i).first()).toBeVisible();

    await page.reload();
    await expect(page.getByPlaceholder(/Free guide/i)).toHaveValue('Edited by the end-to-end suite');
});

/* The save button is disabled until something changes, so the edit is the point of the test. */
test('an edited menu label saves', async ({ page }) => {
    await page.goto('/cms/navigation');

    const label = page.locator('input[placeholder="Wording"]:not([disabled])').first();
    await label.fill('How it works ');

    const save = page.getByRole('button', { name: 'Save menus' });
    await expect(save).toBeEnabled();
    await save.click();

    await expect(page.getByText(/saved/i).first()).toBeVisible();
});

test('the settings form saves and rejects a bad tracking id', async ({ page }) => {
    await page.goto('/cms/settings');
    await page.getByRole('button', { name: 'Tracking' }).click();

    await page.getByPlaceholder('G-XXXXXXXXXX').fill('not-an-id');
    await page.getByRole('button', { name: /Save settings/ }).click();
    await expect(page.locator('.cms-error, .cms-field-error, [role="alert"]').first()).toBeVisible();

    await page.getByPlaceholder('G-XXXXXXXXXX').fill('G-E2E1234567');
    await page.getByRole('button', { name: /Save settings/ }).click();
    await expect(page.getByText(/saved/i).first()).toBeVisible();
});

test('the password form refuses a wrong current password', async ({ page }) => {
    await page.goto('/cms/account');

    const fields = page.locator('input[type="password"]');
    await fields.nth(0).fill('wrong-password');
    await fields.nth(1).fill('a-long-enough-new-password');
    await fields.nth(2).fill('a-long-enough-new-password');
    await page.getByRole('button', { name: 'Update password' }).click();

    await expect(page.getByText(/password/i).filter({ hasText: /not|incorrect|wrong/i }).first()).toBeVisible();
});

test('the password form refuses a mismatched confirmation', async ({ page }) => {
    await page.goto('/cms/account');

    const fields = page.locator('input[type="password"]');
    await fields.nth(0).fill(SUPER_ADMIN.password);
    await fields.nth(1).fill('a-long-enough-new-password');
    await fields.nth(2).fill('a-different-password');
    await page.getByRole('button', { name: 'Update password' }).click();

    await expect(page.locator('.cms-field-error, .cms-error, [role="alert"]').first()).toBeVisible();
});

test('a new account is created and the form rejects a duplicate address', async ({ page }) => {
    await page.goto('/cms/users');

    const fill = async (name, email) => {
        await page.getByRole('button', { name: 'Add a user' }).click();
        await page.locator('.cms-modal input[type="text"], .cms-modal input:not([type])').first().fill(name);
        await page.locator('.cms-modal input[type="email"]').fill(email);
        await page.locator('.cms-modal input[type="password"]').fill('a-long-enough-password');
        await page.getByRole('button', { name: 'Save', exact: true }).click();
    };

    await fill('E2E Editor', 'e2e-editor@example.com');
    await expect(page.getByText('e2e-editor@example.com')).toBeVisible();

    /* A duplicate address is refused, so the modal stays open with the address still in it. */
    await fill('Duplicate', CLIENT_ADMIN.email);
    await expect(page.locator('.cms-modal .cms-field-error').first()).toBeVisible();
    await expect(page.locator('.cms-modal input[type="email"]')).toHaveValue(CLIENT_ADMIN.email);
});
