import { expect } from '@playwright/test';
import { clickThrough } from '../helpers.js';

/**
 * The page builder, wrapped.
 *
 * It is the hardest surface in the application to drive and every block test goes through it, so
 * the awkward parts live here once rather than in fifty specs.
 *
 * Two things make it awkward, both worth knowing before changing anything below:
 *
 * 1. **Settings fields have no `id`, `name` or associated `label`** — only visible label text. So a
 *    field is found by filtering `.cms-field` on an *exact* text match. Substring matching is not
 *    an option: "Heading", "Highlighted heading" and "Heading level" all contain each other.
 * 2. **The canvas is an `about:blank` iframe** React portals into, so anything drawn on the page
 *    itself is behind `frameLocator`.
 */
export const TABS = ['Content', 'Layout', 'Style', 'Responsive', 'Advanced'];

export function canvas(page) {
    return page.frameLocator('.cms-canvas-iframe');
}

/** Opens a page in the builder by its title on the pages list. */
export async function openBuilder(page, title) {
    await page.goto('/cms/pages', { waitUntil: 'domcontentloaded' });
    await expect(page.locator('.cms-shell')).toBeVisible();

    await clickThrough(
        page,
        page.getByRole('button', { name: title, exact: true }).first(),
        /\/cms\/pages\/\d+\/edit$/,
        `the builder for "${title}"`,
    );

    await expect(page.getByRole('button', { name: 'Save draft' })).toBeVisible();
    await expect(canvas(page).locator('body')).not.toBeEmpty();
}

/** Clicks a component in the library, which appends it at page level and selects it. */
export async function addBlock(page, label) {
    const before = await canvas(page).locator('.cms-block').count();

    await page.locator(`.cms-component-card[title="${label}"]`).click();

    await expect(canvas(page).locator('.cms-block')).toHaveCount(before + 1);
    /* The tag is drawn on the block, so it lives in the canvas rather than on the page. */
    await expect(canvas(page).locator('.cms-block__label-tag').first()).toBeVisible();
}

/** The block currently selected, as it appears in the canvas. */
export function selectedBlock(page) {
    return canvas(page).locator('.cms-block--selected').first();
}

/**
 * Opens one of the settings accordions. Only Content is open on arrival — the others' inputs do
 * not exist in the DOM at all until their heading is clicked, so this is not optional.
 */
export async function openTab(page, name) {
    const head = page.locator('.cms-accordion__head', { hasText: name }).first();

    if ((await head.getAttribute('aria-expanded')) !== 'true') {
        await head.click();
    }

    await expect(head).toHaveAttribute('aria-expanded', 'true');
}

/**
 * A settings field, by its visible label. `within` scopes to a `.cms-fieldgroup` first, which is
 * how the hero's three "Headline" fields are told apart.
 */
export function field(page, label, { within = null } = {}) {
    const scope = within
        ? page.locator('.cms-fieldgroup').filter({ hasText: within })
        : page.locator('.cms-settings-panel, .cms-panel, body');

    return scope
        .locator('.cms-field')
        .filter({ has: page.getByText(label, { exact: true }) })
        .first();
}

/** The input inside a field, whatever kind it is. */
export function input(page, label, options) {
    return field(page, label, options).locator('input, textarea, select').first();
}

export async function fillField(page, label, value, options) {
    const control = input(page, label, options);

    await control.fill(value);
    await control.blur();
}

/** A repeater collection, addressed by the title its header shows. */
export function repeater(page, title) {
    return page.locator('.cms-rep').filter({ hasText: title }).first();
}

export async function saveDraft(page) {
    await page.getByRole('button', { name: 'Save draft' }).click();
    await expect(page.getByText('Draft saved')).toBeVisible();
}

export async function publish(page) {
    await page.getByRole('button', { name: 'Publish', exact: true }).click();
    await expect(page.getByText('Publish this page?')).toBeVisible();
    await page.getByRole('button', { name: 'Publish now' }).click();
}

/**
 * Removes the selected block through its own toolbar. Used by cleanup, so a spec leaves the
 * fixture page as it found it.
 */
export async function deleteSelected(page) {
    const before = await canvas(page).locator('.cms-block').count();

    await toolbar(page, 'Delete');

    await expect(canvas(page).locator('.cms-block')).toHaveCount(before - 1);
}

/**
 * Presses a button on the selected block's toolbar.
 *
 * `force` is not laziness here. The canvas fades in, re-measures its own height and is drawn under
 * a CSS `scale()`, so a toolbar button is almost never "stable" by Playwright's definition — it
 * waits for two animation frames at the same position and the canvas keeps moving underneath it.
 * The button is resolved, visible and enabled before this runs; what is skipped is only the
 * did-it-stop-moving check.
 */
export async function toolbar(page, title) {
    const button = selectedBlock(page).locator(`[title="${title}"]`).first();

    await expect(button).toBeVisible();

    /* Dispatched rather than clicked. A real click is delivered to whatever occupies the point,
       and the toolbar floats over a canvas that is scaled, fading and re-measuring its own height
       — so the coordinates are a moving target even once the button itself is visible. */
    await button.dispatchEvent('click');
}

/**
 * Saves, reloads, and returns — the only way to prove a value reached the database rather than
 * merely reaching React state.
 */
export async function saveAndReload(page) {
    await saveDraft(page);
    await page.reload({ waitUntil: 'domcontentloaded' });
    await expect(page.getByRole('button', { name: 'Save draft' })).toBeVisible();
    await expect(canvas(page).locator('body')).not.toBeEmpty();
}

/** Selects a block in the canvas by the label its tag shows, so a reload can find it again. */
export async function selectBlock(page, label) {
    const block = canvas(page).locator('.cms-block').filter({ hasText: label }).first();

    await expect(block).toBeVisible();
    /* Dispatched, for the reason given on `toolbar`: a real click waits for a stability that a
       scaled, animating canvas never quite reaches. */
    await block.dispatchEvent('click');
    /* The tag is drawn on the block, so it lives in the canvas rather than on the page. */
    await expect(canvas(page).locator('.cms-block__label-tag').first()).toBeVisible();
}

/** For blocks that render nothing findable as text — an image on its own, say. */
export async function selectLastBlock(page) {
    const block = canvas(page).locator('.cms-block').last();

    await expect(block).toBeVisible();
    await block.dispatchEvent('click');
    await expect(canvas(page).locator('.cms-block__label-tag').first()).toBeVisible();
}
