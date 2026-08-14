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

Run by hand, never scheduled or called from a migration: `content:import [--force]`,
`content:purge-deleted [--days=90] [--force]`, `media:init`, `media:optimise [--dry-run]`,
`pages:scaffold`, `security:check [--production]`, `cms:user`. Each says why under its own heading
below; the pattern they share is that all of them either destroy something or touch the environment,
and both are somebody's decision rather than a side effect of deploying.

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

`app/Content/Seo.php` works out the head once, on the server, and `resources/views/app.blade.php`
prints it — including the JSON-LD (Article, Organization). Two things it exists to get right:
**`og:image` has to be absolute**, and content stores media as `/media/…`, which is correct for an
`<img>` and silently useless to a crawler; and **the width and height have to be sent**, or a crawler
that has not fetched the image yet renders the small card, so the first person to share a link gets
the worse preview. The media table already knows both.

`/sitemap.xml` and `/robots.txt` are routes, not files. The sitemap filters on `status` and the
page's own noindex flag and nothing else — advertising a noindexed page asks a crawler to fetch
something it is then told to forget. It is **deliberately uncached**: two queries over a few dozen
rows against five ways to serve a stale sitemap.

## Site settings and global content

Two `settings` rows, and the split is a permissions boundary rather than a filing choice. `globals`
is wording a **client administrator** edits at `/cms/global-content` — footer blurb, announcement
bar, phone. `app/Content/Site.php` is the row behind `/cms/settings`, **super administrator only**
(`settings.manage`) — SEO defaults, the GA4/GTM ids, the switches set once. One row edited by two
screens under two permissions is how a save from one silently reverts the other.

Nothing is stored in both. The phone number, address and copyright line live in `globals` and stay
there; a value stored twice is a value that disagrees with itself.

`security:check [--production]` is the deployment list — `SESSION_SECURE_COOKIE`, `APP_DEBUG`,
`SESSION_LIFETIME` — as a command rather than a paragraph, because nothing reads a security review
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

Client administrators create, edit, publish and unpublish content. Super administrators
additionally delete content, restore archived pages, manage accounts and reach settings.
Deleting anything is therefore a super-admin route — the scope never gives client users a
delete, only disable and archive.

`Tests\TestCase` signs in a super administrator for every test, so existing suites need no
auth setup; `PermissionsTest` and `AuthTest` sign in as somebody else, or nobody.

Accounts are made with `php artisan cms:user email --name= --role= [--password=]`, which
generates and prints a password when none is given (a supplied one is held to `PasswordPolicy`). `UserSeeder` creates local development
accounts with a shared password and must never run in production.

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

### Which form it came from

Two forms write this table: the contact form section, and the Find My Agent wizard. `source` says which
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

A segmented strip — All / Contact form / Find My Agent — and **no source badge on the rows**. The rule
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

## The security suite

`tests/Feature/Security/OwaspTest.php` is 37 tests named by OWASP category (`test_a01_…`), and it is
one file on purpose: the alternative is a security assertion in whichever suite happened to touch the
route, where nothing says which category has no cover at all.

What it holds that lives nowhere else: that **every** CMS route refuses a signed-out visitor and no
delete route is open to a client administrator (both derived, so a new route is covered the day it is
added); that a wrong password and an unknown account answer identically; that the content policy both
blocks what a policy is for **and permits the upload it signs** — the pair that caught `connect-src`
killing every upload; that a wildcard in a search stays a literal; and that the one endpoint making
an outbound request on a visitor's behalf cannot be steered.

It is also where `security:check` is tested against a production misconfiguration, so the deployment
list cannot rot.

## Running it in production

**There is no production equivalent of `composer dev`, and there should not be.** That command exists
to make one laptop convenient; a server has a release step and a set of processes something else keeps
alive. Nothing here is automated yet — no pipeline, no deploy script, and `docker-compose.yml` is local
object storage only.

The release step:

```sh
composer install --no-dev --optimize-autoloader
npm ci && npm run build
rm -f public/hot
php artisan migrate --force
php artisan config:cache && php artisan route:cache && php artisan view:cache
php artisan queue:restart
php artisan security:check --production
```

And three processes, each under a supervisor that restarts them on failure and on boot — systemd or
supervisord on Linux, a service wrapper on Windows:

| what | how | what happens without it |
|---|---|---|
| the site | nginx or Apache with **PHP-FPM**, serving `public/` | `artisan serve` is PHP's built-in server: one request at a time, and it is a development tool |
| the queue | `php artisan queue:work --tries=3 --max-time=3600` | enquiries still arrive and are still kept; nothing tells an open CMS screen about them |
| the socket | `php artisan reverb:start --host=0.0.0.0 --port=8080`, behind the proxy that terminates TLS | the same: the inbox updates when somebody looks at it |

Neither of the last two can lose an enquiry — the notice is queued and the dispatch is wrapped, so a
dead worker or an unreachable socket is a failed job, never a visitor's error page.

Four things to get right, each of which fails quietly rather than loudly:

- **`public/hot` must not exist on the server.** It is how a developer's machine says "assets are
  coming from Vite"; copied to a server, every page asks a dev server that is not there and renders
  blank, with the reason only in the browser console. Hence the `rm -f` above.
- **`REVERB_SCHEME=https`**, with the proxy exposing the socket as `wss://`. A plain `ws://` socket on
  an HTTPS page is refused as mixed content and the CMS silently stops updating. `security:check
  --production` fails on this, which is the only reason anybody would notice.
- **`php artisan queue:restart` after every release**, or workers go on running the code they were
  started with.
- **`SESSION_SECURE_COOKIE=true`** and a deliberate `SESSION_LIFETIME`, neither of which belongs in a
  local `.env` — see `.env.example`, and `security:check` again.

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
- **Toolbar buttons are dispatched, not clicked.** The canvas fades in, re-measures its height and
  is drawn under a CSS `scale()`, so a real click is delivered to whatever occupies the coordinates
  and Playwright's stability check never settles.

**Dropping a block inside another goes through `support/dragShim.js`.** The canvas uses the native
HTML5 drag API, which Playwright cannot drive; the shim dispatches the events itself. It works
because the builder keeps its drag state in React refs and uses `dataTransfer` only for
`effectAllowed` — the events must arrive, not carry anything. A passing drag test is weaker evidence
than a passing click test, and it is the first thing to suspect if the drag code is rewritten.

`npm run e2e:fast` skips the generated per-field tests (`@deep`); the full sweep is for before a
merge.

The public site is otherwise out of scope here; it is rendered from data the PHPUnit feature tests
already assert. **`e2e/public/` holds the one exception, and it was paid for.** The Find My Agent
wizard is four steps held together by React state, and renaming its options catalogue left one
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
