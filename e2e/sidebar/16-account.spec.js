import { expect, test } from '../fixtures.js';
import { SUPER_ADMIN, gotoCms } from '../helpers.js';

test.describe('Account', () => {
    test.beforeEach(async ({ page }) => {
        await gotoCms(page, '/cms/account');
    });

    const fields = (page) => page.locator('input[type="password"]');

    /* Nothing here ever completes a change: the whole suite shares one sign-in, and moving the
       seeded password would strand the tests that sign in themselves. */
    const ACCEPTABLE = 'Windmill-Harbour-4';

    test('shows who you are signed in as', async ({ page }) => {
        const facts = page.locator('.cms-account-facts');

        await expect(facts).toContainText(SUPER_ADMIN.email);
        await expect(facts).toContainText('Super Administrator');
    });

    /* It signs you out everywhere else, so the screen says so before you do it. */
    test('warns that changing it ends other sessions', async ({ page }) => {
        await expect(page.getByText(/signs you out everywhere else/i)).toBeVisible();
    });

    /* The seeded account is still on the password it was given, which is what raises this. */
    test('says so while the password has never been changed', async ({ page }) => {
        await expect(page.locator('.cms-impact-banner')).toContainText(/password this account was given/i);
    });

    test('the submit stays disabled until the new password meets the policy', async ({ page }) => {
        const submit = page.getByRole('button', { name: 'Update password' });

        await expect(submit).toBeDisabled();

        await fields(page).nth(0).fill(SUPER_ADMIN.password);
        await expect(submit).toBeDisabled();

        await fields(page).nth(1).fill('windmillharbour');
        await expect(submit).toBeDisabled();

        await fields(page).nth(1).fill(ACCEPTABLE);
        await expect(submit).toBeEnabled();
    });

    /* The list is the guidance the field used to give in one line, and it has to track the typing. */
    test('the requirement list marks off what has been met', async ({ page }) => {
        const rules = page.locator('.cms-password-rules__item');

        await expect(rules).toHaveCount(0);

        await fields(page).nth(1).fill('windmill');
        await expect(rules).toHaveCount(5);
        await expect(page.locator('.cms-password-rules__item--met')).toHaveCount(1);
        await expect(rules.filter({ hasText: 'At least one uppercase letter' }))
            .not.toHaveClass(/--met/);

        await fields(page).nth(1).fill(ACCEPTABLE);
        await expect(page.locator('.cms-password-rules__item--met')).toHaveCount(5);
    });

    test('the new password can be shown and hidden', async ({ page }) => {
        await fields(page).nth(1).fill(ACCEPTABLE);

        await page.getByRole('button', { name: 'Show password' }).click();
        await expect(page.locator('.cms-reveal input[type="text"]')).toHaveValue(ACCEPTABLE);

        await page.getByRole('button', { name: 'Hide password' }).click();
        await expect(page.locator('.cms-reveal input[type="password"]')).toHaveCount(1);
    });

    test('a wrong current password is refused', async ({ page }) => {
        await fields(page).nth(0).fill('not-the-password');
        await fields(page).nth(1).fill(ACCEPTABLE);
        await fields(page).nth(2).fill(ACCEPTABLE);
        await page.getByRole('button', { name: 'Update password' }).click();

        await expect(page.locator('.cms-field-error').first()).toContainText(/not correct/i);
    });

    test('a mismatched confirmation is refused', async ({ page }) => {
        await fields(page).nth(0).fill(SUPER_ADMIN.password);
        await fields(page).nth(1).fill(ACCEPTABLE);
        await fields(page).nth(2).fill('Different-Harbour-8');
        await page.getByRole('button', { name: 'Update password' }).click();

        await expect(page.locator('.cms-field-error').first()).toContainText(/do not match/i);
    });
});
