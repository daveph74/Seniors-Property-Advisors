# Outstanding work

Known gaps, in the order they are worth doing. Scope references are to the CMS development scope.

## Scope sections not yet built

**§3 CMS Dashboard.** The last screen still rendering `mockData.js` — the counts, the recently
edited list and the drafts awaiting publication are all invented. Every count it asks for now has a
real table behind it, so this is mostly wiring, and it retires the last of the mock data.

**§13 Audit History.** Nothing records who did what. Page publishing keeps `published_by` and a
revision per publish, but creating, editing, unpublishing, archiving and deleting leave no trail,
and there is none at all for articles, FAQs, testimonials or media. §13 also asks for restoring
recently deleted content "where practical" — pages can be unarchived today, nothing else can.

## Gaps in what is built

**Enquiries cannot be read.** `/contact` stores enquiries and shows the editor's confirmation, but
there is no screen listing them, so seeing one means opening the database. The `handled_at` column
exists for a mark-as-handled control that was never built. §12 puts enquiry handling in CRM scope,
so this is defensible — but until SyncID exists, an enquiry that nobody can see is close to an
enquiry lost. Nothing emails anyone either: an enquiry arrives silently.

**Look for a third write-only field.** Two editable fields turned out to save and never render:
the media caption and the contact form's confirmation message. Both were found by accident. Worth
walking the builder's field schemas against what each section actually renders, once, deliberately.

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

## Content, not code

- Five of the six testimonials are sample data, with permission recorded as "Sample data" and
  generated placeholder portraits in `public/sample-portraits/` (gitignored). They need replacing.
- Two blog articles are fixtures written during development, not client copy.
- The footer still has dead `#` links for Pricing, Free guide, Selling checklist, Glossary, Privacy,
  Terms and Complaints. Each needs a page or removing.
- `php artisan media:optimise` must be run once on any environment that has existing images — the
  migrations add the columns, but the backfill builds the small copies and shrinks the originals.
