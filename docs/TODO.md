# Outstanding work

Known gaps, in the order they are worth doing. Scope references are to the CMS development scope.

## Scope sections not yet built

Every section §1–§15 is built. §16 and §17 are checking rather than building, and that pass is
done — see `docs/acceptance.md`, which names the test proving each criterion.

Point 14 turned out to be the opposite of the worry recorded here. "Content editing does not allow
users to break the approved website layout" is enforced in four server-side layers — the type
allowlist, the nesting rules, `SaveSectionsRequest::checkTree()` and `sanitise()` — with about
twenty negative tests already behind it. It is met, and the write-up says why so the conclusion can
be re-checked rather than trusted. Both §17 exclusions that are recoverable from this repo already
have named guard tests.

Two things keep that document provisional:

**The scope is not in the repo,** so §16's fifteen statements are reconstructed rather than quoted
— only point 14's wording is known. Put the scope at `docs/specs/cms-scope.md` and diff it against
the matrix; until then a criterion that exists in the scope and not in the matrix stays invisible.
`docs/acceptance.md` names the rows most likely to be wrong.

**Two rows are not clean.** Row 12, enquiries, is a gap for the reason below. Row 15, the
responsive admin, is met but has no automated test and still cannot be reordered by touch.

## Decisions waiting on somebody

**SVG uploads are never inspected.** Only a super administrator can upload one and it is served
with `nosniff` plus a locked-down CSP, so script inside it cannot execute — but the file itself is
trusted, and that safety rests entirely on those headers surviving whatever CDN or proxy ends up in
front of the route. Either parse the XML and reject scripts, or drop SVG support.

**Page revision restore is not behind `content.restore`.** A client administrator can roll a page
back to an old version but cannot unarchive one, which contradicts §2's split. Which way to resolve
it is a permissions decision.

## Gaps in what is built

**Enquiries cannot be read.** `/contact` stores enquiries and shows the editor's confirmation, but
there is no screen listing them, so seeing one means opening the database. The `handled_at` column
exists for a mark-as-handled control that was never built. §12 puts enquiry handling in CRM scope,
so this is defensible — but until SyncID exists, an enquiry that nobody can see is close to an
enquiry lost. Nothing emails anyone either: an enquiry arrives silently.

**Look for a third write-only field.** Two editable fields turned out to save and never render: the
media caption and the contact form's confirmation message. A third of the same shape has since
turned up in a different place — `enquiries.handled_at` existed but was not fillable, so marking an
enquiry dealt with would have quietly done nothing. All three were found by accident. Worth walking
the field schemas against what each section renders, and the model `$fillable` lists against their
columns, once, deliberately.

**The admin header's search box and notification bell do nothing.** `Header.jsx` renders a search
input with a `⌘K` hint and a bell with an unread dot, and neither has ever had a handler behind it —
the dot is hardcoded. They are the last of the prototype's furniture. The search is hidden below
1024px by the responsive layer, which shrinks the problem without fixing it: on a laptop it still
invites a search that never runs. Either build them or take them out.

**The page builder cannot be reordered by touch.** It still uses the HTML5 drag API, where
`dragstart` never fires on a tablet. `resources/js/cms/useSortableList.js` is the pointer-based
replacement, already used by the FAQ, testimonial and blog lists. Migrating the builder to it would
also retire the second drag idiom in the codebase.

**Two things are unverified rather than known-good.** Touch drag on a real phone or tablet — both
the public testimonial slider and the CMS handles — has only ever been exercised with synthetic
pointer events. And the FAQ page's 980px layout breakpoint was checked by applying its declarations,
not by resizing a window.

**The responsive layer has no test.** §15 was audited by measuring every admin screen in a real
768px and 1024px viewport, and the numbers are in the commit message — but nothing stops a later
change reintroducing a fixed width. Playwright is in `package.json` and completely unused: no
config, no specs, no script. Standing it up would give the breakpoints a regression test and the
touch-drag work above somewhere to live.

## No screen is a prototype any more

Settings was the last, and calling it "nothing behind it" was wrong: its SEO tab described a default
description and sharing image the site genuinely lacked, and its Legal tab a disclaimer with nowhere
to live. Those are built. Two things on it were not, deliberately:

- **Business details** — the phone number, address, email and copyright line already live in Global
  content, and the registered name and ABN sit inside that copyright line. A second copy is a copy
  that disagrees. Its mock also invented an ABN, a Hawthorn address and a domain, none matching the
  real content; plausible fiction in an admin screen is worse than an empty one.
- **A terms page picker** beside the privacy one. Privacy has a real job — it links from the enquiry
  consent line, which had no link at all, so people were agreeing to nothing they could read. Terms
  has nowhere to render that would not duplicate the footer's small print, which Navigation owns. It
  is a field waiting for a place, and those become write-only fields.

**Tracking is switched off, with nothing entered.** Turning it on adds Google's script to every
public page. Before that happens the privacy policy has to say so, and somebody has to decide whether
a cookie notice is needed — neither is a code question. Ids are format-checked at the save and again
in `Site::tracking()`, because the value is printed inside a `<script>`.

Settings live in their own `site` row rather than in `globals`, because `settings.manage` is
super-admin-only while `globals` is client-administrator territory. One row edited by two screens with
two permissions is how a save from one reverts the other. `MediaController::usage()` scans both rows —
it only scanned `globals`, which would have let the favicon or the default sharing image be deleted
while every page used it.

Navigation and Global content were the other two prototypes, and both are real now. They share the `globals`
setting and split it by what somebody is doing rather than by where it is stored: menus and their
ordering under Navigation, the wording of the chrome under Global content. Both merge into the
existing row, so neither can wipe the other's half — a test asserts that from each side.

One duplication survives that split, and it is a genuine trap: the phone number appears both in the
header (Global content) and in the footer's contact column (Navigation). Changing one does not change
the other, and the screen says so rather than silently syncing them.

The dashboard was the last mock screen among the *numbered* sections; Navigation, Global content and
Settings came after it and were not numbered.

`mockData.js` is still imported, but almost all of what is left are UI constants sitting in a
badly-named file — `STATUS_LABEL`, `STATUS_TONE`, `NAV_ITEMS`, `SCREEN_TITLES`, `QUICK_ACTIONS`,
`COMPONENT_LIBRARY`. One real remnant survives: `Builder.jsx` still falls back to the invented `PAGES`
list when its `page` prop is null. The controller always passes one, so it is unreachable today —
which is exactly why it would be believed if it ever fired, showing titles like "Downsizing Support"
for a page that does not exist. Worth deleting along with the constants being moved somewhere honestly
named.

## Content, not code

- Five of the six testimonials are sample data, with permission recorded as "Sample data" and
  generated placeholder portraits in `public/sample-portraits/` (gitignored). They need replacing.
- Two blog articles are fixtures written during development, not client copy.
- The footer still has dead `#` links for Pricing, Free guide, Selling checklist, Glossary, Privacy,
  Terms and Complaints. Each needs a page or removing.
- `php artisan media:optimise` must be run once on any environment that has existing images — the
  migrations add the columns, but the backfill builds the small copies and shrinks the originals.
