import { test as base, expect } from '@playwright/test';

/**
 * Media bytes are not fetched unless a test is about them.
 *
 * Every image on a CMS screen is streamed out of object storage by PHP, and `artisan serve` answers
 * one request at a time. A visit to the media library leaves a request per image in flight, and
 * everything after it queues behind them: measured at **46 seconds for three navigations with
 * images, 2.6 seconds without**. That is a property of the development server rather than of the
 * site — a real one serves them concurrently, and they carry a year-long immutable cache — but it
 * makes the suite time out on work that has nothing to do with pictures.
 *
 * A test that genuinely needs the bytes calls `withImages(page)` and pays for them.
 */
export const test = base.extend({
    page: async ({ page }, use) => {
        await page.route('**/media/**', (route) => route.abort());

        await use(page);
    },
});

export async function withImages(page) {
    await page.unroute('**/media/**');
}

export { expect };
