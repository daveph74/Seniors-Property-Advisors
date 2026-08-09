import { defineConfig, devices } from '@playwright/test';
import { SUPER_ADMIN_STATE } from './e2e/helpers.js';

const baseURL = 'http://127.0.0.1:8001';

export default defineConfig({
    testDir: './e2e',
    globalSetup: './e2e/global-setup.mjs',
    fullyParallel: true,
    forbidOnly: !! process.env.CI,
    retries: process.env.CI ? 1 : 0,
    /* `php artisan serve` is PHP's built-in server: one request at a time, and it cannot fork
       on Windows. A second worker deadlocks the moment a page waits on an Inertia POST. */
    workers: 1,
    timeout: 45_000,
    expect: { timeout: 10_000 },
    reporter: process.env.CI ? 'list' : [['html', { open: 'never' }], ['list']],
    use: {
        baseURL,
        trace: 'retain-on-failure',
        screenshot: 'only-on-failure',
    },
    projects: [
        { name: 'setup', testMatch: /auth\.setup\.js/ },
        {
            name: 'chromium',
            dependencies: ['setup'],
            use: { ...devices['Desktop Chrome'], storageState: SUPER_ADMIN_STATE },
        },
    ],
    webServer: {
        /* APP_ENV goes in the child's environment, not on the command line: `artisan serve`
           forwards a whitelist of variables to the server process and drops `--env`, which
           would leave the site running against the developer's own database. */
        command: 'php artisan serve --host=127.0.0.1 --port=8001',
        env: { APP_ENV: 'e2e' },
        url: baseURL,
        reuseExistingServer: false,
        stdout: 'pipe',
        stderr: 'pipe',
    },
});
