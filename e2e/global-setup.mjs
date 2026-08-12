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

    /* Before the seed, because `MediaSeeder` writes real bytes and swallows a storage failure with a
       warning — the run would carry on and several tests would fail as though their screens were
       broken. `media:init` creates the bucket and applies the CORS rules a presigned PUT needs, and
       returns a failure exit code when storage is unreachable, which `execFileSync` turns into the
       abort this wants. The suite has always needed the container; now it says so. */
    artisan('media:init');

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
