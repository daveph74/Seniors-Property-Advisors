import { expect, test } from '../fixtures.js';
import { gotoCms } from '../helpers.js';
import { unique } from '../support/unique.js';

test.describe('Recently deleted', () => {
    /** Deletes an FAQ so there is something in the bin to act on. */
    async function binAQuestion(page, question) {
        await gotoCms(page, '/cms/faqs');
        await page.getByRole('button', { name: 'Add a question' }).click();
        await page.locator('.cms-modal input, .cms-modal textarea').first().fill(question);
        await page.locator('.cms-modal textarea').first().fill('Destined for the bin.');
        await page.getByRole('button', { name: 'Save', exact: true }).click();

        const row = page.locator('.cms-faq-row', { hasText: question });
        await expect(row).toBeVisible();

        await row.getByRole('button', { name: 'Delete' }).click();
        await page.locator('.cms-modal').getByRole('button', { name: /^Delete/ }).click();
        await expect(row).toHaveCount(0);
    }

    test('something deleted waits here and can be restored', async ({ page }) => {
        const question = unique('Restore me');

        await binAQuestion(page, question);
        await gotoCms(page, '/cms/deleted');

        const row = page.locator('.cms-faq-row', { hasText: question });
        await expect(row).toBeVisible();
        await expect(row).toContainText('question');

        await row.getByRole('button', { name: 'Restore' }).click();
        await expect(page.getByText(/restored/)).toBeVisible();
        await expect(row).toHaveCount(0);

        /* Back where it came from, not merely gone from the bin. */
        await gotoCms(page, '/cms/faqs');
        await expect(page.locator('.cms-faq-row', { hasText: question })).toBeVisible();

        await page.locator('.cms-faq-row', { hasText: question }).getByRole('button', { name: 'Delete' }).click();
        await page.locator('.cms-modal').getByRole('button', { name: /^Delete/ }).click();

        await gotoCms(page, '/cms/deleted');
        await page.locator('.cms-faq-row', { hasText: question })
            .getByRole('button', { name: 'Delete for good' }).click();
        await page.locator('.cms-modal').getByRole('button', { name: 'Delete for good' }).click();
    });

    /* Everything else here can be undone. This one cannot, and the wording has to say so. */
    test('deleting for good warns that it is the one thing that cannot be undone', async ({ page }) => {
        const question = unique('Gone for good');

        await binAQuestion(page, question);
        await gotoCms(page, '/cms/deleted');

        const row = page.locator('.cms-faq-row', { hasText: question });
        await row.getByRole('button', { name: 'Delete for good' }).click();

        const modal = page.locator('.cms-modal');
        await expect(modal).toContainText('Delete this for good?');
        await expect(modal).toContainText('cannot be undone');
        await expect(modal).toContainText('Everything else on this screen can be restored');

        await modal.getByRole('button', { name: 'Delete for good' }).click();

        await expect(page.getByText(/deleted for good/)).toBeVisible();
        await expect(row).toHaveCount(0);
    });

    test('an empty bin explains what would arrive in it', async ({ page }) => {
        await gotoCms(page, '/cms/deleted');

        const empty = page.locator('.cms-media-empty');

        if (await empty.count() > 0) {
            await expect(empty).toContainText('waits here until somebody removes it for good');
        }
    });
});
