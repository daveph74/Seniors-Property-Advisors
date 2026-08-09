import { expect } from '@playwright/test';

export const SUPER_ADMIN = { email: 'superadmin@seniorspropertyadvisors.com.au', password: 'password' };
export const CLIENT_ADMIN = { email: 'helen@seniorspropertyadvisors.com.au', password: 'password' };

export const SUPER_ADMIN_STATE = 'test-results/.auth/super-admin.json';

export async function signIn(page, account = SUPER_ADMIN) {
    await page.goto('/login');
    await page.locator('input[type="email"]').fill(account.email);
    await page.locator('input[type="password"]').fill(account.password);
    await page.getByRole('button', { name: /sign in/i }).click();

    await expect(page).toHaveURL(/\/cms\/?$/);
}
