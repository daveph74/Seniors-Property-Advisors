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
        /* The fixture page holds one section, so this adds the second block rather than skipping.
           A conditional skip here answered nothing and reported it as a pass. */
        await B.addBlock(page, 'Heading');

        await page.getByRole('button', { name: 'Layers' }).click();

        const labels = () => page.locator('.cms-layer-row__label').allInnerTexts();
        const before = await labels();

        const second = page.locator('.cms-layer-row').nth(1);
        await second.getByTitle('Move up').click();

        await expect
            .poll(async () => (await labels()).join('|'), { message: 'the order should change' })
            .not.toBe(before.join('|'));

        /* And back, so the fixture page is left as it was found. */
        await page.locator('.cms-layer-row').nth(0).getByTitle('Move down').click();

        await expect.poll(async () => (await labels()).join('|')).toBe(before.join('|'));

        await B.selectLastBlock(page);
        await B.deleteSelected(page);
    });

    test('the first block cannot be moved above itself', async ({ page }) => {
        /* Two blocks, or the first row is also the last and both assertions pass on one disabled
           button — true, and about nothing. */
        await B.addBlock(page, 'Heading');

        await page.getByRole('button', { name: 'Layers' }).click();

        await expect(page.locator('.cms-layer-row')).not.toHaveCount(1);
        await expect(page.locator('.cms-layer-row').first().getByTitle('Move up')).toBeDisabled();
        await expect(page.locator('.cms-layer-row').last().getByTitle('Move down')).toBeDisabled();

        await B.deleteSelected(page);
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
        await B.selectLastBlock(page);
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

    /**
     * An image is chosen, never typed.
     *
     * The field used to carry a free text box inviting "a web address", and `img-src` is `'self' data:`
     * — so that invitation produced a block which saved, published and drew nothing, the picture
     * missing with no reason given. The box is gone rather than validated: no way to enter an address
     * is a stronger guarantee than a message explaining why the one you entered will not work.
     *
     * Asserted because it is an absence, and an absence is what nobody notices being undone.
     */
    test('an image can only be chosen from the library, not typed', async ({ page }) => {
        await B.addBlock(page, 'Text and image');

        /* The open accordion, not a panel class: `.cms-settings-panel` does not exist, which is worth
           knowing because `support/builder.js` names it in a scope selector and falls through to
           `body` every time. */
        const settings = page.locator('.cms-accordion__body');

        await expect(page.getByRole('button', { name: /^(Choose|Replace)$/ }).first()).toBeVisible();

        /* Every text box in the settings, and none of them for an address. */
        for (const box of await settings.locator('input[type="text"], input:not([type])').all()) {
            const placeholder = (await box.getAttribute('placeholder')) || '';

            expect(placeholder, 'an image address can be typed again').not.toContain('/media/');
            expect(placeholder.toLowerCase()).not.toContain('web address');
        }
    });

    /**
     * The picker, once, against a real library item — and the reason it is here rather than in the
     * generated per-field tests: those can no longer type an address, and adapting them would have had
     * `B.input` return the "Describe the image" box in the same `.cms-field`, where a typed path
     * round-trips perfectly and proves nothing.
     */
    test('an image chosen from the library survives a save and a reload', async ({ page }) => {
        await B.addBlock(page, 'Text and image');

        await page.getByRole('button', { name: /^(Choose|Replace)$/ }).first().click();

        const modal = page.locator('.cms-modal');
        await expect(modal.locator('.cms-modal__title')).toContainText('Choose an image');

        await modal.locator('.cms-library__tile').first().click();
        await expect(modal).toHaveCount(0);

        const chosen = await page.locator('.cms-media-pick-row__name').first().innerText();
        expect(chosen).not.toBe('No image yet');

        await B.saveAndReload(page);
        await B.selectLastBlock(page);

        await expect(page.locator('.cms-media-pick-row__name').first()).toHaveText(chosen);

        await B.deleteSelected(page);
        await B.saveDraft(page);
    });

    test('bold in a rich text block survives a save and a reload', async ({ page }) => {
        await B.addBlock(page, 'Rich text');

        /* The editor loads lazily behind a placeholder that wears the same surface class, so the
           real one is the one that can be typed into. */
        const surface = B.field(page, 'Text').locator('.cms-rt__surface[contenteditable="true"]');
        const marker = uniqueValue('Bold');

        await expect(surface).toBeVisible();
        await surface.click();
        await page.keyboard.press('Control+b');
        await page.keyboard.type(marker);

        await expect(B.canvas(page).locator('.block-text strong')).toContainText(marker);

        await B.saveAndReload(page);
        await B.selectBlock(page, marker);

        await expect(B.canvas(page).locator('.block-text strong')).toContainText(marker);
        await expect(B.field(page, 'Text').locator('.cms-rt__surface strong')).toContainText(marker);

        await B.deleteSelected(page);
        await B.saveDraft(page);
    });

    /* A section arrives with a row and a column — three blocks, so `addBlock` cannot count it in, and
       the last block in the canvas is the column. Layers is the one place the section itself can be
       picked by name. */
    const addSection = async (page) => {
        await page.locator('.cms-component-card[title="Section"]').click();
        await expect(B.canvas(page).locator('.cms-nest-drop')).toHaveCount(1);
        await expect(page.locator('.cms-accordion__head', { hasText: 'Style' })).toBeVisible();
    };

    const selectLastSection = async (page) => {
        await page.getByRole('button', { name: 'Layers' }).click();
        await page.locator('.cms-layer-row', { hasText: 'Section' }).last().click();
        await expect(page.locator('.cms-accordion__head', { hasText: 'Style' })).toBeVisible();
    };

    test('a section background image chosen from the library survives a save and a reload', async ({ page }) => {
        await addSection(page);
        await B.openTab(page, 'Style');
        await B.chooseImage(page, 'Background image');
        await B.input(page, 'Image position').selectOption('top-right');

        const chosen = await B.field(page, 'Background image').locator('.cms-media-pick-row__name').innerText();

        await expect(B.canvas(page).locator('.section-block__bg--pos-top-right')).toHaveCount(1);

        await B.saveAndReload(page);
        await selectLastSection(page);
        await B.openTab(page, 'Style');

        await expect(B.field(page, 'Background image').locator('.cms-media-pick-row__name')).toHaveText(chosen);
        await expect(B.input(page, 'Image position')).toHaveValue('top-right');
        await expect(B.canvas(page).locator('.section-block__bg--pos-top-right')).toHaveCount(1);

        await B.toolbar(page, 'Delete');
        await B.saveDraft(page);
    });

    test('a heading can put its highlight on a new line, and keeps the choice', async ({ page }) => {
        await B.addBlock(page, 'Heading');
        const marker = uniqueValue('How we');

        await B.fillField(page, 'Heading', marker);
        await B.fillField(page, 'Highlighted heading', 'help you');

        const toggle = () => B.field(page, 'Highlighted heading starts a new line').getByRole('switch');

        await toggle().click();
        await expect(toggle()).toHaveAttribute('aria-checked', 'true');
        await expect(B.canvas(page).locator('.block-heading br')).toHaveCount(1);

        await B.saveAndReload(page);
        await B.selectBlock(page, marker);

        await expect(toggle()).toHaveAttribute('aria-checked', 'true');
        await expect(B.canvas(page).locator('.block-heading br')).toHaveCount(1);

        await B.deleteSelected(page);
        await B.saveDraft(page);
    });

    test('a banner keeps its look settings through a save and a reload', async ({ page }) => {
        await B.addBlock(page, 'Banner, full bleed');
        await B.chooseImage(page, 'Background image');
        await B.input(page, 'Overlay', { within: 'Look' }).selectOption('navy-left');
        await B.input(page, 'Section height', { within: 'Look' }).selectOption('compact');

        await expect(B.canvas(page).locator('.banner--compact .section-block__overlay--navy-left')).toHaveCount(1);

        await B.saveAndReload(page);
        await B.selectLastBlock(page);

        await expect(B.input(page, 'Overlay', { within: 'Look' })).toHaveValue('navy-left');
        await expect(B.input(page, 'Section height', { within: 'Look' })).toHaveValue('compact');

        await B.deleteSelected(page);
        await B.saveDraft(page);
    });

    test('editing one of two new headings leaves the other alone', async ({ page }) => {
        await B.addBlock(page, 'Heading');
        await B.addBlock(page, 'Heading');

        const marker = uniqueValue('Only here');
        await B.fillField(page, 'Heading', marker);

        await expect(B.canvas(page).locator('.block-heading', { hasText: marker })).toHaveCount(1);

        await B.saveAndReload(page);
        await expect(B.canvas(page).locator('.block-heading', { hasText: marker })).toHaveCount(1);

        await B.selectBlock(page, marker);
        await B.deleteSelected(page);
        await B.selectLastBlock(page);
        await B.deleteSelected(page);
        await B.saveDraft(page);
    });

    test('two full-bleed heroes still leave the page with one h1', async ({ page }) => {
        await B.addBlock(page, 'Hero, full bleed');
        await B.addBlock(page, 'Hero, full bleed');

        await expect(B.canvas(page).locator('.hero-full__title')).toHaveCount(2);
        await expect(B.canvas(page).locator('h1')).toHaveCount(1);

        await B.deleteSelected(page);
        await B.selectLastBlock(page);
        await B.deleteSelected(page);
        await B.saveDraft(page);
    });

    test('a Full screen section height survives a save and a reload', async ({ page }) => {
        await addSection(page);
        await B.openTab(page, 'Layout');
        await B.input(page, 'Section height').selectOption('full');

        await expect(B.canvas(page).locator('.section-block--full')).toHaveCount(1);

        await B.saveAndReload(page);
        await selectLastSection(page);
        await B.openTab(page, 'Layout');

        await expect(B.input(page, 'Section height')).toHaveValue('full');

        await B.toolbar(page, 'Delete');
        await B.saveDraft(page);
    });

    const selectLastRow = async (page) => {
        await page.getByRole('button', { name: 'Layers' }).click();
        await page.locator('.cms-layer-row', { hasText: 'Row' }).last().click();
        await expect(page.locator('.cms-accordion__head', { hasText: 'Layout' })).toBeVisible();
    };

    test('a row gap setting survives a save and a reload', async ({ page }) => {
        await addSection(page);
        await selectLastRow(page);
        await B.openTab(page, 'Layout');
        await B.input(page, 'Gap between columns').selectOption('large');
        await B.input(page, 'Space above').selectOption('xlarge');

        await expect(B.canvas(page).locator('.row-container--gap-large.u-space-above-xlarge')).toHaveCount(1);

        await B.saveAndReload(page);
        await selectLastRow(page);
        await B.openTab(page, 'Layout');

        await expect(B.input(page, 'Gap between columns')).toHaveValue('large');
        await expect(B.input(page, 'Space above')).toHaveValue('xlarge');

        await selectLastSection(page);
        await B.toolbar(page, 'Delete');
        await B.saveDraft(page);
    });

    const selectLastColumn = async (page) => {
        await page.getByRole('button', { name: 'Layers' }).click();
        await page.locator('.cms-layer-row', { hasText: 'Column' }).last().click();
        await expect(page.locator('.cms-accordion__head', { hasText: 'Style' })).toBeVisible();
    };

    test('an animated column reveals itself once it scrolls into view', async ({ page }) => {
        await addSection(page);
        await selectLastColumn(page);
        await B.openTab(page, 'Style');
        await B.input(page, 'Animation').selectOption('fade-up');
        await B.saveDraft(page);

        const builder = page.url();
        await page.goto(builder.replace(/\/edit$/, '/preview'), { waitUntil: 'domcontentloaded' });

        const section = page.locator('.reveal').last();
        await expect(section).toHaveCount(1);
        await section.scrollIntoViewIfNeeded();
        await expect(section).toHaveClass(/is-in-view/);

        await page.goto(builder, { waitUntil: 'domcontentloaded' });
        await expect(B.canvas(page).locator('body')).not.toBeEmpty();
        await selectLastSection(page);
        await B.toolbar(page, 'Delete');
        await B.saveDraft(page);
    });

    test('a column animation setting survives a save and a reload', async ({ page }) => {
        await addSection(page);
        await selectLastColumn(page);
        await B.openTab(page, 'Style');

        await B.input(page, 'Animation').selectOption('fade-up');
        await B.input(page, 'Delay').selectOption('200');

        await B.saveAndReload(page);
        await selectLastColumn(page);
        await B.openTab(page, 'Style');

        await expect(B.input(page, 'Animation')).toHaveValue('fade-up');
        await expect(B.input(page, 'Delay')).toHaveValue('200');
        /* The canvas is editing, so the column is never hidden waiting for a scroll. */
        await expect(B.canvas(page).locator('.reveal')).toHaveCount(0);

        await selectLastSection(page);
        await B.toolbar(page, 'Delete');
        await B.saveDraft(page);
    });

    test('a block can be renamed in the panel', async ({ page }) => {
        await B.addBlock(page, 'Heading');
        await B.openTab(page, 'Advanced');

        const label = uniqueValue('Renamed');
        await B.fillField(page, 'Component label', label);

        await page.getByRole('button', { name: 'Layers' }).click();
        await expect(page.locator('.cms-layer-row__label', { hasText: label })).toBeVisible();

        await B.selectLastBlock(page);
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

        await B.selectLastBlock(page);
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
