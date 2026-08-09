import { test as setup } from '@playwright/test';
import { SUPER_ADMIN, SUPER_ADMIN_STATE, signIn } from './helpers.js';

/* Signing in once and reusing the cookie. `/login` is throttled at ten attempts a minute —
   deliberately, and a suite that signs in per test locks itself out halfway through. */
setup('sign in as a super administrator', async ({ page }) => {
    await signIn(page, SUPER_ADMIN);
    await page.context().storageState({ path: SUPER_ADMIN_STATE });
});
