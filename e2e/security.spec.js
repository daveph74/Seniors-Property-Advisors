import { expect, test } from './fixtures.js';

/**
 * The content policy is the one thing here that fails silently. A blocked script does not error the
 * response — the page simply arrives with a piece of itself missing, and the only witness is a
 * console message nobody is reading. So this walks the application and listens.
 */
const SCREENS = [
    '/', '/how-it-works', '/why-agent-finder', '/compare-agents', '/for-families',
    '/faqs', '/blog', '/contact', '/privacy-policy',
    '/cms', '/cms/pages', '/cms/blog', '/cms/faqs', '/cms/testimonials', '/cms/media',
    '/cms/enquiries', '/cms/navigation', '/cms/global-content', '/cms/activity',
    '/cms/deleted', '/cms/users', '/cms/settings', '/cms/account',
];

test('no screen violates the content security policy', async ({ page }) => {
    const violations = [];

    page.on('console', (m) => {
        if (/Content Security Policy|Refused to/i.test(m.text())) {
            violations.push(`${page.url()} — ${m.text().slice(0, 120)}`);
        }
    });

    for (const path of SCREENS) {
        await page.goto(path, { waitUntil: 'domcontentloaded' });
        await page.waitForTimeout(250);
    }

    expect(violations).toEqual([]);
});

/** The builder portals React into an `about:blank` iframe, which is the sharpest test of the policy. */
test('the builder canvas renders under the policy', async ({ page }) => {
    const violations = [];
    page.on('console', (m) => /Content Security Policy|Refused to/i.test(m.text()) && violations.push(m.text()));

    await page.goto('/cms/pages', { waitUntil: 'domcontentloaded' });
    await page.getByRole('button', { name: 'Contact', exact: true }).first().click();
    await page.waitForURL(/\/cms\/pages\/\d+\/edit/);

    const canvas = page.frameLocator('.cms-canvas-iframe');
    await expect(canvas.locator('body')).not.toBeEmpty();

    expect(violations).toEqual([]);
});

test('every response carries the security headers', async ({ request }) => {
    for (const path of ['/', '/login', '/cms', '/sitemap.xml']) {
        const headers = (await request.get(path)).headers();

        expect(headers['x-content-type-options'], path).toBe('nosniff');
        expect(headers['x-frame-options'], path).toBe('DENY');
        expect(headers['referrer-policy'], path).toBe('strict-origin-when-cross-origin');
        expect(headers['permissions-policy'], path).toBeTruthy();
        expect(headers['content-security-policy'], path).toContain("frame-ancestors 'none'");

        /* The version of PHP is a free hint about which exploits to try. */
        expect(headers['x-powered-by'], path).toBeUndefined();
    }
});

test('inline script is allowed by nonce, not by blanket permission', async ({ request }) => {
    const policy = (await request.get('/')).headers()['content-security-policy'];

    expect(policy).toContain('nonce-');
    expect(policy).not.toContain("script-src 'self' 'unsafe-inline'");
    expect(policy).toContain("object-src 'none'");
    expect(policy).toContain("base-uri 'self'");
});

/** The media route keeps its own, tighter policy rather than inheriting the site's. */
test('a media file is served with its own hardened policy', async ({ request }) => {
    const response = await request.get('/media/2026/08/rachel.jpg');

    expect(response.status()).toBe(200);
    expect(response.headers()['content-security-policy']).toContain("default-src 'none'");
    expect(response.headers()['x-content-type-options']).toBe('nosniff');
});
