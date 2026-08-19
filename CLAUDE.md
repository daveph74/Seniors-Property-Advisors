# SPA CMS

Seniors Property Advisors — public marketing site + CMS admin.

Stack: Laravel + React (JSX). The visual section builder is the application's own — Puck was
evaluated, never installed, and is not going to be.
Section storage is JSON-snapshot based, not normalised rows.

## Conventions

- No code comments. Clean, self-explanatory code.
- Prefer minimal, targeted diffs over structural rewrites.
- Separate data resolution from presentation in components.
- Specs live in docs/specs/. Implement the referenced workstream only.
- **This file is part of the change, not a write-up of it.** Anything that adds, removes or alters a
  functionality updates `CLAUDE.md` in the *same* commit — no follow-up pass, no separate docs
  commit. A change that lands without it is incomplete.

### What that means in practice

- **Added** — a section, or a paragraph in the nearest existing one. Say why the design is the way it
  is and what breaks if somebody changes it back. An inventory of files is not documentation; this
  file has been wrong twice precisely because it listed what existed instead of what was decided.
- **Changed** — edit the sentence that is now false. Do not append a newer note beside it: two
  statements with no rule saying which wins is how "the dashboard is a prototype" and "an image field
  is a text box" both survived for several features after they stopped being true.
- **Removed** — delete the paragraph. A trap that no longer exists is worse than no note, because the
  next person spends their time avoiding it.
- **New trap found** — the traps here were each paid for once. Write it down at the moment it is
  understood, in the section it belongs to, with what the symptom looked like — the symptom is what
  the next person will search for, not the cause.

Two checks before considering a change done: does anything in this file now contradict the code, and
does anything contradict another part of this file. If something here reads like a limitation, verify
it against the code before repeating it — that is the failure mode this repository actually has.

## Commands

- `composer dev` — server, queue, Vite and Reverb together, so the CMS updates live without a second
  terminal. `concurrently --kill-others` means one process failing stops the rest, which is why
  **`pail` is not in there**: it needs `pcntl`, XAMPP on Windows has no such extension, so it exited
  immediately and took the whole stack down with it — the symptom is `composer dev` returning code 1
  seconds after starting, naming the concurrently line rather than the command that actually failed.
  Logs are `composer logs` instead, on a machine whose PHP can run them
- `php artisan serve` — app at http://localhost:8000 (Vite only builds assets; it never serves pages)
- `npm run dev` / `npm run build` — assets. Exit `npm run dev` with Ctrl+C so it removes `public/hot`; a stale `hot` file points assets at a dead Vite server and renders a blank page
- `composer test` — clears config, then `php artisan test`
- `npm run e2e` — builds assets, then drives the CMS in a real browser (Playwright). `e2e:headed`
  shows the browser doing it, `e2e:ui` is the interactive runner, `e2e:report` opens the last report.
  Run from the project root: from inside `e2e/` Playwright finds no config and fails everything
- `./vendor/bin/pint` — PHP formatting

**Deploying is not one of these commands.** `composer dev` is a laptop convenience with no production
equivalent — a server has a release step and three processes something else keeps alive. That is
"Running it in production", further down, and it is the section to read before a first deploy: every
mistake it lists fails silently rather than loudly.

Run by hand, never scheduled or called from a migration: `content:import [--force]`, `seo:apply [--force]`,
`content:purge-deleted [--days=90] [--force]`, `enquiries:purge [--months=24] [--force]`,
`enquiries:erase {email} [--force]`, `activity:prune [--months=24] [--force]`, `media:init`,
`media:optimise [--dry-run]`, `pages:scaffold`, `security:check [--production]`, `cms:user`. Each says
why under its own heading below; the pattern they share is that all of them either destroy something
or touch the environment, and both are somebody's decision rather than a side effect of deploying.

### Running the suite on another database

SQLite is the default because it is fast and needs nothing installed, not because it is what a server
runs. PHPUnit leaves an environment variable alone if one is already set, so the whole suite runs
against another engine without editing anything:

```sh
DB_CONNECTION=mysql DB_DATABASE=spa_cms_test DB_USERNAME=root DB_PASSWORD= php artisan test
```

**Worth doing before a release**, because the engines disagree in ways that are invisible until they
are not, and both of these were live faults found the first time it was run:

- **Reserved words.** `Like` pasted the column name into raw SQL, and the media table has a column
  called `key` — every CMS search answered with a syntax error on MySQL and worked perfectly on
  SQLite. It is wrapped by the connection's own grammar now.
- **Backslashes inside `LIKE`.** MySQL treats one as an escape character and SQLite does not, so the
  media usage scan — which looks for the `\/media\/…` spelling `json_encode` writes into a section
  tree — quietly matched nothing, and an editor would have been told a published page's picture was
  unused. Naming an `ESCAPE` character makes it a literal on both.

Nothing else in the application is engine-specific: no `DB::raw`, and the JSON columns are read
through casts rather than queried into. The browser suite reads its connection from `.env.e2e` and
only deletes a database file when there is a file to delete.

## Layout

- `routes/web.php` — `/` renders the `AgentFinder` Inertia page; `/cms/*` is the admin
- `resources/js/Pages/` — Inertia page components, mirroring the route names
- `resources/js/cms/` — admin shell: `layout/`, `builder/`, `components/`, `data/constants.js`
- `app/Content/PageContentStore.php` — the only storage seam; both CMS controllers go through it
- SQLite (`database/database.sqlite`)

## Content storage

Pages live in the `pages` table with the whole section tree as a JSON blob in the
`draft` / `published` columns — not normalised rows. Site chrome lives in `settings`
under the `globals` key; publish history in `page_revisions`, one row per publish.

`resources/content/*.json` is no longer a runtime read path. It is seeder input:
`ContentSeeder` (run from `DatabaseSeeder`, and from every test via `Tests\TestCase`)
loads it into the database, keyed on slug.

Pages are `updateOrCreate`, so re-seeding **overwrites an edited page** with the file's
version — idempotent file-to-database, not idempotent from the editor's side. The two
`settings` rows are `firstOrCreate` instead: they hold the menus, footer, SEO defaults
and the GA4/GTM ids, have no revision history, and a wholesale replace is unrecoverable.
The consequence is deliberate — a `globals.json` menu change never reaches a database
that already has the row, and `/cms/navigation` is where a running site is edited.

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

## Testimonials

Their own `testimonials` table. Scope §7 closes with a constraint rather than a field — names and
images may only be published where the client has given permission — so consent is **recorded, not
validated**: `consent_confirmed_at` plus `consent_confirmed_by`, set by its own route. `scopeActive`
requires both `active` and a recorded consent, so an unconfirmed testimonial cannot reach a reader
through any path, and withdrawing permission unpublishes and unfeatures in the same move. One flag
covers the name and the photo together; splitting them was considered and judged not worth the
second failure mode.

Updates validate `sometimes|required`, so a toggle patches **one field alone**. Sending the whole
record on a single-field change lets a stale copy revert whatever else moved — turning "featured" on
switched "showing" back off, which is how that was found.

The section reads `library.testimonials` and picks in the browser: `featured`, `all`, or `chosen`
(a comma-joined list of ids, scalar so the section-tree sanitiser passes it through untouched).
`ContentLibrary::choices()` feeds the builder's picker and is builder-only. The slider is a focusable
overflow scroller with a `:focus-visible` ring — never autoplaying, since auto-rotating quotes are
worst for the readers this site is for.

`MediaController::usage()` scans testimonial and article images too. It only read section trees
before, so an article's featured image could be deleted while in use with no warning.

## FAQs

Their own `faqs` and `faq_categories` tables, read into a section the same way testimonials are —
`library.faqs` and `library.faqCategories`, picked in the browser.

The load-bearing column is **`page_slug`**, and it is nullable on purpose. Null means the question
belongs to the whole site and appears wherever a `faq-list` section is placed; a slug means it is
that page's question and nowhere else. `ContentLibrary` resolves it as *null or this page*, so the
general set and the page's own set arrive together and the section never has to ask twice. Categories
are filtered through the same rule — a category whose only questions belong to another page is not
offered, because an empty category reads as a broken filter.

Reordering is its own route for both questions and categories, and the order is a stored
`sort_order`, not the id: the whole point of the screen is that the most-asked question goes first.
Showing and hiding is `active`, with no `status` column — there is nothing to publish here separately
from the page the section sits on.

Answers are plain text through `Text::clean()`, not HTML. A question is one paragraph; the editor
that would justify HTML is the one the blog pays 140KB for.

## Blog articles

Articles are their own tables (`blog_posts`, `blog_categories`, and a pivot), not page
sections. Bodies are **HTML**, written in a what-you-see editor — the people using this are
not typing markup — and `app/Content/Html.php` is the only gate between what they type and
what a reader receives. It purifies on the way in, so a body in the database is already safe
to print, which is what lets `Pages/Article.jsx` use `dangerouslySetInnerHTML`. Its allowlist
is scope §5's editor list and nothing more; §17 excludes editing raw HTML, so nothing beyond
it should be added. `BlogTest` is what keeps that true — do not widen the allowlist without
adding a case there.

**A link may leave this site; an image may not.** `img-src` is `'self' data:` and nothing more, so a
hotlinked picture would be stored, published and drawn for no reader. Handled in three places, and the
order is the design: `RichTextEditor`'s `transformPastedHTML` drops remote sources **at the paste** and
toasts how many, `SaveBlogPostRequest` refuses a body that still carries one, and
`URI.DisableExternalResources` strips it if both are bypassed.

The paste is where it belongs for an article body, because pasting is the only way one arrives — the
toolbar's image button opens the media library and there is no field for an address. Refusing the save
was tried first and was wrong: a writer pasting an article could not store their own words until they
had chased addresses they never typed, which contradicts the principle stated in `SaveBlogPostRequest`
itself — a paste "keeps their words and loses the markup… no error to decipher".

**The builder was the other path, and its box is gone.** `ImageField` carried a free text address whose
placeholder invited "a web address" — which narrowing `img-src` turned into a block that saves,
publishes and draws nothing. An image is chosen from the library now, full stop: nothing to type is a
better guarantee than a warning about what you typed. Two things moved with it, and both are the sort of
thing that gets missed — the media library's hint said "Paste this into an image field", which had
outlived its target, and `04-pages-blocks` filled image fields by typing a path, which would now land in
the "Describe the image" box in the same `.cms-field` and round-trip perfectly while asserting nothing.
Image fields are skipped there and the picker has one real test in `03-pages-builder` instead.

Section trees have no server-side backstop for this, deliberately: the schema saying which keys hold an
image is in `contentFields.js` and nowhere in PHP, so one would mean the schema in two languages or
guessing by file extension.

Two traps: `DisableExternalResources` must not become `DisableExternal`, which takes links with it, and
`URI.Host` has to be set from `app.url` or HTMLPurifier calls this site's own absolute address external
and strips an image the form request just allowed.

The editor is TipTap (MIT). CKEditor and TinyMCE were rejected: both are GPL-or-paid, and GPL
copyleft would reach this application. It lazy-loads as its own Vite chunk (~140KB gzipped),
so only the article editor pays for it.

The listing at `/blog` is an ordinary CMS page holding a `blog-list` section, so its heading
and intro stay editable. Only `/blog/{article}` is a route, which is why `articles` is a
reserved article slug and `PageContentStore::slugIsReserved()` refuses a page under `blog/`.
`published_at` is the date readers see, never a scheduler — §17 excludes scheduled publishing.

## Media

**The bytes never pass through PHP on the way in.** `POST /cms/media/sign` checks the size, the
extension and the permission and returns a presigned URL; the browser PUTs the file to storage
itself; `POST /cms/media` then records the row. Three calls where one upload would do, and the
reason is the one thing the CMS does that leaves the origin — which is also why `09-media.spec.js`
walks all three steps rather than asserting the final button, and why `connect-src` is part of the
content policy at all.

Coming **out**, they do pass through PHP: `/media/{key}` streams from the disk. That is deliberate —
the key is the only identity an image has, so a public bucket URL would be a second one — and it is
also what makes the e2e suite block `/media/` paths, since a one-request-at-a-time dev server serves
them one at a time. The response carries a year-long `immutable` cache, which holds only because a
key is a ULID and is never reused. `nosniff` and a `default-src 'none'` policy ride along with it,
because an uploaded file is the one thing here a reader supplies.

- **An SVG is a document, not a picture.** It can carry script, so uploading one needs
  `media.upload_svg` — super administrator only. The headers neutralise it either way; the
  permission is about who can put one there.
- **One file per image, plus one small copy.** `ImageOptimiser` shrinks and re-encodes in place and
  keeps the format, because the extension is part of the key. A responsive set of widths would serve
  phones better and would mean several identities for one image, matched across page trees, drafts,
  revisions, articles, testimonials and chrome. The small copy lives at its own `thumb_key` and is
  served through the same route so it inherits the same headers. `media:optimise` brings images
  uploaded before any of this existed up to the same standard; re-running is safe.
- **`media:init` creates the bucket and applies CORS.** A presigned PUT is cross-origin and fails
  without it. `global-setup.mjs` runs it before seeding for exactly that reason.
- **The record call does not believe the browser.** `sign()` checks the name and extension, but
  `store()` re-opens the object and judges it **by its bytes** — a PHP file uploaded as `lie.jpg`
  with `image/jpeg` is refused and the object is removed — and it refuses any key this application
  did not mint, so `../../etc/passwd` is not a way in. The signing step is where the rules are
  explained; this is where they are enforced, because between the two the file was in the browser's
  hands.
- **Nothing is deleted while it is in use.** `usage()` scans section trees *and* testimonial and
  article images, and the delete route refuses with the list of what still points at it. The library
  asks before it offers the dialog, which is why blocking `**/media/**` in a test breaks the delete
  screen rather than just its thumbnails.

## Activity log

`activity_log`, written by `app/Observers/RecordsActivity.php` — **on the model, not in the
controllers**. Recording per controller stays complete only while everybody remembers, and a new
route, a command or a tinker session slips past.

Two decisions hold it together. **The verb is read back off the change, never passed in**: publish,
unpublish and archive are not events to the database, they are `status` moving, so the observer
derives which happened from `getOriginal('status')` — and for the models with no status (questions,
testimonials) from `active`, since §13 asks for published and unpublished rather than "edited" three
times running. And **deleted and destroyed are told apart**, because one is recoverable and the other
is not, and that is the whole question somebody asks of this screen.

`Activity::note()` exists for what is not a row: the menus and site wording live in one `settings`
row keyed by a string, so there is no id to point at and "edited Setting #globals" would say nothing.
`by_name` is stored as text beside `by_id` so an entry stays readable after the account is gone.

One trap: **restoring saves the row**, so `updated` fires alongside `restored` and would log an edit
nobody made. The observer returns early when `deleted_at` is the only change.

And one thing the log must **not** do: an enquiry's deletion is recorded without the sender's name.
Erasing somebody while minting a permanent copy of their name is not erasing them —
`OwaspTest::test_a09_the_log_does_not_keep_what_a_deletion_was_meant_to_remove` pins it.

## Deleted content

Content deletes are soft. `/cms/deleted` is **one screen for all three kinds** — articles, questions,
testimonials — because somebody hunting for what they deleted does not always remember what it was
filed as, and three empty bins is three places to look. `DeletedContentController::KINDS` is the
whole registry.

The screen and its restore are behind `content.restore`; `destroy()` is the only path that means it
and is behind `content.delete` — a super administrator either way, but not the same ability, so
restoring is never granted by granting a delete. Everything else is recoverable, which is what lets the list screens delete without a
scare dialog.

"Recently deleted" has to end somewhere or the words stop meaning anything: `content:purge-deleted
--days=90` reports by default and needs `--force` to act. Run by hand, never scheduled — a cron job
that quietly destroys content should be somebody's decision.

Pages are not here. They archive instead, and `/cms/pages` restores them.

## Slug changes and redirects

Renaming a **published** page or article leaves a `page_redirects` row behind, and the public
controllers check it before they 404. Three details, all easy to lose:

- **Chains are collapsed, not followed.** Renaming twice rewrites the existing rows' `to_url` to the
  new address rather than adding a hop, so a link from years ago still costs one lookup.
- **A redirect to the address being claimed is deleted**, or a page moved back to its old slug would
  redirect to itself.
- **Only published renames record anything.** A draft nobody could reach has no address worth
  preserving. `home` is refused a rename outright.

## SEO and crawlers

### The page itself is server-rendered

`resources/js/ssr.jsx` and a node process render the public site, so the delivered document carries the
heading, the copy and the links. It did not, for a long time, and the reason it went unnoticed is worth
keeping: the head tags and JSON-LD **were** always server-rendered, so sharing cards worked perfectly
while the `<body>` was an empty div and a JSON blob. Google runs JavaScript and indexed the site anyway;
Bing, LinkedIn, Slack and every AI crawler read what arrives, and what arrived was nothing. Measured on
`/how-it-works`: 8KB with no `<h1>` and no links, against 38KB with both.

Three things hold it together, and each is a way it would otherwise not work at all:

- **`app.jsx` hydrates when the server sent HTML and mounts fresh when it did not.**
  `createRoot().render()` on server-rendered nodes throws that HTML away and redraws — SSR would still
  "work" and buy nothing, which is the version of this that nobody notices. The other branch is equally
  load-bearing: the admin is never server-rendered, so it arrives as an empty div where `hydrateRoot`
  would warn.
- **The admin is excluded, by `HandleInertiaRequests::$withoutSsr`.** Nothing crawls it — `robots.txt`
  refuses it and it is behind sign-in — so rendering it twice would buy a slower response and pull the
  editor and the socket client into a process with no browser to offer them.
- **`ssr.jsx` globs `./Pages/*.jsx`, one level, eagerly.** SSR needs an eager glob, and one level happens
  to be exactly the two pages a visitor can reach, because `Login` lives under `Pages/Auth/` and the
  admin under `Pages/Cms/`. A full glob would execute every admin module in node at boot — TipTap
  reaching for `document` would kill the renderer before it served one request. `SsrScopeTest` asserts
  the glob and the exclusion list still agree.

The hydration contract that keeps it honest: **nothing may touch `window`, `document` or
`localStorage` while rendering** — effects and handlers only — and a breakpoint is a CSS class, never a
measured width. A section that formats a date or reads `innerWidth` during render produces a body that
differs from what hydration wants, and React recovers by redrawing: the visible symptom is a flash, the
crawled symptom is wrong content.

Two local traps. **`public/hot` diverts SSR to Vite**, so with `composer dev` running the production path
is never exercised — if you are checking whether SSR works, that file must be out of the way, and a test
about SSR has to point Vite at a hot file that does not exist or it passes or fails on whether somebody
had a dev server open.
And **Inertia memoises the render per request scope**, so a test or a script making two page visits in
one PHP process gets the first page's HTML twice; it looks exactly like the renderer serving one page for
every address.

### The head, and the sitemap

`app/Content/Seo.php` works out the head once, on the server, and `resources/views/app.blade.php`
prints it — including the JSON-LD (Article, Organization). Two things it exists to get right:
**`og:image` has to be absolute**, and content stores media as `/media/…`, which is correct for an
`<img>` and silently useless to a crawler; and **the width and height have to be sent**, or a crawler
that has not fetched the image yet renders the small card, so the first person to share a link gets
the worse preview. The media table already knows both.

`/sitemap.xml` and `/robots.txt` are routes, not files. The list of addresses lives in
`SeoReport::sitemapUrls()` rather than in the controller, and that placement is the design: `/cms/seo`
reports *why* an address is missing from the sitemap, and a report explaining a list has to be reading
the very list it explains. A shared predicate would have left two call sites free to drift; membership
of one produced list cannot. `SitemapController` is four lines over it now, and it is **deliberately
uncached** — two queries over a few dozen rows against five ways to serve a stale sitemap.

It filters on `status` and the page's own noindex flag and nothing else — advertising a noindexed page
asks a crawler to fetch something it is then told to forget. `robots.txt` also disallows
`/blog/articles`, the load-more endpoint, which answers with article content and no page around it.

**`lastmod` is a page's last publish, never its last save.** A page has a draft, so `updated_at` moves
when somebody saves work no reader can see, and reporting that asks every crawler to re-fetch a page
that did not move. An article has no draft — editing a published one changes it live — so `updated_at`
is the honest answer there. A page with no `published_at` gets **no lastmod at all**: falling back to
`updated_at` was written first and put the draft-save time back for precisely the pages the rule
protects, and several seeded pages are in that state. Absent means "unknown", which is true and valid;
a wrong date is neither.

### What the metadata has to fit inside

Every published address carries its own title and description, and two numbers are enforced by
`SeoContentTest` rather than left to judgement: a description of **155 characters** and a rendered title
of **61**, the latter including the ` | Seniors Property Advisors` the site format appends. Neither is the
stored limit — `seo.description` allows 320 — because the question is not what may be saved but what a
search result shows before it cuts. Eleven descriptions were over the line when this was written, all of
them perfectly valid and all of them truncated mid-sentence in the one place a reader decides whether to
click.

Three more rules that file pins, each of which had gone wrong:

- **No address may inherit the site-wide description.** It exists as a fallback and was `null`, so a page
  whose own description was ever cleared shipped no description and no `og:description` at all. It has a
  value now, and a page relying on it is a finding rather than a pass — a sentence shared by twelve
  addresses tells a reader nothing about which one to open.
- **The three articles carry their own.** They had none, so each fell back to the site's — three results,
  one sentence. An article's `summary` is not reusable for this: it is a card blurb, written for a listing
  where the title sits directly above it.
- **The seed files and the database must agree.** Both were written, because `resources/content/pages/*.json`
  is the source of truth for a fresh install and the database is what serves. Applied through
  `PageContentStore::saveDetails()`, which merges the `seo` key and leaves the section tree alone — not
  through `ContentSeeder`, which is `updateOrCreate` over whole pages and would overwrite an editor's work.
### Getting metadata onto a site that already has content

`php artisan seo:apply` writes the titles and descriptions from `resources/content/pages/*.json` onto
pages that already exist, reporting unless given `--force`. It exists because the obvious way is
destructive: those files are the source of truth for a *fresh* install, and `db:seed` runs
`updateOrCreate` across the **whole page** — sections included — so on a live site it would replace
every page with the repository's version and silently undo months of editing.

So the command writes **two fields and nothing else**, through `PageContentStore::saveDetails()`, which
merges. A seed file that does not mention a sharing image is not an instruction to remove one — an image
and a canonical are per-page choices an editor made in the builder, and `ApplySeoMetadataTest` asserts
they survive. A page in the repository the site has never had is reported and stepped over rather than
created; creating one is `pages:scaffold`'s job.

The practical consequence is worth stating plainly, because it is the thing that looks like a failed
deploy: **releasing this changes nothing a reader sees.** The release step has no `db:seed`, correctly, so
the live titles and descriptions stay as they were until somebody runs `seo:apply --force`.
### The head, and what is deliberately not in it

Added because the data was already there and the tag was not: `og:site_name` and `og:locale` (`en_AU` —
Facebook assumes American otherwise), `og:image:alt` from the media row, `article:published_time` and
`article:modified_time` on articles, and **a `robots` tag on every page** rather than only on a hidden
one, because an indexable page still has a preference worth stating: `max-image-preview:large` is what
earns a full-width thumbnail in mobile results instead of a postage stamp.

That last one broke something invisible, which is the part worth remembering. Both public controllers
asked `isset($head['robots'])` to mean "is this page hidden from search", which was true only while a
robots tag existed for no other reason. Every page sends one now, so that reading would have silently
stopped **every page emitting any structured data at all** — a change no existing test would have
noticed. `Seo::isHidden()` is the question actually being asked, in one place.

Left out on purpose, all of it cargo cult for this site: `twitter:site` and `twitter:creator` (there is no
X account, and X falls back to the Open Graph tags anyway), `theme-color`, `rel=prev/next` (Google dropped
it in 2019 and there are no paginated addresses), `speakable` (news publishers only), a standalone
`WebPage` node, and font preconnect — DM Sans is bundled, so preconnecting to Google Fonts on a public
page would make it slower.

**`ProfessionalService`, not `LocalBusiness`.** Both are narrower than `Organization` and both take an
address, but `LocalBusiness` claims a place a customer can walk into, and a 1300 number with a serviced
office on level 14 is not that. `areaServed: Australia` says the true thing instead. The organisation node
carries an `@id`, and an article's `publisher` points at it rather than restating the name — two nodes
describing one business are two businesses as far as a search engine is concerned. The email is read out of
the footer's own contact column, so it cannot disagree with what a reader sees. **The ABN is deliberately
absent**: the one in the footer is a placeholder, and an identifier invented for a search engine is worse
than none.

**No `aggregateRating`, and there is a test whose whole job is to keep it that way.** The `testimonials`
table has a `rating` column, so this is the obvious place to add stars — and it is a trap twice over:
Google does not show review rich results sourced from an organisation's own first-party testimonials, and
marking up your own quote slider is the pattern that earns a manual action. The absence was already
correct; now the next person to have the idea finds out from a red test instead of from Search Console.
### Unfinished content, and four checks that were not worth making

`PublishedContentTest` fails when a published page carries text a reader would see as unfinished —
"TO BE CONFIRMED", "[X business days]", "PLACEHOLDER" and the rest. It exists because three pages already
do: `privacy-policy`, `terms-and-conditions` and `complaints`, the last of which says **in its own words**
that its timeframes are placeholders and must not be published, and is published. Nothing in the
application had an opinion: a placeholder validates, saves, publishes and is served exactly like a finished
sentence.

It **pins the exact set** rather than failing or skipping. A permanently red suite teaches people to ignore
it, and a skip would break this file's own rule that a standing skip is a standing question — so a new
placeholder anywhere fails it, and *finishing* one of the three fails it too, with the list to shorten,
which is the most useful moment to be asked. Still outstanding, and not inventable here: the ABN, the
complaint response timeframe, who handles complaints, and an effective date.

Four things an audit flagged and the code did not need, recorded so nobody pays to find out twice:

- **`width`/`height` on every image.** The wrappers already carry `aspect-ratio` in `app.css` —
  `.hero-visual`, `.why-visual`, `.family-visual`, `.team-member__photo`, `.article-card__image`,
  `.article__hero` — so the space is reserved before the image arrives. The two rules without a ratio,
  `.block-image img` and `.text-image__media img`, belong to blocks **no seeded page uses at all**. The
  finding came from reading the markup and not the stylesheet.
- **An eager-loading escape hatch for `ImageBlock`.** Same reason: it would let a page opt out of lazy
  loading for its largest image, and no page has one.
- **A single-`<h1>` guard.** Two hero sections on one page would produce two, and nothing prevents it — but
  no page has two, and multiple `h1`s have not been a ranking problem for years. The cost of the guard is
  making every hero ask whether it is the first one.
- **Editorial internal links.** Real finding: `/how-it-works`, `/why-agent-finder`, `/faqs` and `/contact`
  have no internal links in their body at all, so nothing but the header and footer passes any authority to
  them. It is not fixable as metadata, and it is somebody's decision rather than a defect: section text
  cannot hold markup (`SaveSectionsRequest::sanitise()` strips tags from every string), so a link means a
  button or a call-to-action block — which is exactly what was deliberately removed when every page was cut
  to one section.
### The SEO screen

`/cms/seo` is two tabs over one ability, `seo.manage` — super **and** client administrator, because a
client admin already writes every one of these fields in the page builder, so gathering them onto one
screen widens nobody's reach.

**Overview** is a row per publicly addressable URL — pages, articles, and trashed articles too, since
an address a search engine still holds is exactly what somebody comes here to explain. **A deleted row
is reported and never editable**: it has no edit link, its address is plain text rather than a button,
and the server refuses the patch anyway (`findOrFail` excludes trashed). All three, because two of them
were not enough — the row was clickable, the editor opened, and its save answered 404 while the panel
sat there looking busy. Restoring is the Deleted content screen's job, behind a different ability. What it reports
is what a crawler *receives*, not what the column holds: every row goes through `Seo::head()`, the same
function `app.blade.php` prints from. That works only because `head()` never reads the request — the
callers pass the URL — so the report hands it each row's **public** address. Get that wrong and every
row claims the admin screen is its canonical, which is the kind of report somebody acts on before
noticing. `descriptionInherited` is called out separately from "has a description": twelve addresses
sharing one site default is a finding, not a pass.

**Defaults** is the title pattern, default description and default sharing image — which used to be a
tab on `/cms/settings` and **moved rather than being copied**. Settings is `settings.manage`, so
leaving them there kept them from the person most likely to want them, and dragging Settings' gate down
would have handed out the GA4 ids and the legal wording with it.

That move gave the `site` settings row **two writers**, which this file used to describe as the thing
that must never happen. It is safe now for one reason: both go through `Site::merge()`, so a save says
which top-level keys it changes instead of asserting the whole row. `/cms/settings` replaced it
wholesale before, and a save from the SEO screen — which has no analytics ids to send — would have
cleared them, failing silently until a monthly report came back empty. `SeoEditingTest` pins both
directions.

Two fields are editable from a row, description and hide-from-search, through `PATCH /cms/seo/{kind}/{id}`
with `sometimes` on both — so a toggle patches one field alone, the rule the testimonials screen
already pays for. **The canonical and the search title are deliberately not offered here**: a canonical
typed into a list row is an address de-indexed by a fat finger, and it stays in the builder's SEO panel
where there is room to explain it. `SeoFieldRules` holds the limits for all four requests that write
these values, because a description that saves from the builder and is refused here reads as one of the
two screens being broken.

Two traps in its own table markup. `--seo` is the grid modifier and **the head row wears it too**, so
`.cms-table__row--seo` first() resolves to the header — which fails as "the row does not contain that
text" and reads as a broken save; a data row is the one that also has `.cms-table__row`. And the screen
carries **two segmented strips** — the Overview/Defaults tabs and the filter row — plus a sidebar with
its own "Pages" link, so every locator has to name the strip it means or Playwright reports a
strict-mode violation rather than clicking the wrong thing.

Two smaller things worth knowing. A page row's link is built from **`cms_id`, not `id`** — the same trap
the search palette pays for, since `CmsPageController::edit` resolves through `findByCmsId` — and
`SeoReportTest` follows the link rather than matching its shape, which is the only reason that is
caught. And filtering and search run **in PHP, not SQL**: the title after the site format, an inherited
description and sitemap membership are not columns, so `Like` cannot see them and a search pushed down
to the database would find fewer rows than the eye can see on screen. The row set is capped at
`SeoReport::CEILING`, with a `truncated` prop so the screen says so rather than quietly slowing down
every month.

There is **no CSV export**. One was built and removed: Google reads the XML, and a spreadsheet was a
workflow nobody had asked for, carrying formula-injection escaping and an export-versus-screen filter
mismatch to keep in step for it.

## Site settings and global content

Two `settings` rows, and the split is a permissions boundary rather than a filing choice. `globals`
is wording a **client administrator** edits at `/cms/global-content` — footer blurb, announcement
bar, phone. `app/Content/Site.php` is the row behind `/cms/settings`, **super administrator only**
(`settings.manage`) — the GA4/GTM ids, the legal wording, the switches set once.

The SEO defaults are the exception and they live on `/cms/seo` under `seo.manage`, so this row has two
writers. That is only safe because both go through `Site::merge()`; see "The SEO screen" above for
what the wholesale write it replaced would have erased.

Nothing is stored in both. The phone number, address and copyright line live in `globals` and stay
there; a value stored twice is a value that disagrees with itself.

`security:check [--production]` is the deployment list — the session cookie, debug mode, the proxy in
front, where media is really stored, and whether anything from a developer's machine came along — as a
command rather than a paragraph, because nothing reads a security review
at deploy time. It **reports and never enforces**: refusing to boot on a misconfiguration turns a
warning into an outage, and not every environment that runs it is production.

## The public site

`/` is the `AgentFinder` Inertia page; every other address falls through `/{path}` to
`PageController`, and `/blog/{article}` is the one other named content route.

The enquiry form posts to `/enquiries` and is CSRF-protected — which is why e2e fixtures are made in
`global-setup.mjs` instead of through it.

`/api/suburbs` proxies Google Places (New) so **the API key never reaches the browser**. Two modes:
`?q=` for predictions, `?place_id=` for the picked suburb. A Google failure degrades to an
empty-but-successful payload, never an error — the field falls back to free text, so an outage
upstream can slow the form down but can never block it.

## Dashboard

Everything on `/cms` is counted or read at the moment the page loads. It used to render invented
figures, which is worse than an empty dashboard because it reads as fact.

## Text, layouts and diffs

- **`app/Content/Text.php` sanitises before validation, not after.** Every write path used to
  validate and strip afterwards, so a value could pass `required` and then be emptied on the way to
  the database: `<hr>` as a testimonial name passed, sanitised to nothing, and hit a NOT NULL
  constraint as a 500. The rules have to see what will really be saved.
- **`StarterLayouts`** backs the layout choice when a page is created — blank, standard, service,
  landing, blog listing. It returns a section tree, so a starter is an ordinary draft from the moment
  it exists, with nothing to migrate if the layouts change. `pages:scaffold` creates the agreed page
  list from these, **as drafts** and only where the page does not exist, so it can be re-run against
  a site people are already editing.
- **`SectionDiff::between()`** flattens both trees to paths and compares, which is what the publish
  summary and the history drawer both read. `POST /pages/{page}/changes` is the same diff answered
  for an unsaved editor state.
- **`duplicate()` copies into a draft and nothing else.** It takes the source's draft — or its
  published tree if there is no draft — plus the SEO block, and takes **no revision history**: a copy
  has not been published, so a history saying otherwise would offer restores to versions of a
  different page.

## Accounts and permissions

`/cms/*` requires an active account. Scope §2's two roles live in one place —
`app/Auth/Permissions.php` — as an ability map and a module map; the `permit:{ability}`
middleware, the `Gate` definitions in `AppServiceProvider`, and the sidebar's shared
`auth.modules` prop all read from it. Nothing else should hard-code a role name.

Client administrators create, edit, publish and unpublish content, and reach `/cms/seo` — its own
`seo.manage` ability, since the report and the two fields it patches are things they already write in
the builder. Super administrators additionally delete content, restore archived pages, manage accounts
and reach settings.
Deleting anything is therefore a super-admin route — the scope never gives client users a
delete, only disable and archive.

`Tests\TestCase` signs in a super administrator for every test, so existing suites need no
auth setup; `PermissionsTest` and `AuthTest` sign in as somebody else, or nobody.

Accounts are made with `php artisan cms:user email --name= --role= [--password=]`, which
generates and prints a password when none is given (a supplied one is held to `PasswordPolicy`). `UserSeeder` creates local development
accounts with a shared password, and **refuses to run when `APP_ENV=production`** — it and
`SampleContentSeeder` share the `DevelopmentOnly` trait for that. Saying so in a docblock was not
enough: `UserSeeder` matches on email with `updateOrCreate`, so `db:seed --force` on a live site
would not have added a test account, it would have reset the real site administrator's password to a
well-known word and cleared `password_changed_at` on the way past. It reports and returns rather than
throwing, so a seed run stops being destructive without becoming an exception somebody forces.

Everyone changes their own password at `/cms/account`; only super administrators set anyone
else's. Both paths end sessions on other devices, via `auth.session` on the `/cms` group plus
`Auth::logoutOtherDevices()`. Two ordering traps live there, both covered by `AccountTest`:
the plaintext must be handed to `logoutOtherDevices()` **after** the save, and when the target
is the acting user the guard needs `Auth::setUser()` first — it caches its own instance and
would otherwise compare against the hash that was just replaced.

The session settings a deployment has to choose are in "Running it in production", with the rest of
what a server needs, rather than stated twice here.

### What a password has to be

`app/Auth/PasswordPolicy.php` states it once — ten characters, mixed case, a number, a symbol,
and not in the public breach corpus — and the two form requests plus `cms:user` all read it from
there. The command used to accept any `--password` unchecked, which made it the one way round
the rule. `resources/js/cms/passwordPolicy.js` mirrors the rules to draw the live checklist under
every field where a *new* password is typed; that copy is guidance, the PHP decides, and
`PasswordPolicyTest` runs the same five refusals through all three paths so they cannot part
company. Sign-in is deliberately not held to the policy — it verifies a password that already
exists, and refusing an old one there would lock people out of the screen that fixes it.

`users.password_changed_at` answers a different question: **was this password chosen, or issued?**
Only changing your own at `/cms/account` stamps it. A password set for somebody else at
`/cms/users`, by `cms:user`, or by `UserSeeder` leaves it null, and null is what raises the
warning strip on every CMS screen (`auth.mustChangePassword`, shared like `auth.can`). So the
seeded `password` account is told to replace it, on arrival, until it does. The strip is not
dismissible: the way to clear it is to change the password.

Two things that follow from it. The **factory stamps `now()`** — otherwise every feature and
browser test would carry the warning, and the assertions about screens would be asserting about
this. And a super admin who changes their *own* password at `/cms/users` is choosing, not being
issued, so that path stamps it when the target is the acting user — the same `is()` check the
session guard there already needs.

The layout strip is `.cms-impact-banner--global`, outside `.cms-page`, so a screen with its own
`.cms-impact-banner` now has two: the e2e tests for Settings and Global content scope theirs to
`.cms-page .cms-impact-banner` for that reason.

## Header search and the bell

Both were painted-on: an input that swallowed keystrokes and a notification dot wired to nothing.

`app/Cms/Search.php` answers `GET /cms/search` as JSON — Inertia would put every half-typed word in
the browser's history. Six sources, each capped at five, each result a link. Bodies are searched but
never returned, and an **enquiry's message is not searched at all**: somebody's account of their own
circumstances is not an index for a colleague to browse, so only the sender's name, email and suburb
match. Terms shorter than two characters search nothing.

Two traps live in that file. `LIKE` needs an explicit `ESCAPE` clause — SQLite has no default escape
character, so escaping `%` without declaring one leaves the wildcard live and searching for "50%"
matches every row. And a page's builder link must be built from **`cms_id`, not `id`**:
`CmsPageController::edit` resolves through `findByCmsId`, so a link of the right shape built from the
primary key 404s. `SearchTest` follows every link rather than pattern-matching the href, which is the
only reason that second one is caught — a regex on the URL passes happily while the link is broken.

`app/Cms/Notifications.php` feeds both the bell and the sidebar as one shared Inertia prop, and only
on `cms.*` routes — the public site shares that middleware and should not pay for the counts a page
view. It answers **two different questions, and they must not be merged**:

- **The bell** counts enquiries nobody has opened (`Enquiry::unread()`). Opening one clears it, so it
  can honestly reach zero.
- **The sidebar** counts work still outstanding — `Enquiry::outstanding()` and published pages holding
  a draft, the latter by the same rule `PageContentStore` uses for the "Unpublished changes" filter,
  so the sidebar and that screen cannot disagree. Nothing marks these read.

That split is the whole design. A badge you can clear by glancing at something must never be the one
reporting how much work is left, which is why reading an enquiry moves the bell and never the
sidebar — `NotificationsTest::test_reading_one_clears_the_badge_but_not_the_work` pins it. Both are
still derived: there is no notifications table, and `read_at` lives on the enquiry, so an enquiry read
by one person is read for the whole inbox. That is deliberate — it is a shared inbox, not a mailbox
each.

Sidebar counts key off `notifications.counts` by nav id. `constants.js` used to hardcode them empty
because it had no way to know a true figure; it still does not, which is why they come from the prop.

## Paging the admin lists

`app/Cms/Listing.php` is the seam: count, clamp, slice. Rows stay a **flat array** and the paging
facts ride alongside in a `pagination` prop — handing the front end a paginator object would rename
every list prop to `.data` for nothing. `perPage()` reads an **allowlist** (25/50/100), never the
number that arrived, or the size selector becomes a way to ask for the whole table. The page is
clamped to `1..lastPage`, so deleting the last row on the last page cannot strand anybody.

`resources/js/cms/components/Pagination.jsx` renders nothing while everything fits on the smallest
page, and must be a **sibling after** a list, never inside one — both list containers clip their
overflow to keep their rounded corners.

Two consequences that are easy to get wrong:

- **Search had to move to the server.** Both screens filtered the loaded array, which with paging
  searches one page and reports the rest as absent. `app/Cms/Like.php` holds the escaping, including
  the `ESCAPE` clause SQLite needs — and it is a scan, not an indexed lookup.
- **Deep links must not be resolved against the rows on screen.** `?open={id}` and `?selected={id}`
  send the whole record from the server, because the thing linked to is routinely on another page or
  outside the current filter.

Only Enquiries and Media page so far. Pages, Blog, FAQs, Testimonials and Users still load every row.

## Enquiries

`status` (`new` / `in_progress` / `dealt_with`) replaced `handled_at`, which was one boolean wearing a
timestamp — there was no way to show something had been picked up without claiming it was finished.
The old column was dropped rather than kept beside the new one: two columns that can disagree, with no
rule saying which wins, is how a screen reports one thing and a count another. `read_at` is a separate
question and deliberately not the same column, because read is not answered.

An enquiry opens in a modal deep-linked at `/cms/enquiries?open={id}`, the same pattern as the media
library's `?selected={id}`. Opening it marks it read, but **not on the GET** — `?open=` only decides what
is on screen, and the front end then posts `/cms/enquiries/{id}/read`, once, when an unread one is
actually put in front of somebody. (This section used to say the write happened in `index()`; it does not,
and `CmsEnquiryTest` asserts the GET leaves `read_at` alone.) The header's counter is a closure resolved
after the controller returns, so the badge falls in that same response, and the read POST names
`notifications` in its partial reload for the same reason.

Setting the status is still its own single-key route, not an `update()`. The reason has not changed:
the name, email and message are the sender's words, and a general endpoint here would be an
editable-enquiry endpoint by construction, whatever the request happened to carry.

### What the thing is called

**Agent Finder** is the service — a noun, and what the site, this file and the CMS call it.
**Find My Agent** is an instruction, and belongs only on a control a visitor presses.

The feature answered to four names before that was written down, and the one that mattered was in the
inbox: a tab reading "Find My Agent" beside "Contact form" put a verb where a noun belongs, when both
are answering the same question — where did this come from. One CMS line had invented a third, "the
agent-finder form".

Everything below the surface keeps the name it has: `find_my_agent` on a row, `open-finder` on a button,
the `AgentFinder` page, `FindMyAgentModal`, `/why-agent-finder`. Those are identifiers, and renaming
them costs a migration or a public address for nothing a reader would see — the whole point of storing a
key and resolving a label is that wording can move without data moving. "Wizard" survives in comments
and test names as a description of its shape, four steps held together by React state, not as its name.
The reference a sender quotes was already `AF-2026-00042`.

### Which form it came from

Two forms write this table: the contact form section, and Agent Finder. `source` says which
— **a column, not a reading of `page_slug`**, because the slug records the address the form sat on, it
arrives from the browser, and both forms appear on `/contact`. Every row that predates the column did
come through the contact form, since nothing else could write here, so the backfill is a statement of
fact rather than a default nobody set.

**One route, one throttle.** Both post to `/enquiries` (`throttle:6,1`), and `StoreEnquiryRequest` turns
the wizard's extra rules on when the payload says so. A second endpoint would be a public write path
`OwaspTest` does not know exists — so its rate-limit test now sends the seventh request as a wizard
payload, which is the whole payoff of the decision.

**`details` holds what they picked; `message` stays what they wrote.** The wizard asks four questions
with fixed answers, and they live in a JSON column as **keys, never wording** — the labels are resolved
for the screen by `app/Enquiries/FindMyAgentOptions.php`, so re-labelling an answer never rewrites a row.
Composing them into `message` was rejected: the list snippet and the search palette both treat that
column as the sender's own account, and every wizard enquiry would have opened with the same boilerplate.
Discrete columns were rejected too — null for every contact-form row, and a migration per new question.

The catalogue exists twice, in PHP and in `resources/js/components/findMyAgentOptions.js`, because the
server validates it and the browser draws it. `FindMyAgentOptionsParityTest` reads the JavaScript and
holds it to the PHP; without that the server would refuse an answer the form had just offered.

**The wizard used to send the position of the chosen card.** An index makes the order of a JavaScript
array the meaning of every answer already stored — reorder the cards and history is silently rewritten,
with no test that could notice. `OptGrid` reports `o.value` now, and a test rejects an integer where a
key belongs so the old wire format cannot come back.

The reference the sender is told to quote is **derived, never stored**: `AF-{year}-{id}`. Nothing to keep
in step, and it leads straight back to a row this CMS can open.

### The inbox separates them with tabs, not badges

A segmented strip — All / Contact form / Agent Finder — and **no source badge on the rows**. The rule
it follows is already in `cms.css`: the status badge and the unread rule are as many markers as one row
should compete with, and on a source tab every row *is* that source, so the row has nothing left to say.

They are **links in a `role="group"`, not ARIA tabs**. Each one is a real address a colleague can be sent,
and pressing it fetches a page rather than swapping a panel beside you, which is what `role="tab"` would
promise. `aria-current` marks the active one.

Three things that follow, and each was a way to mislead somebody:

- **The counts are source-scoped.** They sit on the status filter, which sits inside the tab, so left
  whole they would say "waiting for a reply (12)" above three rows. The `counts` prop keeps its exact
  shape — a test pins that, and it is deliberately left untouched as proof nothing moved. Search is not
  applied to them: the pager already says what a search found.
- **`params()` has to carry `source`.** Anything missing from that object is dropped by the next visit,
  so leaving it out sends searching, paging, changing a status and opening a row all back to every form.
- **Both filters are allowlisted and echoed back normalised.** `?show=` had no allowlist: a mistyped
  value fell through to "everything" while the control showed nothing chosen. Harmless until a tab strip
  renders it as a row of unlit buttons, which reads as a broken screen rather than a bad address.

A wizard enquiry's answers print in the modal as an unboxed `<dl>` above the message, under "In their own
words". Unboxed on purpose — nothing on this screen may be edited, and a bordered field on a pale fill
reads as one you could type into; the e2e suite asserts **zero** inputs in that modal, answers and all.
Notes are optional there, so `snippet()` falls back to the picked answers rather than leaving a row as a
name and a time among rows that all carry a sentence.

### An open inbox hears about an arrival

The screens are server-rendered, so a tab somebody left open used to keep showing what it fetched when
they opened it. Laravel Reverb closes that: `EnquiryReceived` is broadcast on a private `cms` channel
and any open CMS screen refreshes itself.

**The message carries nothing.** Not the name, not the suburb, not the first line — `broadcastWith()`
returns an empty array, on purpose. A payload would put somebody's account of their own circumstances
into a queue record and a socket frame, delivered to every signed-in browser whether or not anyone is
looking at the inbox; the search palette already refuses to index that message for the same reason. So
the event is a nudge, and the browser refetches through `/cms/enquiries` — authorised, filtered and
paged exactly as when somebody presses reload. One path to the data, and nothing to keep in step with
the shape of the props.

Four things that follow, each of which was a way for this to fail quietly:

- **`connect-src` has to name the socket**, as `ws://` or `wss://` — naming the `http://` origin it
  upgrades from does not permit it. Blocked, the only symptom is an inbox that has gone back to
  updating on reload. `OwaspTest` pins both the permission and its absence where no key is configured,
  and `security:check` fails a production environment still on `ws://`, since a plain socket on an
  HTTPS page is blocked as mixed content.
- **Echo must build its own client.** Handing it a pre-made Pusher instance keeps Pusher's defaults,
  which authorise a private channel at `/pusher/auth` — an address this application answers with a 405
  from the catch-all page route. The socket connects, the subscription is never authorised, and nothing
  is ever delivered, with no error worth reading anywhere in the sequence.
- **The dispatch cannot be allowed to cost an enquiry.** It is queued, so an unreachable Reverb is a
  failed job; and it is wrapped, because on a `sync` queue the broadcast happens inside the request
  that just saved somebody's enquiry and would otherwise answer them with a 500 after keeping it.
- **The rule about who may listen lives in `app/Broadcasting/CmsChannel.php`, not in a closure.**
  Testing it through `/broadcasting/auth` proved nothing: under the `null` broadcaster this suite runs
  with, the endpoint answers without consulting the callback, so every channel refused every caller and
  the denial tests passed vacuously. The rule mirrors `Permit` — an active account with
  `content.manage` — because a socket outliving a deactivation is a way back into the screens the
  account was locked out of.

The list holds still while an enquiry is open: rows behind a modal are what somebody is about to click,
and re-ordering them under a dialog is how the wrong person's message gets opened. The bell still moves,
so nothing is hidden — only deferred until the modal closes, which visits the list anyway.

Running it needs `php artisan reverb:start` **and** a queue worker. Without either, the CMS behaves
exactly as it did before any of this: the inbox updates when somebody looks at it. `.env.e2e` sets
`BROADCAST_CONNECTION=null` deliberately — the browser suite runs a synchronous queue, so a broadcast
would happen inside the request and the run would depend on a socket server being up to pass.

**No confirmation email exists, and step 4 no longer claims one.** The wizard used to promise one and show
a reference that was the same five digits for everybody, while storing nothing at all. There is no
`app/Mail` in this repository — nobody internal is notified of a new enquiry either, which is arguably the
more urgent half. Wizard submissions are also deliberately **not** written to the activity log:
`Activity::labelFor()` falls through to `name`, and that log has no delete path, which is exactly what
`OwaspTest`'s a09 test protects against.

## Limits, and what is not kept forever

**`config/limits.php` holds every number and `app/Http/Limits.php` says why each one is that number**,
against the screen it was measured on. A limit with no reasoning beside it is one somebody tightens
later, and the symptom — a save button that stopped working — reads as a bug rather than a policy.

They are not an access control: `permit:` is that, and it runs first. These bound a runaway script, a
stolen session and a stranger with a word list, so they sit where honest use never reaches them.

**Signed-in limits key on the account, public ones on the address.** An office shares one address, so
keying the CMS on it would refuse the second person to save because of the first. `/media` keys on
whichever exists, since the library grid and the public site share that route.

Three numbers are deliberately unlike their neighbours:

- **Uploads get 180 a minute, not 60.** Each picture is a sign and then a store, so dropping forty
  images is eighty calls — the low number that looks prudent on paper ruins a real afternoon's work.
- **Media gets 600.** An image-heavy page asks for dozens at once, and a limit that bites leaves a
  reader looking at broken pictures with nothing to explain them. It exists to stop somebody walking
  the whole library, not a browser.
- **`/up` gets none at all.** A 429 on a health check is a supervisor restarting a healthy
  application — a limiter causing the outage it was added to prevent.

**A refusal has three audiences.** The admin gets its error back through Inertia, where the builder
already toasts and leaves the draft unsaved. A reader gets `errors/429.blade.php`, which says we are
busy in words and never prints the number. And `POST /enquiries` stays a **plain 429 on purpose**:
both public forms treat a request that neither succeeded nor failed as "we could not send that just
now", and a redirect would arrive as a *successful* Inertia visit and thank somebody for an enquiry
that was never saved.

**None of it means anything until a proxy is trusted.** `TRUSTED_PROXIES` is read in
`AppServiceProvider` — not in `bootstrap/app.php`, where configuration does not exist yet, and not
through `env()`, which is empty once config is cached. Without it every visitor is the proxy, six
enquiries a minute becomes six for the whole internet, and `$request->secure()` stays false so the
HSTS header this application has tested since the security review has never once been sent.
`security:check --production` fails on it.

Sign-in has its own counter in `LoginRequest`, five attempts keyed on email and address, and the decay
is **five minutes rather than the default one** — at sixty seconds, five wrong guesses buy a pause and
then five more, which is three hundred an hour for ever. `RecordSignInTrouble` logs failures and
lockouts **to the log file and never to `activity_log`**: that email is unverified, belongs to somebody
who is not a user here, and the audit table has no delete path — the same rule that keeps a deleted
enquiry's name out of it. The account id goes in when the address matches somebody real, a hash when it
does not, which still answers "one account or five hundred".

**Personal data now has an end date.** `enquiries:purge --months=24` and `activity:prune --months=24`
report by default and need `--force`, run by hand like everything else here that destroys something.
`enquiries:erase {email}` answers somebody asking to be forgotten — every row for that address,
whatever case they typed it in. All three record *that* something went without recording whose it was.
Nothing is encrypted at rest, deliberately: the inbox searches name, email, suburb and message, and an
encrypted column cannot be searched.

## The security suite

`tests/Feature/Security/OwaspTest.php` names every test by OWASP category (`test_a01_…`), and it is
one file on purpose: the alternative is a security assertion in whichever suite happened to touch the
route, where nothing says which category has no cover at all. (This sentence used to carry a count of
them, which was wrong within a fortnight — the categories are the point, not the total.)

What it holds that lives nowhere else: that **every** CMS route refuses a signed-out visitor and no
delete route is open to a client administrator (both derived, so a new route is covered the day it is
added); that a wrong password and an unknown account answer identically; that the content policy both
blocks what a policy is for **and permits the upload it signs** — the pair that caught `connect-src`
killing every upload; that a wildcard in a search stays a literal; and that the one endpoint making
an outbound request on a visitor's behalf cannot be steered.

Its A04 section is where the limits are pinned, and each test names the thing it protects rather than
the number it uses: that two editors behind one address do not share a bucket, that a page's worth of
images is not turned away, that the health check is never limited, that a refusal tells a reader when
to come back and is never mistakable for success, and that a locked-out sign-in is recorded without
the address. They set their own ceilings through `config('limits.…')`, which is how a limit is proved
in three requests instead of a hundred and twenty-one.

It is also where `security:check` is tested against a production misconfiguration, so the deployment
list cannot rot.

## Running it in production

> **Read this before the first deploy.** Everything in it fails quietly: the site renders blank, or the
> inbox stops updating, or a worker runs last week's code — and none of it raises an error anybody
> will see. `php artisan security:check --production` is the same list as a command.

**There is no production equivalent of `composer dev`, and there should not be.** That command exists
to make one laptop convenient; a server has a release step and a set of processes something else keeps
alive. Nothing here is automated yet — no pipeline and no deploy script.

The release step:

```sh
composer install --no-dev --optimize-autoloader
npm ci && npm run build
rm -f public/hot
php artisan migrate --force
php artisan config:cache && php artisan route:cache && php artisan view:cache
php artisan queue:restart
php artisan inertia:stop-ssr || true
php artisan security:check --production
```

And three processes, each under a supervisor that restarts them on failure and on boot — systemd or
supervisord on Linux, a service wrapper on Windows:

| what | how | what happens without it |
|---|---|---|
| the site | nginx or Apache with **PHP-FPM**, serving `public/` | `artisan serve` is PHP's built-in server: one request at a time, and it is a development tool |
| the queue | `php artisan queue:work --tries=3 --max-time=3600` | enquiries still arrive and are still kept; nothing tells an open CMS screen about them |
| the socket | `php artisan reverb:start --host=0.0.0.0 --port=8080`, behind the proxy that terminates TLS | the same: the inbox updates when somebody looks at it |
| the renderer | `php artisan inertia:start-ssr` — a unit file is in `deploy/seniors-ssr.service` | the site still works, and serves a body with no heading and no links — see below, because this is the quietest failure here |

Neither of the last two can lose an enquiry — the notice is queued and the dispatch is wrapped, so a
dead worker or an unreachable socket is a failed job, never a visitor's error page.

**Docker is not part of any of this.** `docker-compose.yml` runs `floci`, an S3-compatible emulator on
`:4566`, and it exists for a developer's machine and the browser suite — it is never deployed. A server
points `AWS_*` at real object storage instead, and the only thing that has to be true of that bucket is
the thing the emulator needed too: **CORS has to allow a presigned PUT from the site's own origin**, or
every upload fails at the browser with a message about storage being unreachable while storage is
perfectly well. `php artisan media:init` applies it and is safe to re-run — it reads whichever endpoint
is configured, so it is a deployment step against a real bucket exactly as it is a setup step locally.

The way it reaches a server anyway is `.env`. **`.env.production.example` is the file to copy**, not
`.env.example` — the local one carries `AWS_ENDPOINT=http://localhost:4566` and the emulator's dummy
credentials, and a server provisioned by copying it and filling in only the obvious blanks keeps them.
Uploads then land in a container's volume with no versioning and no backup, and **nothing looks wrong**:
the `s3` disk is configured `'throw' => false`, so storage that is not there behaves like storage that
is. `security:check --production` refuses a loopback endpoint, and refuses port 4566 on any host —
because the emulator reached over a real hostname is still the emulator, and that is the shape a
staging box takes when somebody runs floci on the server to make uploads work.

`AWS_ENDPOINT` is therefore **absent** from `.env.production.example` rather than blank-and-commented:
unset is what selects AWS. A non-AWS provider is still supported — an endpoint on a real host and port
passes.

### The first deploy, in order

The release step above assumes a server that has already run one. The first one has an order, and two of
these steps are safe to do and unsafe to skip:

1. **Copy `.env.production.example`, not `.env.example`** — the second carries the storage emulator's
   endpoint and dummy credentials, and a server that keeps them stores uploads in a container's volume
   while looking perfectly well.
2. `php artisan key:generate`, then fill in the database, the `AWS_*` values and `TRUSTED_PROXIES`.
3. `php artisan migrate --force`, then `php artisan db:seed --force` — **on an empty database only.**
   `UserSeeder` and `SampleContentSeeder` refuse to run in production, so this installs the client's pages
   and settings and nothing else. On a site that already has content this is the wrong command; see
   `seo:apply` for changing metadata on a live site.
4. `php artisan cms:user you@example.com --name="Your Name" --role=super_admin` — it prints a password.
5. `php artisan media:init` against the real bucket, which applies the CORS rules a presigned upload needs.
6. The release step, then `php artisan security:check --production` until it is silent.
7. **Server-side rendering is off in the template on purpose, and is the last thing to turn on.** Install
   `deploy/seniors-ssr.service`, `systemctl enable --now`, set `INERTIA_SSR_ENABLED=true`, re-run
   `config:cache`, and then prove it rather than believing it:

   ```sh
   curl -s https://your-domain/how-it-works | grep -c '<h1'
   ```

   One means it is working. Zero means it fell back — and the page still looks perfect in a browser, which
   is the whole reason this is the step people think they have done.

Leaving it off is a supported state, not a broken one: `security:check` passes, and everything else on the
site — the metadata, the structured data, the sitemap — is unaffected. What is lost is only that anything
which does not run JavaScript reads a blank page.
The rest, each of which fails quietly rather than loudly (no count, because this list grows):

- **`public/hot` must not exist on the server.** It is how a developer's machine says "assets are
  coming from Vite"; copied to a server, every page asks a dev server that is not there and renders
  blank, with the reason only in the browser console. Hence the `rm -f` above, and
  `security:check` fails on it — reading the hot file Laravel itself would use, not a fixed path.
- **`REVERB_SCHEME=https`**, with the proxy exposing the socket as `wss://`. A plain `ws://` socket on
  an HTTPS page is refused as mixed content and the CMS silently stops updating. `security:check
  --production` fails on this, which is the only reason anybody would notice.
- **`php artisan queue:restart` after every release**, or workers go on running the code they were
  started with. **`inertia:stop-ssr` is the same sentence about the renderer** — it holds the bundle it
  started with, so without this readers are served last week's pages by a process nobody restarted. The
  gap before the supervisor brings it back renders in the browser, which is what the site did before SSR
  existed, so there is no outage in it. **`|| true` is not decoration**: the command exits 1 when there
  is no renderer to stop, which is the normal state of a first deploy and of any server running with SSR
  off — and a release script with `set -e` would abort there, having already built and migrated. Found by
  checking the exit code rather than the message; the first measurement said 0 because the pipe to `tail`
  was reporting its own success.
- **The renderer fails silently and looks fine.** A missing bundle, a dead process, or
  `INERTIA_SSR_ENABLED` never reaching the server's `.env` all end the same way: Inertia answers `null`,
  the browser draws the page, every screen looks right, and the delivered HTML quietly goes back to
  having no heading and no links. `security:check --production` fails on a missing bundle — note the
  bundle is **gitignored**, so a release that copies only tracked files loses it — `app/Listeners/RecordSsrFailure.php`
  logs every failed render with the component and the browser API that caused it, and
  `php artisan inertia:check-ssr` answers by hand. Three defences for one fault, because nothing else
  would ever tell you.
- **`SESSION_SECURE_COOKIE=true`** and a deliberate `SESSION_LIFETIME`, neither of which belongs in a
  local `.env` — see `.env.production.example`, and `security:check` again.
- **`AWS_BUCKET` and the credentials must be filled**, and `AWS_ENDPOINT` left unset. Blank credentials
  are the same silence as a wrong endpoint: every upload fails at the browser and the log says nothing.
- **`TRUSTED_PROXIES` must name the proxy**, or every rate limit keyed on a visitor collapses onto one
  bucket and HSTS is never sent. `CACHE_STORE=file` too, on a single server: the limiter counts in the
  cache, and counting in SQLite takes a database-wide write lock on every throttled request.

Rotating the socket credentials needs no rebuild: the browser is told what to connect to by the server
that drew the page, not by a value baked into the assets — see `app/Cms/Realtime.php`.

## Browser tests

`e2e/` drives the **CMS admin** through Chromium. It covers what PHPUnit cannot see: that a screen
renders, that a form's save button is reachable and enabled, and that what was typed comes back
after a reload.

Its database is its own — `database/e2e.sqlite`, rebuilt from scratch by `e2e/global-setup.mjs`
on every run. `APP_ENV=e2e` must go in the **server process's environment**, not on the command
line: `php artisan serve` forwards a whitelist of variables to the server it starts and drops
`--env`, so `serve --env=e2e` quietly runs the site against the developer's own database. That is
how three test enquiries once landed in `database/database.sqlite`.

Its **bucket** is its own too — `spa-media-e2e`, because the upload test generates objects nothing
prunes and they should not accumulate in the bucket used for development. `global-setup.mjs` runs
`media:init` **before** the seed, which creates it and applies the CORS rules a presigned PUT needs.
That ordering is the point: `MediaSeeder` swallows a storage failure with a warning, so without the
preflight a missing container let the run continue and failed several tests as though their screens
were broken. The suite has always needed `docker compose up -d`; now it says so and stops.

**Two concurrent runs corrupt each other, and not via the port.** The port clash is the visible half
— `webServer` is `reuseExistingServer: false` deliberately, so the second run refuses to start. The
damaging half is that `npm run e2e` builds first, and Vite empties `public/build` before rewriting it:
any page the *other* run renders in that window dies with `ViteManifestNotFoundException`, which
Playwright reports as a blank screen and a missing button. If a handful of unrelated builder tests
fail with nothing in common, check whether a second build ran — `public/build/manifest.json`'s
timestamp against the run's start answers it.

Three constraints shape the suite, and all of them are load-bearing:

- **One worker.** `artisan serve` is PHP's built-in server — one request at a time, and it cannot
  fork on Windows. A second worker deadlocks the moment one page waits on an Inertia POST.
- **One sign-in.** `/login` is throttled at ten attempts a minute, so `auth.setup.js` signs in once
  and every test reuses the cookie. The tests that need a signed-out or client-administrator
  browser opt out with `test.use({ storageState: … })` and sign in themselves.
- **No media bytes unless a test is about them.** Every image is streamed out of storage by PHP,
  and against a one-request-at-a-time server a visit to the media library leaves a request per image
  in flight with everything else queued behind: measured at **46 seconds for three navigations with
  images against 2.6 without**. `e2e/fixtures.js` aborts `**/media/**` for every test; the one test
  that checks a thumbnail calls `withImages(page)` and pays for it. Nothing about production —
  a real server answers them concurrently and they carry a year-long immutable cache.

Navigation waits on `domcontentloaded`, not `load`, for the same reason. What is being asserted is
that a screen arrives and renders, and the shell being visible says that better than a load event.

Fixture data that a route would refuse is made in `global-setup.mjs` rather than through the
application — the public enquiry form is CSRF-protected and an API request context carries no token,
so posting to it would fail for a reason having nothing to do with the test.

### How it is arranged

`e2e/sidebar/` holds one numbered file per sidebar module, in sidebar order, each opening a
`describe` named after the module so the report reads the way the menu does. `e2e/cross/` holds what
spans all of them — sign-in, the search palette, the content policy. `e2e/support/` holds the parts
that are awkward enough to be worth writing once.

**The block tests are generated from the application's own schema.** `contentFields.js`,
`repeaters.js` and `COMPONENT_LIBRARY` are pure data, so `04-pages-blocks.spec.js` imports them and
writes a test per field. Add a field to a block and it is covered that day. The objection — that a
test derived from the schema agrees with the schema — is answered by what it asserts: the typed
value has to survive a save **and a reload**, which is true or false whatever the schema says.

Three things about the builder are worth knowing before touching those tests:

- **Settings fields have no `id`, `name` or associated label**, only visible text — and "Heading",
  "Highlighted heading" and "Heading level" all contain one another. `support/builder.js` matches on
  exact text for that reason; substring matching silently picks the wrong field.
- **Only the Content accordion is open on arrival.** The Layout, Style, Responsive and Advanced
  inputs do not exist in the DOM until their heading is clicked.
- **Nothing in the canvas is clicked — blocks included.** The canvas fades in, re-measures its
  height and is drawn under a CSS `scale()`, so a real click is delivered to whatever occupies the
  coordinates and Playwright's stability check never settles. `toolbar`, `selectBlock` and
  `selectLastBlock` all dispatch. The three specs that still called `.cms-block').last().click()`
  passed for as long as the fixture pages were tall enough for the geometry to agree: trimming
  Contact to a single section made one of them select nothing, and it surfaced two steps later as
  "the selected block's toolbar has no Delete button" — which reads as a broken builder rather than
  a missed click.

**Dropping a block inside another goes through `support/dragShim.js`.** The canvas uses the native
HTML5 drag API, which Playwright cannot drive; the shim dispatches the events itself. It works
because the builder keeps its drag state in React refs and uses `dataTransfer` only for
`effectAllowed` — the events must arrive, not carry anything. A passing drag test is weaker evidence
than a passing click test, and it is the first thing to suspect if the drag code is rewritten.

`npm run e2e:fast` skips the generated per-field tests (`@deep`); the full sweep is for before a
merge.

The public site is otherwise out of scope here; it is rendered from data the PHPUnit feature tests
already assert. **`e2e/public/` holds the one exception, and it was paid for.** Agent Finder is four
steps held together by React state, and renaming its options catalogue left one
`options={TIMES}` behind: step 3 threw a `ReferenceError` the moment anybody reached it. `npm run
build` was clean, 699 PHPUnit tests were green, the CMS suite was green, and the form was broken for
every visitor — because nothing had ever pressed the buttons. A page that only breaks when somebody
uses it needs a test that uses it.

What the suite covers beyond the screens loading: the enquiry inbox including the bell and sidebar
counts disagreeing on purpose, the search palette including that a page's link resolves through
`cms_id`, and that **no screen violates the content security policy** — a blocked script does not
error a response, so without this nobody would notice until something silently stopped working.

One more lesson from the same afternoon: **assert the effect, not the marker.** The test for the
inbox's source tabs checked `aria-current` and passed while the active tab was navy text on a navy
fill — correct in the accessibility tree, invisible on screen. Comparing the two colours for
inequality was not enough either (`rgb(27,58,105)` on `rgb(18,41,76)`), so it measures the contrast
ratio an eye would see.

That last one covers screens, and screens alone, which is why `09-media.spec.js` performs a **real
upload** and asserts each step of it: sign, a cross-origin PUT with a 2xx, the record call, and the
file still being findable after a reload. An upload is the one thing the CMS does that leaves the
origin — the browser PUTs the bytes at storage itself — so it is the one thing the screen sweep
structurally cannot see. It was worth writing: it found `connect-src` blocking every upload in every
environment, and a second violation behind that one, within a minute of first running. Asserting the
final button alone would have proved neither — that can pass on a path that never leaves the origin.

### Three traps that cost hours, written down so they do not again

**`cmsField`'s inner locator is built from the page, not from the scope.** Playwright bakes a
locator's own selector into anything used as `has:`, so building it from `scope` produced
`.cms-field >> .cms-modal .cms-field-label` — matching nothing. It worked wherever the scope
happened to be the page and failed only inside modals, which made it look like those *screens* were
broken. Five tests, one helper.

**The media fixture blocks `/media/` paths, not `**​/media/**`.** The glob also swallowed
`/cms/media/usage` — the request the library makes before it will let anything be deleted — so the
delete dialog never appeared and the test read as a broken screen.

**A switch is not inside a `.cms-field`.** `SettingsPanel` renders it as its own `.cms-toggle-row`
with its own label class, so `field()` — which searched `.cms-field` alone — resolved to nothing for
every toggle. The generated switch tests read the empty result as the block having no switch and
skipped themselves, reporting "no switch rendered" about five blocks that render one perfectly well.
They had never asserted anything, and the suite said `5 skipped` on every run for as long as they
existed.

The lesson the first two share: when a probe passes and the test fails, the difference is in the test.
The third adds the quieter half — **a skip is a test declining to answer, so a standing count of them
is a standing question.** Anything conditionally skipped must say what it looked for, or it reports a
broken harness as a property of the thing under test.

## Current state

The public site renders from the database, and the builder is functional: undo/redo,
draft and per-version preview, restore-to-draft, reusable sections, and a real change
summary on publish and in the history drawer.

**Every CMS screen is real.** The line that used to sit here said the dashboard, blog, testimonials,
navigation, global content and settings were prototypes rendering static props from `mockData.js` —
that file was deleted several features ago, and each of those screens reads and writes the database.
Puck was never installed and is not going to be; the builder is the application's own.

A note in a file nobody re-reads outlives what it describes. That is the second time this section
has been wrong in the same way, so: if something here reads like a limitation, check it against the
code before repeating it.

The builder's image fields open a real media library — `ImageField.jsx` renders `MediaLibraryModal`
and picks against `/cms/media/library`. The note that used to sit here said `onOpenMediaPicker` was a
stub that only raised a toast; that function no longer exists anywhere in the repository, and the
note outlived it by several features. Worth remembering the next time something here says "stub".
