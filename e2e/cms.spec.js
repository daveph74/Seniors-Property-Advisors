import { expect, test } from '@playwright/test';
import { CLIENT_ADMIN, signIn } from './helpers.js';

const MODULES = [
    '/cms', '/cms/pages', '/cms/blog', '/cms/faqs', '/cms/testimonials', '/cms/media',
    '/cms/enquiries', '/cms/navigation', '/cms/global-content', '/cms/activity',
    '/cms/deleted', '/cms/users', '/cms/settings', '/cms/account',
];

test('every admin module loads for a super administrator', async ({ page }) => {
    const errors = [];
    page.on('pageerror', (e) => errors.push(e.message));
    page.on('console', (m) => m.type() === 'error' && errors.push(m.text()));

    for (const path of MODULES) {
        const response = await page.goto(path);
        expect(response.status(), path).toBe(200);
        await expect(page.locator('.cms-shell'), path).toBeVisible();
    }

    expect(errors).toEqual([]);
});

/* These sign in themselves, so they start without the shared super-administrator cookie. */
test.describe('signing in', () => {
    test.use({ storageState: { cookies: [], origins: [] } });

    test('the CMS is closed to a signed-out visitor', async ({ page }) => {
        await page.goto('/cms');

        await expect(page).toHaveURL(/\/login$/);
    });

    test('a wrong password is rejected', async ({ page }) => {
        await page.goto('/login');
        await page.locator('input[type="email"]').fill(CLIENT_ADMIN.email);
        await page.locator('input[type="password"]').fill('not-the-password');
        await page.getByRole('button', { name: /sign in/i }).click();

        await expect(page.locator('.cms-signin__error')).toBeVisible();
        await expect(page).toHaveURL(/\/login$/);
    });

    test('a client administrator is kept out of the super-admin modules', async ({ page }) => {
        await signIn(page, CLIENT_ADMIN);

        for (const path of ['/cms/users', '/cms/settings', '/cms/deleted']) {
            const response = await page.goto(path, { waitUntil: 'commit' });
            expect(response.status(), path).toBe(403);
        }
    });

    test('signing out closes the CMS', async ({ page }) => {
        await signIn(page);

        await page.getByRole('button', { name: /Site Administrator/ }).click();
        await page.getByText('Sign out').click();

        await page.goto('/cms');
        await expect(page).toHaveURL(/\/login$/);
    });
});
