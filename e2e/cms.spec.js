import { expect, test } from './fixtures.js';
import { CLIENT_ADMIN, signIn } from './helpers.js';

const MODULES = [
    '/cms', '/cms/pages', '/cms/blog', '/cms/faqs', '/cms/testimonials', '/cms/media',
    '/cms/enquiries', '/cms/navigation', '/cms/global-content', '/cms/activity',
    '/cms/deleted', '/cms/users', '/cms/settings', '/cms/account',
];

test('every admin module loads for a super administrator', async ({ page }) => {
    const errors = [];
    page.on('pageerror', (e) => errors.push(e.message));
    /* `ERR_FAILED` is the suite's own doing — media bytes are aborted by the fixture, and the
       browser reports each one. Anything else is the application's. */
    page.on('console', (m) => {
        if (m.type() === 'error' && ! m.text().includes('net::ERR_FAILED')) errors.push(m.text());
    });

    /* `domcontentloaded`, not the default `load`. The media library leaves a thumbnail request per
       image in flight, each streamed out of storage by PHP, and `artisan serve` answers one request
       at a time — so waiting for every subresource means waiting for the whole library behind the
       page you asked for. What is being asserted is that the screen arrives and renders, and the
       shell being visible says that better than a load event does. */
    for (const path of MODULES) {
        const response = await page.goto(path, { waitUntil: 'domcontentloaded' });
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
