import { expect } from '@playwright/test';

export const SUPER_ADMIN = { email: 'superadmin@seniorspropertyadvisors.com.au', password: 'password' };
export const CLIENT_ADMIN = { email: 'helen@seniorspropertyadvisors.com.au', password: 'password' };

export const SUPER_ADMIN_STATE = 'test-results/.auth/super-admin.json';

/**
 * Navigate, then wait for React.
 *
 * `domcontentloaded` is deliberate — waiting for `load` means waiting for every image, and the
 * media library serialises those behind a one-request-at-a-time server. But it returns before
 * anything is rendered, so a count taken straight afterwards is a count of nothing. Waiting for
 * the shell is what makes the difference between measuring the screen and measuring the gap
 * before it.
 */
export async function gotoCms(page, path) {
    await page.goto(path, { waitUntil: 'domcontentloaded' });

    await expect(page.locator('.cms-shell')).toBeVisible();
}

/**
 * The shell only exists once React has hydrated, and the palette's keyboard listener with it — so
 * pressing the shortcut against a page that has merely arrived does nothing at all.
 */
export async function openPalette(page) {
    await expect(page.locator('.cms-shell')).toBeVisible();

    await page.keyboard.press('Control+k');
    await expect(page.locator('.cms-palette')).toBeVisible();
}

export async function signIn(page, account = SUPER_ADMIN) {
    await page.goto('/login');
    await page.locator('input[type="email"]').fill(account.email);
    await page.locator('input[type="password"]').fill(account.password);
    await page.getByRole('button', { name: /sign in/i }).click();

    await expect(page).toHaveURL(/\/cms\/?$/);
}
