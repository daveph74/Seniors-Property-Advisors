import { expect, test } from '../fixtures.js';

/**
 * The one part of the public site this suite drives, and it earns the exception.
 *
 * Everything else out there is rendered from data the PHPUnit tests already assert. This is a
 * four-step form held together by React state, and the failure it is here to catch has already
 * happened once: a renamed constant left `options={TIMES}` behind, so step 3 threw a ReferenceError
 * the moment somebody reached it. The build was clean, 699 PHPUnit tests were green, and the form
 * was broken for every visitor — because no test had ever pressed the buttons.
 *
 * Signed out on purpose: an enquirer never has a CMS session, and the header CTA is what they press.
 */
test.use({ storageState: { cookies: [], origins: [] } });

const answer = async (page, { consent = true, email = 'e2e@example.invalid' } = {}) => {
    await page.getByRole('button', { name: /find my agent/i }).first().click();

    const modal = page.locator('.modal-back.open');
    await expect(modal).toBeVisible();

    await page.fill('#fma-suburb', 'Mosman');
    await modal.locator('.opt', { hasText: 'House' }).first().click();
    await page.getByRole('button', { name: /continue/i }).click();

    await modal.locator('.opt', { hasText: 'Within 3 months' }).first().click();
    await page.fill('#fma-notes', 'Sent by the end-to-end suite.');
    await page.getByRole('button', { name: /continue/i }).click();

    /* Reaching step 3 at all is the assertion the ReferenceError would have failed. */
    await expect(modal.locator('#fma-name')).toBeVisible();
    await expect(modal.locator('.opt')).toHaveCount(3);

    await page.fill('#fma-name', 'Playwright Sender');
    await page.fill('#fma-phone', '0412 345 678');
    await page.fill('#fma-email', email);
    await modal.locator('.opt', { hasText: 'Morning' }).first().click();

    if (consent) {
        await page.check('#fma-consent');
    }

    await page.getByRole('button', { name: /submit/i }).click();

    return modal;
};

test('every step renders, and a finished form comes back with a real reference', async ({ page }) => {
    const problems = [];
    page.on('pageerror', (error) => problems.push(error.message));

    await page.goto('/', { waitUntil: 'domcontentloaded' });

    const modal = await answer(page);

    await expect(modal.locator('.success')).toBeVisible();
    await expect(modal.locator('.success')).toContainText('Playwright');
    /* The reference belongs to the row that was just written, so it is a shape rather than a
       literal — it used to be the same five digits for everybody. */
    await expect(modal.locator('.ref')).toContainText(/AF-\d{4}-\d{5}/);
    /* Nothing sends mail, so nothing may say it does. */
    await expect(modal.locator('.success')).not.toContainText(/email/i);

    expect(problems, 'the wizard must raise no errors on any step').toEqual([]);
});

test('it will not send without consent, and says so on the step that asks', async ({ page }) => {
    await page.goto('/', { waitUntil: 'domcontentloaded' });

    const modal = await answer(page, { consent: false, email: 'no-consent@example.invalid' });

    await expect(modal.locator('#fma-consent-error')).toBeVisible();
    await expect(modal.locator('.step-count')).toHaveText('Step 3 of 3');
    await expect(modal.locator('.success')).toHaveCount(0);
});
