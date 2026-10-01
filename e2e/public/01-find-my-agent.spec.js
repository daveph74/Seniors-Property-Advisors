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

const answer = async (page, { email = 'e2e@example.invalid', open } = {}) => {
    if (open) {
        await open();
    } else {
        await page.getByRole('button', { name: /find my agent/i }).first().click();
    }

    const modal = page.locator('.modal-back.open');
    await expect(modal).toBeVisible();

    await page.fill('#fma-address', '12 Smith Street, Mosman NSW');
    await modal.locator('.opt', { hasText: 'House' }).first().click();
    await page.getByRole('button', { name: /continue/i }).click();

    await expect(modal.locator('.step-count')).toHaveText('Step 2 of 3');
    await expect(modal.getByLabel('First Name')).toBeVisible();
    await expect(modal.getByLabel('Surname')).toBeVisible();
    await expect(modal.locator('.opt')).toHaveCount(3);
    await expect(modal.locator('input[type="checkbox"]')).toHaveCount(0);

    await page.getByRole('button', { name: /continue/i }).click();
    await expect(modal.locator('#fma-firstName-error')).toBeVisible();
    await expect(modal.locator('#fma-surname-error')).toBeVisible();
    await modal.getByLabel('First Name').fill('Playwright');
    await modal.getByLabel('Surname').fill('Sender');
    await page.fill('#fma-phone', '0412 345 678');
    await page.fill('#fma-email', email);
    await modal.locator('.opt', { hasText: 'Morning' }).first().click();

    await page.getByRole('button', { name: /continue/i }).click();

    await expect(modal.locator('.step-count')).toHaveText('Step 3 of 3');
    await expect(modal.locator('#modal-title')).toHaveText('When are you hoping to sell?');
    await page.getByRole('button', { name: /submit/i }).click();
    await expect(modal.locator('#fma-timeline-error')).toBeVisible();
    await modal.locator('.opt', { hasText: 'Now' }).first().click();
    await page.fill('#fma-notes', 'Sent by the end-to-end suite.');

    await page.getByRole('button', { name: /back/i }).click();
    await expect(modal.getByLabel('First Name')).toHaveValue('Playwright');
    await expect(modal.getByLabel('Surname')).toHaveValue('Sender');
    await page.getByRole('button', { name: /continue/i }).click();
    await expect(modal.getByRole('radio', { name: /Now/ })).toHaveAttribute('aria-checked', 'true');
    await expect(modal.locator('#fma-notes')).toHaveValue('Sent by the end-to-end suite.');
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

test('the start box opens the form', async ({ page }) => {
    const problems = [];
    page.on('pageerror', (error) => problems.push(error.message));

    await page.goto('/home-preview', { waitUntil: 'domcontentloaded' });

    const box = page.locator('.block-finder-start');
    await expect(box.locator('input')).toHaveCount(0);

    const sent = page.waitForRequest((request) => request.method() === 'POST' && request.url().endsWith('/enquiries'));

    const modal = await answer(page, {
        email: 'start-box@example.invalid',
        open: () => box.getByRole('button', { name: 'Start Here' }).click(),
    });

    expect((await sent).postDataJSON().details.location.street).toBeTruthy();
    await expect(modal.locator('.success')).toBeVisible();

    expect(problems, 'the start box and the wizard must raise no errors').toEqual([]);
});

test.describe('on a phone', () => {
    test.use({ viewport: { width: 375, height: 667 }, hasTouch: true, isMobile: true });

    test('every field can be reached, and the page behind stays put', async ({ page }) => {
        const problems = [];
        page.on('pageerror', (error) => problems.push(error.message));

        await page.goto('/', { waitUntil: 'domcontentloaded' });
        await expect
            .poll(() => page.evaluate(() => (window.scrollTo(0, 600), window.scrollY)))
            .toBeGreaterThan(0);
        const before = await page.evaluate(() => window.scrollY);

        await page.getByRole('button', { name: /find my agent/i }).first().click();
        const back = page.locator('.modal-back.open');
        await expect(back).toBeVisible();

        await page.fill('#fma-address', '12 Smith Street, Mosman NSW');
        await back.locator('.opt', { hasText: 'House' }).first().click();
        await page.getByRole('button', { name: /continue/i }).click();
        await expect(back.locator('.step-count')).toHaveText('Step 2 of 3');

        const first = await back.getByLabel('First Name').boundingBox();
        const surname = await back.getByLabel('Surname').boundingBox();
        expect(surname.y, 'surname stacks under first name').toBeGreaterThan(first.y + first.height);
        for (const id of ['#fma-firstName', '#fma-surname', '#fma-phone', '#fma-email']) {
            expect((await back.locator(id).boundingBox()).width, `${id} has room to type in`).toBeGreaterThanOrEqual(280);
        }
        const fontSize = await back.locator('#fma-email').evaluate((el) => parseFloat(getComputedStyle(el).fontSize));
        expect(fontSize, 'under 16px, iOS zooms the page on focus').toBeGreaterThanOrEqual(16);

        expect(await back.evaluate((el) => el.scrollHeight > el.clientHeight), 'step 2 is taller than a phone').toBe(true);

        const bodyTop = () => page.evaluate(() => document.body.getBoundingClientRect().top);
        const pinned = await bodyTop();
        await page.mouse.move(187, 400);
        await page.mouse.wheel(0, 2000);
        await expect(back.locator('.opt', { hasText: 'Morning' })).toBeInViewport();
        await expect(page.getByRole('button', { name: /continue/i })).toBeInViewport();
        expect(await bodyTop(), 'scrolling the form must not move the page behind it').toBe(pinned);

        await page.mouse.wheel(0, -2000);
        await expect(back.locator('#modal-title')).toBeInViewport();

        await page.getByRole('button', { name: 'Close' }).click();
        await expect(page.locator('.modal-back.open')).toHaveCount(0);
        expect(await page.evaluate(() => window.scrollY), 'closing puts the page back where it was').toBe(before);

        expect(problems).toEqual([]);
    });

    test('the whole form can be finished', async ({ page }) => {
        await page.goto('/', { waitUntil: 'domcontentloaded' });

        const modal = await answer(page, { email: 'phone@example.invalid' });

        await expect(modal.locator('.ref')).toContainText(/AF-\d{4}-\d{5}/);
    });
});
