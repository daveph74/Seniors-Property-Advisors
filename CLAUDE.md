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

`PageContentStore::renderable()` drops inactive *and* illegal blocks; the private
`legal()` behind it can keep inactive ones. Restore needs that split — a restored
version must keep sections the editor had hidden while still dropping block types that
have since left `BLOCK_TYPES`, or the draft could never be saved again.

Publish history keeps **whole snapshots, never deltas** — restore correctness beats storage,
and a snapshot is ~9KB. Nothing prunes it. The list read must stay metadata-only: `revisions()`
selects named columns and reads the persisted `section_count`, never the `sections` blob, and a
test asserts that. Publishing an unchanged tree records no revision.

Reusable sections are **independent copies**, stored whole in `reusable_sections` with
their root type in its own column (drop legality is checked before the subtree loads).
Inserting one re-ids the subtree via `reid()`. There is no linking between copies.

## Blog articles

Articles are their own tables (`blog_posts`, `blog_categories`, and a pivot), not page
sections. Bodies are **HTML**, written in a what-you-see editor — the people using this are
not typing markup — and `app/Content/Html.php` is the only gate between what they type and
what a reader receives. It purifies on the way in, so a body in the database is already safe
to print, which is what lets `Pages/Article.jsx` use `dangerouslySetInnerHTML`. Its allowlist
is scope §5's editor list and nothing more; §17 excludes editing raw HTML, so nothing beyond
it should be added. `BlogTest` is what keeps that true — do not widen the allowlist without
adding a case there.

The editor is TipTap (MIT). CKEditor and TinyMCE were rejected: both are GPL-or-paid, and GPL
copyleft would reach this application. It lazy-loads as its own Vite chunk (~140KB gzipped),
so only the article editor pays for it.

The listing at `/blog` is an ordinary CMS page holding a `blog-list` section, so its heading
and intro stay editable. Only `/blog/{article}` is a route, which is why `articles` is a
reserved article slug and `PageContentStore::slugIsReserved()` refuses a page under `blog/`.
`published_at` is the date readers see, never a scheduler — §17 excludes scheduled publishing.

## Accounts and permissions

`/cms/*` requires an active account. Scope §2's two roles live in one place —
`app/Auth/Permissions.php` — as an ability map and a module map; the `permit:{ability}`
middleware, the `Gate` definitions in `AppServiceProvider`, and the sidebar's shared
`auth.modules` prop all read from it. Nothing else should hard-code a role name.

Client administrators create, edit, publish and unpublish content. Super administrators
additionally delete content, restore archived pages, manage accounts and reach settings.
Deleting anything is therefore a super-admin route — the scope never gives client users a
delete, only disable and archive.

`Tests\TestCase` signs in a super administrator for every test, so existing suites need no
auth setup; `PermissionsTest` and `AuthTest` sign in as somebody else, or nobody.

Accounts are made with `php artisan cms:user email --name= --role= [--password=]`, which
generates and prints a password when none is given. `UserSeeder` creates local development
accounts with a shared password and must never run in production.

Everyone changes their own password at `/cms/account`; only super administrators set anyone
else's. Both paths end sessions on other devices, via `auth.session` on the `/cms` group plus
`Auth::logoutOtherDevices()`. Two ordering traps live there, both covered by `AccountTest`:
the plaintext must be handed to `logoutOtherDevices()` **after** the save, and when the target
is the acting user the guard needs `Auth::setUser()` first — it caches its own instance and
would otherwise compare against the hash that was just replaced.

Deployment: set `SESSION_SECURE_COOKIE=true` once the CMS is served over HTTPS, and choose
`SESSION_LIFETIME` deliberately. Neither belongs in local `.env` — see `.env.example`.

## Current state

The public site renders from the database, and the builder is functional: undo/redo,
draft and per-version preview, restore-to-draft, reusable sections, and a real change
summary on publish and in the history drawer.

Pages, FAQs, media and users are real. The remaining CMS routes are still a prototype:
the dashboard, blog, testimonials, navigation, global content and settings render static
props from `mockData.js`, and Puck is not installed. See `docs/specs/`.

Known remaining stub: `onOpenMediaPicker` in the builder still only raises a toast.
