import { expect, test } from '../fixtures.js';
import { gotoCms } from '../helpers.js';
import { uniqueValue } from '../support/unique.js';

test.describe('Navigation', () => {
    test.beforeEach(async ({ page }) => {
        await gotoCms(page, '/cms/navigation');
    });

    const save = (page) => page.getByRole('button', { name: 'Save menus' });

    /* Not editable here: a row bound to a page follows that page's menu label. */
    const freeLabel = (page) => page.locator('input[placeholder="Wording"]:not([disabled])').first();

    test('the save button is inert until something changes', async ({ page }) => {
        await expect(save(page)).toBeDisabled();

        await freeLabel(page).fill(uniqueValue('Wording'));

        await expect(save(page)).toBeEnabled();
    });

    test('an edited menu label saves and comes back', async ({ page }) => {
        const original = await freeLabel(page).inputValue();
        const wording = uniqueValue('Menu');

        await freeLabel(page).fill(wording);
        await save(page).click();
        await expect(page.getByText('Menus saved')).toBeVisible();

        await page.reload({ waitUntil: 'domcontentloaded' });
        await expect(freeLabel(page)).toHaveValue(wording);

        await freeLabel(page).fill(original);
        await save(page).click();
        await expect(page.getByText('Menus saved')).toBeVisible();
    });

    test('all three menus are on the screen', async ({ page }) => {
        for (const title of ['Header menu', 'Footer columns', 'Small print']) {
            await expect(page.locator('.cms-card__title', { hasText: title }), title).toBeVisible();
        }
    });

    /* Scoped to the header card: `.cms-nav-row` spans all three menus, so "the last row" on the
       screen belongs to Small print, not to the menu just added to. */
    const header = (page) => page.locator('.cms-card', { hasText: 'Header menu' });

    test('an item can be added and removed again', async ({ page }) => {
        const rows = header(page).locator('.cms-nav-row');
        const before = await rows.count();

        await page.getByRole('button', { name: 'Add a menu item' }).click();
        await expect(rows).toHaveCount(before + 1);

        await rows.last().getByRole('button', { name: 'Remove' }).click();
        await expect(rows).toHaveCount(before);
    });

    /* Only the header nests, and only one level: a dropdown holds links, not more dropdowns. */
    test('a header item can hold a dropdown child', async ({ page }) => {
        const rows = header(page).locator('.cms-nav-row');
        const before = await rows.count();

        await page.getByRole('button', { name: 'Add a menu item' }).click();

        const row = rows.last();
        await row.getByRole('button', { name: 'Add an item inside this one' }).click();

        await expect(row.locator('.cms-nav-row__branch')).toHaveCount(1);
        await expect(row).toContainText('Opens a dropdown');

        await row.getByRole('button', { name: 'Remove' }).first().click();
        await expect(rows).toHaveCount(before);
    });

    /**
     * Reordering is pointer-driven with a keyboard path, and the keyboard is the reliable one —
     * focus the handle, Space to grab, arrows to move, Space to drop.
     */
    test('an item can be reordered from the keyboard', async ({ page }) => {
        const labels = () => page.locator('.cms-card', { hasText: 'Header menu' })
            .locator('.cms-nav-row__line input[placeholder="Wording"]')
            .evaluateAll((inputs) => inputs.map((i) => i.value));

        const before = await labels();
        test.skip(before.length < 2, 'needs at least two items to reorder');

        const handle = page.locator('.cms-card', { hasText: 'Header menu' })
            .locator('.cms-drag-handle').first();

        await handle.focus();
        await page.keyboard.press('Space');
        await page.keyboard.press('ArrowDown');
        await page.keyboard.press('Space');

        const after = await labels();

        expect(after[0]).toBe(before[1]);
        expect(after[1]).toBe(before[0]);
        /* Local until saved — this screen holds the order in state and posts it on Save. */
        await expect(save(page)).toBeEnabled();
    });
});
