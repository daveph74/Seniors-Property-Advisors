import { expect, test } from '../fixtures.js';
import { gotoCms } from '../helpers.js';

test.describe('Dashboard', () => {
    test.beforeEach(async ({ page }) => {
        await gotoCms(page, '/cms');
    });

    test('greets whoever is signed in', async ({ page }) => {
        await expect(page.locator('.cms-welcome-title')).toContainText('Hello, Site Administrator');
    });

    /* Six shortcuts, each to the module that does the thing it names. */
    test('every quick action goes where it says', async ({ page }) => {
        const expected = [
            ['Create a page', '/cms/pages'],
            ['Write an article', '/cms/blog'],
            ['Add an FAQ', '/cms/faqs'],
            ['Add a testimonial', '/cms/testimonials'],
            ['Upload media', '/cms/media'],
            ['Edit navigation', '/cms/navigation'],
        ];

        for (const [label, href] of expected) {
            await expect(page.locator('.cms-quick-action', { hasText: label }), label)
                .toHaveAttribute('href', href);
        }

        await page.locator('.cms-quick-action', { hasText: 'Add an FAQ' }).click();
        await expect(page).toHaveURL(/\/cms\/faqs$/);
    });

    /* The figures are the reason this screen exists rather than being a welcome mat. */
    test('the counts are real numbers, not placeholders', async ({ page }) => {
        const tiles = page.locator('.cms-count-tile');

        await expect(tiles.first()).toBeVisible();

        for (const text of await tiles.locator('.cms-count-tile__n').allInnerTexts()) {
            expect(text.trim(), 'a count tile should hold a number').toMatch(/^\d+$/);
        }
    });

    test('the status banner says where publishing stands', async ({ page }) => {
        await expect(page.locator('.cms-status-banner'))
            .toContainText(/Last published|Nothing published yet/);
    });

    test('the card links reach their modules', async ({ page }) => {
        await expect(page.locator('.cms-card__link-action', { hasText: 'View all pages' }))
            .toHaveAttribute('href', '/cms/pages');
        await expect(page.locator('.cms-card__link-action', { hasText: 'See all' }))
            .toHaveAttribute('href', '/cms/activity');
    });

    test('the live site opens in its own tab', async ({ page }) => {
        const live = page.getByRole('link', { name: 'View the live website' });

        await expect(live).toHaveAttribute('href', '/');
        await expect(live).toHaveAttribute('target', '_blank');
    });
});
