import { expect, test } from '../fixtures.js';
import { SUPER_ADMIN, gotoCms } from '../helpers.js';

test.describe('Account', () => {
    test.beforeEach(async ({ page }) => {
        await gotoCms(page, '/cms/account');
    });

    const fields = (page) => page.locator('input[type="password"]');

    test('shows who you are signed in as', async ({ page }) => {
        const facts = page.locator('.cms-account-facts');

        await expect(facts).toContainText(SUPER_ADMIN.email);
        await expect(facts).toContainText('Super Administrator');
    });

    /* It signs you out everywhere else, so the screen says so before you do it. */
    test('warns that changing it ends other sessions', async ({ page }) => {
        await expect(page.getByText(/signs you out everywhere else/i)).toBeVisible();
    });

    test('the submit stays disabled until both passwords are filled', async ({ page }) => {
        const submit = page.getByRole('button', { name: 'Update password' });

        await expect(submit).toBeDisabled();

        await fields(page).nth(0).fill(SUPER_ADMIN.password);
        await expect(submit).toBeDisabled();

        await fields(page).nth(1).fill('a-long-enough-new-password');
        await expect(submit).toBeEnabled();
    });

    test('a wrong current password is refused', async ({ page }) => {
        await fields(page).nth(0).fill('not-the-password');
        await fields(page).nth(1).fill('a-long-enough-new-password');
        await fields(page).nth(2).fill('a-long-enough-new-password');
        await page.getByRole('button', { name: 'Update password' }).click();

        await expect(page.locator('.cms-field-error').first()).toContainText(/not correct/i);
    });

    test('a mismatched confirmation is refused', async ({ page }) => {
        await fields(page).nth(0).fill(SUPER_ADMIN.password);
        await fields(page).nth(1).fill('a-long-enough-new-password');
        await fields(page).nth(2).fill('a-different-password');
        await page.getByRole('button', { name: 'Update password' }).click();

        await expect(page.locator('.cms-field-error').first()).toContainText(/do not match/i);
    });

    /* Ten characters is the floor, and the hint on the field says so. */
    test('a short password is refused', async ({ page }) => {
        await fields(page).nth(0).fill(SUPER_ADMIN.password);
        await fields(page).nth(1).fill('short');
        await fields(page).nth(2).fill('short');
        await page.getByRole('button', { name: 'Update password' }).click();

        await expect(page.locator('.cms-field-error').first()).toBeVisible();
    });
});
