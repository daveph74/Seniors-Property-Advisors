import { expect, test } from '../fixtures.js';

test.describe.configure({ mode: 'serial' });

/**
 * The inbox, the bell and the sidebar count — three things that are meant to disagree with each
 * other, and the only way to see that they do is to drive them.
 */
test('an enquiry opens, reads and changes status', async ({ page }) => {
    await page.goto('/cms/enquiries?show=all', { waitUntil: 'domcontentloaded' });

    const row = page.locator('.cms-enquiry-row', { hasText: 'Playwright Enquirer' });
    await expect(row).toBeVisible();
    await expect(row).toHaveClass(/cms-enquiry-row--unread/);

    const badge = page.locator('.cms-icon-btn__badge');
    const before = Number(await badge.innerText());
    expect(before).toBeGreaterThan(0);

    await row.click();
    await expect(page.locator('.cms-modal')).toBeVisible();
    await expect(page.locator('.cms-enquiry-detail__message')).toContainText('end-to-end suite');
    await expect(page).toHaveURL(/open=\d+/);

    /* Reading drops the bell and must leave the sidebar alone — that split is the whole design.
       The numeral only: the count also carries a sentence for a screen reader beside it. */
    const sidebar = page.locator('.cms-nav-item', { hasText: 'Enquiries' })
        .locator('.cms-nav-item__count [aria-hidden="true"]');
    const outstanding = (await sidebar.innerText()).trim();

    /* At zero the badge is not a zero — it is absent, which is the whole point of a count that can
       honestly reach nothing. */
    if (before === 1) {
        await expect(badge).toHaveCount(0);
    } else {
        await expect(badge).toHaveText(String(before - 1));
    }

    await expect(sidebar).toHaveText(outstanding);

    await page.locator('#enquiry-status').selectOption('dealt_with');
    await expect(page.getByText(/marked as dealt with/i)).toBeVisible();

    /* Only now does the work count fall — and like the bell, it goes absent rather than to nought. */
    const left = Number(outstanding) - 1;

    if (left === 0) {
        await expect(sidebar).toHaveCount(0);
    } else {
        await expect(sidebar).toHaveText(String(left));
    }
});

test('the browser Back button closes the enquiry', async ({ page }) => {
    await page.goto('/cms/enquiries?show=all', { waitUntil: 'domcontentloaded' });
    await page.locator('.cms-enquiry-row').first().click();

    await expect(page.locator('.cms-modal')).toBeVisible();

    await page.goBack();

    await expect(page.locator('.cms-modal')).toHaveCount(0);
});

test('the sender’s own words cannot be edited here', async ({ page }) => {
    await page.goto('/cms/enquiries?show=all', { waitUntil: 'domcontentloaded' });
    await page.locator('.cms-enquiry-row').first().click();
    await expect(page.locator('.cms-modal')).toBeVisible();

    /* The status is the only control in the modal that changes anything. */
    const editable = await page.locator('.cms-modal input:not([type=hidden]), .cms-modal textarea').count();

    expect(editable, 'nothing in the modal should accept typing').toBe(0);
    await expect(page.locator('#enquiry-status')).toBeVisible();
});

test('each form has its own tab, and the default shows both', async ({ page }) => {
    await page.goto('/cms/enquiries?show=all', { waitUntil: 'domcontentloaded' });

    const tabs = page.locator('.cms-segmented[aria-label*="form"]');
    await expect(tabs.locator('.cms-segmented__btn')).toHaveCount(3);
    /* All is the default, so the fixture rows and anything the bell links to are on screen. */
    await expect(tabs.getByText('All', { exact: true })).toHaveAttribute('aria-current', 'true');
    await expect(page.locator('.cms-enquiry-row', { hasText: 'Playwright Wizard' })).toBeVisible();
    await expect(page.locator('.cms-enquiry-row', { hasText: 'Playwright Enquirer' })).toBeVisible();

    await tabs.getByText('Agent Finder').click();

    await expect(page).toHaveURL(/source=find_my_agent/);

    /*
     * The chosen tab has to be legible, not merely marked. `aria-current` alone passed while the
     * active segment was navy text on a navy fill — present in the accessibility tree and invisible
     * on screen.
     *
     * Comparing the two colours for inequality was not enough either: they came out as
     * rgb(27,58,105) on rgb(18,41,76), different by a few points and indistinguishable to a reader.
     * So this measures the contrast the way an eye does, and asks for the ratio text is meant to
     * have.
     */
    const contrast = await tabs.locator('.cms-segmented__btn--active').evaluate((el) => {
        const style = getComputedStyle(el);

        const luminance = (colour) => {
            const [r, g, b] = colour.match(/[\d.]+/g).slice(0, 3).map((v) => {
                const channel = Number(v) / 255;

                return channel <= 0.03928 ? channel / 12.92 : ((channel + 0.055) / 1.055) ** 2.4;
            });

            return 0.2126 * r + 0.7152 * g + 0.0722 * b;
        };

        const text = luminance(style.color);
        const fill = luminance(style.backgroundColor);
        const [lighter, darker] = text > fill ? [text, fill] : [fill, text];

        return {
            label: el.textContent.trim(),
            ratio: Number(((lighter + 0.05) / (darker + 0.05)).toFixed(2)),
        };
    });

    expect(contrast.label, 'the active tab must say which one it is').not.toBe('');
    expect(contrast.ratio, `the active tab is unreadable on its own fill (${contrast.ratio}:1)`)
        .toBeGreaterThan(4.5);
    await expect(page.locator('.cms-enquiry-row', { hasText: 'Playwright Wizard' })).toBeVisible();
    await expect(page.locator('.cms-enquiry-row', { hasText: 'Playwright Enquirer' })).toHaveCount(0);

    /* The tab has to survive everything else the screen does, or one is dropped from the address and
       the list silently widens back to every form. */
    await page.locator('#enquiry-show, .cms-toolbar .cms-select').first().selectOption('all');
    await expect(page).toHaveURL(/source=find_my_agent/);
});

test('a wizard enquiry shows what they picked, and none of it can be typed into', async ({ page }) => {
    await page.goto('/cms/enquiries?show=all&source=find_my_agent', { waitUntil: 'domcontentloaded' });
    await page.locator('.cms-enquiry-row', { hasText: 'Playwright Wizard' }).click();

    const modal = page.locator('.cms-modal');
    await expect(modal).toBeVisible();

    await expect(modal.locator('.cms-enquiry-detail__sent')).toContainText('Agent Finder');
    await expect(modal.locator('.cms-enquiry-detail__sent')).toContainText(/AF-\d{4}-\d{5}/);

    const answers = modal.locator('.cms-enquiry-detail__answer');
    await expect(answers).toHaveCount(4);
    await expect(answers.filter({ hasText: 'Property type' })).toContainText('House');
    await expect(answers.filter({ hasText: 'Suburb' })).toContainText('Mosman NSW 2088');

    /* The same guard as above, with the answers present: they are printed, never offered. */
    const editable = await modal.locator('input:not([type=hidden]), textarea').count();

    expect(editable, 'the answers must be printed, not offered as fields').toBe(0);
});
