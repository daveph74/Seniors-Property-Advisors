import { expect } from '@playwright/test';

export const SUPER_ADMIN = { email: 'superadmin@seniorspropertyadvisors.com.au', password: 'password' };
export const CLIENT_ADMIN = { email: 'helen@seniorspropertyadvisors.com.au', password: 'password' };

export const SUPER_ADMIN_STATE = 'test-results/.auth/super-admin.json';

/**
 * Waits for an element's CSS animations to finish.
 *
 * Modals arrive with `cms-anim-modal`, and Playwright will not type into something that is still
 * moving — it waits for two frames at the same position, which an animating element never gives it,
 * and the action times out with the field sitting there perfectly visible. Asking the browser when
 * its own animations have finished is exact where an arbitrary wait is a guess.
 */
export async function settled(locator) {
    await locator.evaluate((el) => Promise.all(
        el.getAnimations({ subtree: true }).map((animation) => animation.finished.catch(() => {})),
    ));
}

/**
 * The flash message, rather than any text on the page that happens to match it.
 *
 * "Published" is both a toast and a status badge; "Delete" is a row button, a modal button and an
 * editor action. Asserting on bare text catches whichever the page happens to hold.
 */
export function toast(page) {
    return page.locator('.cms-toast');
}

/**
 * A field on a CMS screen, by its visible label.
 *
 * Most of the admin writes `<label class="cms-field-label">Title</label>` with the input as a
 * *sibling* — no `htmlFor`, no wrapping — so nothing associates the two and `getByLabel` finds
 * nothing at all. Global content and the blog's SEO panel happen to wrap theirs, which is why some
 * screens answer to `getByLabel` and most do not.
 *
 * Matching is exact on purpose: "Title" would otherwise also catch "SEO title".
 */
export function cmsField(scope, label) {
    /* The `has:` locator has to be built from the page, not from `scope`. Built from a locator it
       carries that locator's own selector with it, so scoping to a modal produced
       `.cms-field >> .cms-modal .cms-field-label` — which matches nothing, and the field simply
       never resolved. It worked wherever `scope` happened to be the page, which is what made it
       look like a problem with the screen rather than with this helper. */
    const root = typeof scope.page === 'function' ? scope.page() : scope;

    return scope
        .locator('.cms-field')
        .filter({ has: root.locator('.cms-field-label', { hasText: new RegExp(`^${label}$`) }) })
        .first();
}

/** The control inside such a field. */
export function cmsInput(scope, label) {
    return cmsField(scope, label).locator('input, textarea, select').first();
}

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
