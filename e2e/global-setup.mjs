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

    /* The database is rebuilt every run; without this the bucket was not, so every upload the suite
       performed stayed there forever as an object no row referred to.

       The guard is the point. This deletes every object it can see, so it refuses to run unless the
       configured bucket is named like the throwaway one — a bucket set wrongly in `.env.e2e`, or an
       `APP_ENV` that failed to reach this process, would otherwise empty the bucket the site is
       actually using. Prefer the ugly abort to the quiet catastrophe. */
    artisan('tinker', '--execute', [
        '$bucket = (string) config("filesystems.disks.s3.bucket");',
        'if (! str_ends_with($bucket, "-e2e")) {',
        'throw new RuntimeException("Refusing to empty \\"{$bucket}\\": the end-to-end bucket must be named -e2e.");',
        '}',
        '$disk = Storage::disk("s3");',
        '$files = $disk->allFiles();',
        'if ($files !== []) { $disk->delete($files); }',
        'echo "Emptied {$bucket} (".count($files)." objects).".PHP_EOL;',
    ].join(' '));

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
