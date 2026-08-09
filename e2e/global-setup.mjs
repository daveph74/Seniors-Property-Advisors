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
}
