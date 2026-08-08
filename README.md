# Seniors Property Advisors

The public marketing site and its custom CMS, in one Laravel application. Laravel + React (JSX)
over Inertia; content is stored in the database and rendered by a fixed registry of section
components.

Built to `docs/specs/cms-scope.md`. Evidence that each acceptance criterion is met, and the test
that proves it, is in `docs/acceptance.md`.

## Getting started

```sh
composer setup          # install, .env, key, migrate, npm install, build
docker compose up -d    # object storage for the media library, on :4566
php artisan media:init  # create the bucket
php artisan migrate:fresh --seed
composer dev            # server, queue, logs and Vite together
```

Storage comes up **before** seeding: the pages point at pictures in the media library, and
`MediaSeeder` has nowhere to put them otherwise. Seed without it and the rows still appear with the
right dimensions, but every picture 404s until you start it and seed again. See `docs/images.md`.

The site is then at http://localhost:8000 and the CMS at http://localhost:8000/cms.
**Vite only builds assets — it never serves pages**, so open the `artisan serve` address, not
Vite's.

Seeded local accounts (from `UserSeeder`, which must never run in production) share the password
`password`:

| Email | Role |
|---|---|
| `superadmin@seniorspropertyadvisors.com.au` | Super administrator |
| `helen@seniorspropertyadvisors.com.au` | Client administrator |

Sign in at `/login`. Use the super administrator for most work — settings, user management, deletes
and archive restores are all super-admin only. Use Helen to check what a client administrator
actually sees.

## Everyday commands

| Command | What it does |
|---|---|
| `composer dev` | Server, queue worker, logs and Vite together |
| `composer test` | Clears config, then runs the suite (453 tests) |
| `./vendor/bin/pint` | PHP formatting |
| `npm run build` | Build assets |
| `php artisan cms:user email --name= --role= [--password=]` | Create or promote an account; prints a generated password when none is given |

Exit `npm run dev` with Ctrl+C so it removes `public/hot`. A stale `hot` file points assets at a
dead Vite server, and every page renders blank.

## Occasional commands

| Command | When |
|---|---|
| `php artisan media:init` | Once per environment — creates the storage bucket |
| `php artisan media:optimise [--dry-run]` | Once on any environment with pre-existing images; the migrations add the columns but this builds the small copies |
| `php artisan pages:scaffold` | Creates the agreed page list as drafts; safe to run twice |
| `php artisan content:import [--force]` | Migrates a legacy `storage/app/content/` overlay into the database. Run by hand, never from a migration |
| `php artisan content:purge-deleted [--days=90] [--force]` | Reports, or removes, long-deleted content |

## Deploying

Two settings are deliberately left unset locally and must be chosen before going live — see the
notes in `.env.example`:

- **`SESSION_SECURE_COOKIE=true`** once the CMS is served over HTTPS. It must stay off locally: a
  secure-only cookie is never sent over `http://localhost`, so sign-in would simply fail.
- **`SESSION_LIFETIME`** — decide it deliberately rather than taking the default.

Also run `php artisan media:optimise` once, and point the `AWS_*` variables at real object storage
rather than the local container.

**Do not run `php artisan db:seed` on a live site.** It is for setting one up. Pages are seeded with
`updateOrCreate`, so every page the client has edited is replaced by the version in
`resources/content/pages/`, and a page has revision history to recover from but the seeder does not
ask first. The two `settings` rows are protected — they are inserted once and never overwritten — so
the menus, SEO defaults and analytics ids survive, but nothing else does. To bring one new page into
a running site, use `--class=` with a seeder that only touches it, or add it through the CMS.

## Where things live

| Path | What |
|---|---|
| `routes/web.php` | `/` renders the `AgentFinder` page; `/cms/*` is the admin |
| `app/Content/PageContentStore.php` | The only storage seam — both CMS controllers go through it |
| `app/Auth/Permissions.php` | Scope §2's two roles, expressed once |
| `app/Content/Html.php` | The only gate between what an editor types and what a reader receives |
| `resources/js/Pages/` | Inertia pages, mirroring the route names |
| `resources/js/sections/` | The public section components and their type rules |
| `resources/js/cms/` | Admin shell: `layout/`, `builder/`, `components/` |
| `database/database.sqlite` | The database |

## The other documents

Read these rather than rediscovering what they contain:

- **`CLAUDE.md`** — conventions and the architectural decisions worth knowing before changing
  anything: how content is stored, why publish history keeps whole snapshots, the ordering traps in
  password changes. Start here before your first change.
- **`docs/specs/cms-scope.md`** — the client scope, §1–§18.
- **`docs/acceptance.md`** — how we know each acceptance criterion is met, and which test proves it.
- **`docs/TODO.md`** — what is outstanding, and the decisions waiting on somebody.
- **`design.md`** — brand colour, typography and tone, from the official style guide.
- **`docs/specs/page-section-module.spec.md`** — the page and section module in detail.

## A note on the section registry

Adding a block type touches `PageContentStore::BLOCK_TYPES`, `resources/js/sections/childTypes.js`
and the React registry — never the database. That constrained registry is what stops an editor
building a layout the design does not support, and it is enforced on the server, so a hand-crafted
request is refused exactly like a bad drag. `CmsBuilderTest` is what keeps that true; do not widen
the allowlist without adding a case there.
