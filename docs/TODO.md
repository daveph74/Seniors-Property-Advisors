# Outstanding work

Known gaps, in the order they are worth doing. Scope references are to the CMS development scope.

## Scope sections not yet built

Every section §1–§14 is built. What remains is checking rather than building:

**§15 Responsive administration interface.** Never audited. The scope asks for a CMS usable on
desktop, laptop and tablet. The drag handles on the list screens were justified by tablet use and
have only ever been exercised with synthetic pointer events, so this is the section most likely to
be wrong.

**§16 Acceptance criteria.** Fifteen numbered statements, which is what the client will judge
"complete" against. Most map onto finished work, but point 14 — "content editing does not allow
users to break the approved website layout" — is not obviously covered by anything, and is worth
deciding deliberately rather than assuming the section allowlist is enough.

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

**The page builder cannot be reordered by touch.** It still uses the HTML5 drag API, where
`dragstart` never fires on a tablet. `resources/js/cms/useSortableList.js` is the pointer-based
replacement, already used by the FAQ, testimonial and blog lists. Migrating the builder to it would
also retire the second drag idiom in the codebase.

**Two things are unverified rather than known-good.** Touch drag on a real phone or tablet — both
the public testimonial slider and the CMS handles — has only ever been exercised with synthetic
pointer events. And the FAQ page's 980px layout breakpoint was checked by applying its declarations,
not by resizing a window.

**§15 Responsive administration interface** has never been audited as a section in its own right.
The scope asks for a CMS usable on a tablet.

## One screen is still a prototype

**Settings** renders `SETTINGS_TABS`. Nothing behind it, and no scope section asks for it — worth
deciding whether it should exist at all rather than building it because the prototype had it. An
empty Settings screen invites people to look for something that is not there.

Navigation and Global content were the other two, and both are real now. They share the `globals`
setting and split it by what somebody is doing rather than by where it is stored: menus and their
ordering under Navigation, the wording of the chrome under Global content. Both merge into the
existing row, so neither can wipe the other's half — a test asserts that from each side.

One duplication survives that split, and it is a genuine trap: the phone number appears both in the
header (Global content) and in the footer's contact column (Navigation). Changing one does not change
the other, and the screen says so rather than silently syncing them.

The dashboard was the last mock screen among the numbered sections, which is a narrower claim than
"the last mock screen" and easy to conflate.

## Content, not code

- Five of the six testimonials are sample data, with permission recorded as "Sample data" and
  generated placeholder portraits in `public/sample-portraits/` (gitignored). They need replacing.
- Two blog articles are fixtures written during development, not client copy.
- The footer still has dead `#` links for Pricing, Free guide, Selling checklist, Glossary, Privacy,
  Terms and Complaints. Each needs a page or removing.
- `php artisan media:optimise` must be run once on any environment that has existing images — the
  migrations add the columns, but the backfill builds the small copies and shrinks the originals.
