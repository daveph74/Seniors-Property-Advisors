import { expect, test } from '../fixtures.js';
import { CLIENT_ADMIN, SUPER_ADMIN, gotoCms } from '../helpers.js';
import { unique } from '../support/unique.js';

test.describe('Users and roles', () => {
    test.beforeEach(async ({ page }) => {
        await gotoCms(page, '/cms/users');
    });

    const row = (page, email) => page.locator('.cms-table__row--users', { hasText: email });

    async function addUser(page, name, email, { role = null } = {}) {
        await page.getByRole('button', { name: 'Add a user' }).click();
        await page.locator('.cms-modal input[type="text"], .cms-modal input:not([type])').first().fill(name);
        await page.locator('.cms-modal input[type="email"]').fill(email);
        await page.locator('.cms-modal input[type="password"]').fill('a-long-enough-password');

        if (role) await page.locator('.cms-modal select').selectOption(role);

        await page.getByRole('button', { name: 'Save', exact: true }).click();
    }

    async function removeUser(page, email) {
        if (await row(page, email).count() === 0) return;

        await row(page, email).getByRole('button', { name: 'Delete' }).click();
        await page.locator('.cms-modal').getByRole('button', { name: 'Delete', exact: true }).click();
        await expect(row(page, email)).toHaveCount(0);
    }

    test('an account is created, disabled, enabled and deleted', async ({ page }) => {
        const email = `${unique('editor').replace(/[^a-z0-9-]/gi, '-').toLowerCase()}@example.invalid`;

        await addUser(page, unique('Editor'), email);
        await expect(page.getByText('Account saved')).toBeVisible();
        await expect(row(page, email)).toContainText('Active');

        await row(page, email).getByRole('button', { name: 'Disable' }).click();
        await expect(page.getByText('Account disabled')).toBeVisible();
        await expect(row(page, email)).toContainText('Disabled');

        await row(page, email).getByRole('button', { name: 'Enable' }).click();
        await expect(page.getByText('Account enabled')).toBeVisible();
        await expect(row(page, email)).toContainText('Active');

        await removeUser(page, email);
        await expect(page.getByText('Account deleted')).toBeVisible();
    });

    test('an address already in use is refused', async ({ page }) => {
        await addUser(page, 'Duplicate', CLIENT_ADMIN.email);

        /* The modal stays open with what was typed still in it, so nothing is lost. */
        await expect(page.locator('.cms-modal .cms-field-error').first()).toBeVisible();
        await expect(page.locator('.cms-modal input[type="email"]')).toHaveValue(CLIENT_ADMIN.email);

        await page.locator('.cms-modal').getByRole('button', { name: 'Cancel' }).click();
    });

    test('a password under ten characters is refused', async ({ page }) => {
        await page.getByRole('button', { name: 'Add a user' }).click();
        await page.locator('.cms-modal input[type="text"], .cms-modal input:not([type])').first().fill('Too Short');
        await page.locator('.cms-modal input[type="email"]').fill('too-short@example.invalid');
        await page.locator('.cms-modal input[type="password"]').fill('short');
        await page.getByRole('button', { name: 'Save', exact: true }).click();

        await expect(page.locator('.cms-modal .cms-field-error').first()).toBeVisible();
        await page.locator('.cms-modal').getByRole('button', { name: 'Cancel' }).click();
    });

    /**
     * You cannot delete yourself. The row says "you" and simply has no Delete — the guard is the
     * absence of the control, not a refusal after the fact.
     */
    test('your own account offers no delete', async ({ page }) => {
        const me = row(page, SUPER_ADMIN.email);

        await expect(me).toContainText('you');
        await expect(me.getByRole('button', { name: 'Delete' })).toHaveCount(0);
        await expect(me.getByRole('button', { name: 'Edit' })).toBeVisible();
    });

    test('the delete confirmation says what is lost and what is kept', async ({ page }) => {
        const email = `${unique('doomed').replace(/[^a-z0-9-]/gi, '-').toLowerCase()}@example.invalid`;

        await addUser(page, unique('Doomed'), email);
        await row(page, email).getByRole('button', { name: 'Delete' }).click();

        const modal = page.locator('.cms-modal');
        await expect(modal).toContainText('Delete this account?');
        await expect(modal).toContainText('lose access immediately');
        await expect(modal).toContainText('keeps their name against it');

        await modal.getByRole('button', { name: 'Cancel' }).click();
        await removeUser(page, email);
    });
});
