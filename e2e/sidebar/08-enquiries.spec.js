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
