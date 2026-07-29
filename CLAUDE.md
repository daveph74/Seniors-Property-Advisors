# SPA CMS

Seniors Property Advisors — public marketing site + CMS admin.

Stack: Laravel + React (JSX), Puck for visual section editing.
Section storage is JSON-snapshot based, not normalised rows.

## Conventions

- No code comments. Clean, self-explanatory code.
- Prefer minimal, targeted diffs over structural rewrites.
- Separate data resolution from presentation in components.
- Specs live in docs/specs/. Implement the referenced workstream only.

## Commands

- `composer dev` — server, queue, logs, and Vite together
- `php artisan serve` — app at http://localhost:8000 (Vite only builds assets; it never serves pages)
- `npm run dev` / `npm run build` — assets. Exit `npm run dev` with Ctrl+C so it removes `public/hot`; a stale `hot` file points assets at a dead Vite server and renders a blank page
- `composer test` — clears config, then `php artisan test`
- `./vendor/bin/pint` — PHP formatting

## Layout

- `routes/web.php` — `/` renders the `AgentFinder` Inertia page; `/cms/*` is the admin prototype
- `resources/js/Pages/` — Inertia page components, mirroring the route names
- `resources/js/cms/` — admin shell: `layout/`, `builder/`, `components/`, `data/mockData.js`
- `app/Content/PageContentStore.php` — the only storage seam; both CMS controllers go through it
- SQLite (`database/database.sqlite`)

## Content storage

Pages live in the `pages` table with the whole section tree as a JSON blob in the
`draft` / `published` columns — not normalised rows. Site chrome lives in `settings`
under the `globals` key; publish history in `page_revisions`, one row per publish.

`resources/content/*.json` is no longer a runtime read path. It is seeder input:
`ContentSeeder` (run from `DatabaseSeeder`, and from every test via `Tests\TestCase`)
loads it into the database, keyed on slug and idempotent.

Adding a block type touches `PageContentStore::BLOCK_TYPES`, `resources/js/sections/childTypes.js`
and the React registry — never the database. Adding a `data` key touches nothing.
Note that `SaveSectionsRequest::sanitise()` strips tags from every string in the tree,
so no `data` key can hold markup.

`php artisan content:import [--force]` migrates a legacy `storage/app/content/` overlay
into the database. Run it manually; never from a migration.

## Current state

The public site renders from the database. The CMS admin is still a prototype elsewhere:
its non-builder routes render static props from `mockData.js`, and Puck is not installed.
See `docs/specs/`.
