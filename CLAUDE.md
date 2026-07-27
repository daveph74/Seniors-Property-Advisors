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
- SQLite (`database/database.sqlite`)

## Current state

The CMS admin is a prototype: routes render static props from `mockData.js`, with no
persistence layer and no Puck dependency installed yet. Building either is spec work —
see `docs/specs/`.
