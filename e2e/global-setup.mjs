import { execFileSync } from 'node:child_process';
import { closeSync, openSync, rmSync } from 'node:fs';
import { resolve } from 'node:path';

const root = resolve(import.meta.dirname, '..');
const database = resolve(root, 'database/e2e.sqlite');

const artisan = (...args) =>
    execFileSync('php', ['artisan', ...args], {
        cwd: root,
        stdio: 'inherit',
        env: { ...process.env, APP_ENV: 'e2e' },
    });

export default function globalSetup() {
    rmSync(database, { force: true });
    closeSync(openSync(database, 'w'));

    artisan('config:clear');
    artisan('migrate:fresh', '--seed', '--force');

    /* One enquiry to open, read and change the status of.
       Made here rather than by posting the public form: that route is CSRF-protected, and an API
       request context carries no token, so it would be refused for a reason having nothing to do
       with what the test is checking. */
    artisan('tinker', '--execute', [
        'App\\Models\\Enquiry::create([',
        '"name" => "Playwright Enquirer",',
        '"email" => "playwright@example.invalid",',
        '"phone" => "0400 000 000",',
        '"suburb" => "Hawthorn",',
        '"message" => "Sent by the end-to-end suite.",',
        '"consented" => true,',
        '"page_slug" => "/contact",',
        ']);',
    ].join(''));
}
