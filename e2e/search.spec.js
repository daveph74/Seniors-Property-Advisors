import { expect, test, withImages } from './fixtures.js';
import { openPalette } from './helpers.js';

/** The header palette. It answers over fetch, so nothing here is an Inertia visit until you pick one. */
test('the palette opens on the keyboard and finds across content types', async ({ page }) => {
    await page.goto('/cms', { waitUntil: 'domcontentloaded' });
    await openPalette(page);

    /* Under two characters searches nothing, and shows nothing rather than an empty box. */
    await page.locator('.cms-palette__input').fill('a');
    await expect(page.locator('.cms-palette__results')).toHaveCount(0);

    await page.locator('.cms-palette__input').fill('agent');
    await expect(page.locator('.cms-palette__group-label').first()).toBeVisible();

    expect(await page.locator('.cms-palette__group-label').allInnerTexts()).toContain('PAGES');

    await page.locator('.cms-palette__input').press('Escape');
    await expect(page.locator('.cms-palette')).toHaveCount(0);
});

/**
 * A page's builder link is built from `cms_id`, not the primary key — a link of the right shape
 * built from the wrong number still 404s, which is why this follows it rather than reading it.
 */
test('choosing a page from the palette opens its builder', async ({ page }) => {
    await page.goto('/cms', { waitUntil: 'domcontentloaded' });
    await openPalette(page);

    await page.locator('.cms-palette__input').fill('compare');
    await expect(page.locator('.cms-palette__result').first()).toBeVisible();
    await page.locator('.cms-palette__result').first().click();

    await expect(page).toHaveURL(/\/cms\/pages\/\d+\/edit/);
    await expect(page.getByRole('button', { name: 'Save draft' })).toBeVisible();
});

test('a media result carries a thumbnail and opens that image', async ({ page }) => {
    /* The one test that is about the picture, so the one that pays for fetching it. */
    await withImages(page);

    await page.goto('/cms', { waitUntil: 'domcontentloaded' });
    await openPalette(page);

    await page.locator('.cms-palette__input').fill('rachel');

    const media = page.locator('.cms-palette__group', { hasText: 'MEDIA' });
    await expect(media).toBeVisible();

    const thumb = media.locator('.cms-palette__thumb--img').first();
    await expect(thumb).toBeVisible();
    await expect(thumb).toHaveJSProperty('complete', true);

    /* The row says what the file is, not what its alt text says it depicts. */
    await expect(media.locator('.cms-palette__result-meta').first()).toContainText(/JPG|PNG|WEBP|GIF/);

    await media.locator('.cms-palette__result').first().click();

    await expect(page).toHaveURL(/\/cms\/media\?selected=\d+/);
    await expect(page.locator('.cms-media-side')).toBeVisible();
});

test('nothing matching says so', async ({ page }) => {
    await page.goto('/cms', { waitUntil: 'domcontentloaded' });
    await openPalette(page);

    await page.locator('.cms-palette__input').fill('zzzzqqq');

    await expect(page.locator('.cms-palette__hint')).toContainText('Nothing matches');
});
