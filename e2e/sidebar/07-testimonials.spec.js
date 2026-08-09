import { expect, test } from '../fixtures.js';
import { cmsInput, gotoCms, settled, toast } from '../helpers.js';
import { unique } from '../support/unique.js';

test.describe('Testimonials', () => {
    test.beforeEach(async ({ page }) => {
        await gotoCms(page, '/cms/testimonials');
    });

    const card = (page, name) => page.locator('.cms-testimonial-card', { hasText: name });

    async function add(page, name) {
        await page.getByRole('button', { name: 'Add a testimonial' }).click();

        const modal = page.locator('.cms-modal');
        await expect(modal.getByRole('heading', { name: 'Add a testimonial' })).toBeVisible();
        await settled(modal);

        /* Positional rather than by label. The modal's own order is stable and short — name,
           quote, heading, location — where the label lookup proved slow to resolve once the whole
           suite was running and the modal was still settling. */
        await modal.locator('input.cms-input').first().fill(name);
        await modal.locator('textarea.cms-textarea').first().fill('They were patient and explained everything.');
        await cmsInput(modal, 'Location or suburb').fill('Hawthorn');

        await expect(modal.getByRole('button', { name: 'Save', exact: true })).toBeEnabled();
        await modal.getByRole('button', { name: 'Save', exact: true }).click();

        await expect(toast(page)).toContainText('Testimonial saved');
        await expect(card(page, name)).toBeVisible();
    }

    async function remove(page, name) {
        if (await card(page, name).count() === 0) return;

        await card(page, name).getByRole('button', { name: 'Delete' }).click();
        await settled(page.locator('.cms-modal'));
        await page.locator('.cms-modal').getByRole('button', { name: 'Delete', exact: true }).click();
        await expect(card(page, name)).toHaveCount(0);
    }

    test('one is added, edited and deleted', async ({ page }) => {
        const name = unique('Rachel');

        await add(page, name);
        await expect(card(page, name)).toContainText('Hawthorn');

        await card(page, name).getByRole('button', { name: 'Edit' }).click();
        await settled(page.locator('.cms-modal'));
        await cmsInput(page.locator('.cms-modal'), 'Location or suburb').fill('Kew');
        await page.locator('.cms-modal').getByRole('button', { name: 'Save', exact: true }).click();

        await expect(card(page, name)).toContainText('Kew');

        await remove(page, name);
        await expect(toast(page)).toContainText('Testimonial deleted');
    });

    /**
     * Scope §7 closes with a constraint rather than a field: a name and a photograph may only be
     * published where the client has given permission. So the switches are dead until permission
     * is recorded — the ordering *is* the feature, and this is the test that pins it.
     */
    test('nothing can be published until permission is recorded', async ({ page }) => {
        const name = unique('Consent');

        await add(page, name);

        const row = card(page, name);
        await expect(row).toContainText('Permission needed');
        await expect(row.getByRole('switch', { name: 'Showing on the website' })).toBeDisabled();
        await expect(row.getByRole('switch', { name: 'Featured on the home page' })).toBeDisabled();

        await row.getByRole('button', { name: 'Record permission' }).click();

        const modal = page.locator('.cms-modal');
        await expect(modal).toContainText('Confirm the client has agreed');
        await modal.getByRole('button', { name: 'Confirm permission' }).click();

        await expect(toast(page)).toContainText('Permission recorded');
        await expect(row).toContainText('Permission recorded');
        await expect(row.getByRole('switch', { name: 'Showing on the website' })).toBeEnabled();

        await remove(page, name);
    });

    /* Withdrawing takes it off the website and unfeatures it in the same move. */
    test('withdrawing permission unpublishes it', async ({ page }) => {
        const name = unique('Withdrawn');

        await add(page, name);
        const row = card(page, name);

        await row.getByRole('button', { name: 'Record permission' }).click();
        await page.locator('.cms-modal').getByRole('button', { name: 'Confirm permission' }).click();
        await expect(toast(page)).toContainText('Permission recorded');

        await row.getByRole('switch', { name: 'Showing on the website' }).click();
        await row.getByRole('switch', { name: 'Featured on the home page' }).click();
        await expect(row).toContainText('Featured');

        await row.getByRole('button', { name: 'Withdraw permission' }).click();
        await expect(page.locator('.cms-modal')).toContainText('off the website straight away');
        await page.locator('.cms-modal').getByRole('button', { name: 'Withdraw' }).click();

        await expect(toast(page)).toContainText('Permission withdrawn');
        await expect(row).toContainText('Permission needed');
        await expect(row.getByRole('switch', { name: 'Showing on the website' })).toBeDisabled();

        await remove(page, name);
    });

    test('the filters narrow the list', async ({ page }) => {
        const name = unique('Filtered');

        await add(page, name);

        await page.locator('.cms-toolbar select').selectOption({ label: 'Waiting on permission' });
        await expect(card(page, name)).toBeVisible();

        await page.locator('.cms-toolbar select').selectOption({ label: 'Featured only' });
        await expect(card(page, name)).toHaveCount(0);

        await page.locator('.cms-toolbar select').selectOption({ label: 'All testimonials' });
        await remove(page, name);
    });

    test('search finds one by name', async ({ page }) => {
        const name = unique('Searchable');

        await add(page, name);

        await page.getByPlaceholder('Search testimonials').fill(name);
        await expect(card(page, name)).toBeVisible();
        await expect(page.locator('.cms-testimonial-card')).toHaveCount(1);

        await page.getByPlaceholder('Search testimonials').fill('');
        await remove(page, name);
    });
});
