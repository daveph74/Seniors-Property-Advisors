import { expect, test } from '../fixtures.js';
import * as B from '../support/builder.js';
import { dragLibraryItem } from '../support/dragShim.js';
import { uniqueValue } from '../support/unique.js';

/** The builder as a tool, rather than as a way of editing one block. */
test.describe('Pages · Builder', () => {
    const FIXTURE = 'Contact';

    test.beforeEach(async ({ page }) => {
        await B.openBuilder(page, FIXTURE);
    });

    test('a draft saves and the state text follows it', async ({ page }) => {
        await expect(page.locator('.cms-builder-topbar__save')).toContainText('All changes saved');

        await B.addBlock(page, 'Heading');
        await expect(page.locator('.cms-builder-topbar__save')).toContainText('Unsaved changes');

        await B.saveDraft(page);
        await expect(page.locator('.cms-builder-topbar__save')).toContainText('All changes saved');

        await B.deleteSelected(page);
        await B.saveDraft(page);
    });

    test('undo and redo walk a change backwards and forwards', async ({ page }) => {
        const before = await B.canvas(page).locator('.cms-block').count();

        await B.addBlock(page, 'Heading');
        await expect(B.canvas(page).locator('.cms-block')).toHaveCount(before + 1);

        await page.getByTitle('Undo').click();
        await expect(B.canvas(page).locator('.cms-block')).toHaveCount(before);

        await page.getByTitle('Redo').click();
        await expect(B.canvas(page).locator('.cms-block')).toHaveCount(before + 1);

        await page.getByTitle('Undo').click();
        await expect(B.canvas(page).locator('.cms-block')).toHaveCount(before);
        await B.saveDraft(page);
    });

    test('the keyboard undoes as well as the button', async ({ page }) => {
        const before = await B.canvas(page).locator('.cms-block').count();

        await B.addBlock(page, 'Heading');
        await page.keyboard.press('Control+z');

        await expect(B.canvas(page).locator('.cms-block')).toHaveCount(before);
        await B.saveDraft(page);
    });

    test('undoing nothing says so rather than doing something', async ({ page }) => {
        while (await page.getByTitle('Undo').isEnabled()) {
            await page.getByTitle('Undo').click();
        }

        await expect(page.getByTitle('Undo')).toBeDisabled();
    });

    /**
     * Layers is the reliable way to reorder — the canvas itself is a drag surface.
     *
     * Move up is scoped to a block's own siblings, and the panel lists nested children too, so
     * this works on the whole listing rather than assuming row 1 sits beside row 0.
     */
    test('a block moves up and down from the Layers panel', async ({ page }) => {
        await page.getByRole('button', { name: 'Layers' }).click();

        const labels = () => page.locator('.cms-layer-row__label').allInnerTexts();
        const before = await labels();

        test.skip(before.length < 2, 'needs two blocks to reorder');

        const second = page.locator('.cms-layer-row').nth(1);
        await second.getByTitle('Move up').click();

        await expect
            .poll(async () => (await labels()).join('|'), { message: 'the order should change' })
            .not.toBe(before.join('|'));

        /* And back, so the fixture page is left as it was found. */
        await page.locator('.cms-layer-row').nth(0).getByTitle('Move down').click();

        await expect.poll(async () => (await labels()).join('|')).toBe(before.join('|'));
    });

    test('the first block cannot be moved above itself', async ({ page }) => {
        await page.getByRole('button', { name: 'Layers' }).click();

        await expect(page.locator('.cms-layer-row').first().getByTitle('Move up')).toBeDisabled();
        await expect(page.locator('.cms-layer-row').last().getByTitle('Move down')).toBeDisabled();
    });

    /* The exact widths belong to the application, not to this test — what matters is that each
       button changes the canvas to the device it names, at some width. */
    test('the device buttons change what the canvas reports', async ({ page }) => {
        const seen = new Set();

        for (const device of ['Tablet', 'Mobile', 'Desktop']) {
            await page.getByTitle(device).click();

            const caption = page.locator('.cms-canvas-caption');
            await expect(caption).toContainText(device);
            await expect(caption).toContainText(/\d+px/);

            seen.add((await caption.innerText()).match(/(\d+)px/)[1]);
        }

        expect(seen.size, 'each device should be a different width').toBe(3);
    });

    test('a block can be duplicated and hidden from its own toolbar', async ({ page }) => {
        const before = await B.canvas(page).locator('.cms-block').count();

        await B.addBlock(page, 'Heading');
        await B.toolbar(page, 'Duplicate');

        await expect(B.canvas(page).locator('.cms-block')).toHaveCount(before + 2);

        await B.toolbar(page, 'Hide on page');
        await expect(page.getByText(/hidden on this page/)).toBeVisible();

        await B.deleteSelected(page);
        await B.canvas(page).locator('.cms-block').last().click();
        await B.deleteSelected(page);
        await B.saveDraft(page);
    });

    /* The anchor is what a menu link points at, so it is slugified rather than taken as typed. */
    test('an anchor id is slugified', async ({ page }) => {
        await B.addBlock(page, 'Heading');
        await B.openTab(page, 'Advanced');

        await B.fillField(page, 'Anchor ID', 'How It Works Here');

        await expect(B.input(page, 'Anchor ID')).toHaveValue('how-it-works-here');

        await B.deleteSelected(page);
        await B.saveDraft(page);
    });

    test('a block can be renamed in the panel', async ({ page }) => {
        await B.addBlock(page, 'Heading');
        await B.openTab(page, 'Advanced');

        const label = uniqueValue('Renamed');
        await B.fillField(page, 'Component label', label);

        await page.getByRole('button', { name: 'Layers' }).click();
        await expect(page.locator('.cms-layer-row__label', { hasText: label })).toBeVisible();

        await B.canvas(page).locator('.cms-block').last().click();
        await B.deleteSelected(page);
        await B.saveDraft(page);
    });

    test('publishing offers a summary of what changes', async ({ page }) => {
        await B.addBlock(page, 'Heading');
        await B.fillField(page, 'Heading', uniqueValue('Published heading'));
        await B.saveDraft(page);

        await page.getByRole('button', { name: 'Publish', exact: true }).click();

        const modal = page.locator('.cms-modal');
        await expect(modal).toContainText('Publish this page?');
        await expect(modal.getByRole('button', { name: 'Publish now' })).toBeEnabled();

        await modal.getByRole('button', { name: 'Publish now' }).click();
        await expect(page.getByText(/is now live/)).toBeVisible();

        await B.canvas(page).locator('.cms-block').last().click();
        await B.deleteSelected(page);
        await B.saveDraft(page);
        await page.getByRole('button', { name: 'Publish', exact: true }).click();
        await page.locator('.cms-modal').getByRole('button', { name: 'Publish now' }).click();
    });

    test('the history drawer lists what has been published', async ({ page }) => {
        await page.getByRole('button', { name: 'History' }).click();

        await expect(page.locator('.cms-drawer, .cms-history').first()).toBeVisible();
    });

    /**
     * The only way to put a block inside another is to drag it there, and the canvas uses the
     * native drag API — so this goes through the shim. See e2e/support/dragShim.js for why a
     * passing test here is weaker evidence than a passing click test.
     */
    test('a block can be dropped inside a section', async ({ page }) => {
        /* A section arrives with a row and an empty column, so it adds three blocks, not one. */
        await page.locator('.cms-component-card[title="Section"]').click();
        await expect(B.canvas(page).locator('.cms-nest-drop')).toHaveCount(1);

        const before = await B.canvas(page).locator('.cms-block').count();

        await dragLibraryItem(page, 'Heading', '.cms-nest-drop');

        /* The empty zone goes because the column is no longer empty — which is how we know the
           block landed inside it rather than at page level. */
        await expect(B.canvas(page).locator('.cms-nest-drop')).toHaveCount(0);
        await expect(B.canvas(page).locator('.cms-block')).toHaveCount(before + 1);

        /* And it is still nested after a round trip through the database. */
        await B.saveAndReload(page);
        await expect(B.canvas(page).locator('.cms-nest-drop')).toHaveCount(0);
        await expect(B.canvas(page).locator('.cms-block')).toHaveCount(before + 1);

        /* Cleanup: select the section from Layers — deleting it takes its row, column and the
           heading with it, which is also worth knowing. */
        await page.getByRole('button', { name: 'Layers' }).click();
        await page.locator('.cms-layer-row', { hasText: 'Section' }).last().click();
        await B.toolbar(page, 'Delete');

        /* The section and everything it held are gone — the count is the app's business, the
           absence of the nesting is the test's. */
        await expect(B.canvas(page).locator('.cms-nest-drop')).toHaveCount(0);
        await expect(B.canvas(page).locator('.cms-block')).toHaveCount(before - 3);
        await B.saveDraft(page);
    });
});
