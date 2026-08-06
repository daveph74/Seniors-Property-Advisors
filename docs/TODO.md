# Outstanding work

Known gaps, in the order they are worth doing. Scope references are to the CMS development scope.

## Scope sections not yet built

None. §1–§15 are built, and §16 and §17 have been checked against the scope — now transcribed at
`docs/specs/cms-scope.md`, so the section numbers used throughout these notes resolve to something
in the repository.

**All fifteen acceptance criteria are met**, each against a named passing test. See
`docs/acceptance.md`. All fifteen §17 exclusions are absent, five of them held in place by a guard
test rather than only by convention.

Criterion 14 turned out to be the opposite of the worry once recorded here. "Content editing does
not allow users to break the approved website layout" is enforced in four server-side layers — the
type allowlist, the nesting rules, `SaveSectionsRequest::checkTree()` and `sanitise()` — with about
twenty negative tests already behind it. What makes it a guarantee is that all four run on the
server: a hand-crafted POST is refused on the same rules as a bad drag.

Two things this pass corrected, both worth remembering:

- **Enquiries not being readable is not an acceptance failure.** §12 puts contact and lead enquiry
  forms in "the broader website and CRM scope, not the content-management modules", and no §16
  criterion mentions them. Still worth building, for the reason below — but it does not block sign-off.
- **The responsive interface is not an acceptance criterion either.** §15 requires it and it is
  built; §16 never mentions it. Its remaining weaknesses are quality gaps against §15, not
  acceptance failures, and §15 itself allows that "complex page-section management may be optimised
  primarily for desktop and tablet".

§18's development notes are met too, now that a `README.md` exists — it was the one thing a
developer inheriting this repo had no entry point for. One line of §18 cannot be closed by code:

**The client has not confirmed the database structure and CMS screens.** §18 asks the developer to
do that "before completing the full implementation". It is a conversation, not a commit.

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

**A fresh install ships two dead menu links.** The header offers FAQs and Blog, and the footer
repeats them, but `/faqs` and `/blog` are created by `php artisan pages:scaffold` — which is not
part of `composer setup`, `migrate --seed`, or anything the README tells a new developer to run. So
a clean database answers 404 on both. Either seed those two pages the way the four content pages
are seeded, or make setup run the scaffold. `HowItWorksPageTest::SCAFFOLD_ONLY` lists them, so the
test that checks every menu link resolves will fail if a third one joins them.

**Eight section types can never carry the h1.** `ownerOfTheH1()` in
`resources/js/sections/headingLevel.js` nominates a section to own the page's h1 when no hero is
present, and `SectionHead` honours it — but `ProcessStepsSection`, `TrustCardsSection`,
`WhyListSection`, `AgentCompareSection`, `FamilySection`, `CtaSection`, `TextImageSection` and
`FaqListSection` all hardcode `<h2>` and ignore the context. A page whose first heading-bearing
section is any of them therefore renders with **no h1 at all**, silently — the resolver believes it
handed the job to someone. Found while building the How it works page, which is why that page opens
on a hero rather than on its steps. The fix is to route those eight through `SectionHead`, or at
least through `useHeadingLevel()`.

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

`mockData.js` is gone. Its six real UI constants moved to `constants.js`; the seven invented
fixtures were deleted.

**The note that used to sit here was wrong, and the way it was wrong is the useful part.** It said
`Builder.jsx`'s fallback to the invented `PAGES` list was "unreachable today, the controller always
passes one". The controller did not: `edit()` rendered the builder with only a `pageId` when the id
matched no page, so `/cms/pages/2/edit` on a database without a page 2 displayed "Downsizing
Support" — and saving it then 404'd. `test_design_only_pages_receive_no_sections` visited ids 2, 5
and 9 and asserted the screen loaded, so the path was not merely reachable but expected; the test
never checked the title, which is why nobody caught it. The edit route now 404s an unknown id like
the save route always did, and that test is `test_an_unknown_page_id_cannot_be_opened`.

The claim was written from reading the JSX without following the prop back to the controller. Worth
remembering the next time something here says "unreachable".

## Content, not code

- Five of the six testimonials are sample data, with permission recorded as "Sample data" and
  generated placeholder portraits in `public/sample-portraits/` (gitignored). They need replacing.
- Two blog articles are fixtures written during development, not client copy.
- The footer still has dead `#` links for Pricing, Free guide, Selling checklist, Glossary, Privacy,
  Terms and Complaints. Each needs a page or removing.
- `php artisan media:optimise` must be run once on any environment that has existing images — the
  migrations add the columns, but the backfill builds the small copies and shrinks the originals.
