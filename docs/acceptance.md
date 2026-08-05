# Acceptance evidence — scope §16 and §17

> **Provisional.** The scope document is not in this repository, so the criteria below are
> **reconstructed** from `CLAUDE.md`, `docs/TODO.md`, `docs/specs/page-section-module.spec.md` and
> the code itself. Only point 14 is quoted from a source. Nothing here is confirmed against §16
> until somebody diffs it against the real document — see [Verifying this document](#verifying-this-document).
>
> A criterion that exists in the scope and not in this list stays invisible until that diff
> happens. That is the one failure this document cannot protect against.

This answers one question for every acceptance criterion: **how do we know?** Each row names the
thing that satisfies it and the test that proves it. A row with no named test is written as a gap,
not as "covered".

The tests are cited, not restated. The suite is 453 tests and most of this was a mapping exercise
rather than new work.

## How the criteria were reconstructed

`docs/TODO.md` records two facts about §16: it holds **fifteen numbered statements**, and point 14
reads *"content editing does not allow users to break the approved website layout"*. Everything
else is inferred from the sections the scope is known to contain, which are recoverable from `§`
references scattered through the code and notes:

| § | Subject | Recovered from |
|---|---|---|
| 2 | Two roles and their split | `app/Auth/Permissions.php` — "Scope section 2, expressed once" |
| 3 | Dashboard, "draft content awaiting publication" | `DashboardController`, `docs/TODO.md` |
| 5 | The editor's format list | `app/Content/Html.php` — "scope §5's editor list and nothing more" |
| 6 | Media | `CLAUDE.md` |
| 7 | Testimonials — consent as a constraint, not a field | `CLAUDE.md`, `TestimonialTest` |
| 12 | Contact forms; CRM boundary sits outside the CMS | `CLAUDE.md`, `EnquiryController` |
| 13 | Audit trail; restoring recently deleted content | `ActivityTest`, `DeletedContentTest` |
| 14 | Validation and data safety | `ValidationSafetyTest` — "Scope §14" |
| 15 | Responsive administration interface | closed by `c707a14` |

Note §14 is **validation**, not layout. Point 14 of §16 is a different thing that happens to share
a number; the two are easy to confuse and are kept apart here deliberately.

## §16 — the fifteen criteria

Status is one of **met** (evidence named), **met, untested** (true but nothing guards it), or
**gap**.

| # | Criterion | Satisfied by | Evidence | Status |
|---|---|---|---|---|
| 1 | Two roles with the agreed split — client administrators create, edit, publish and unpublish; super administrators additionally delete, restore, manage accounts and reach settings `[reconstructed]` | `app/Auth/Permissions.php`, `permit:` middleware | `PermissionsTest::test_a_client_administrator_reaches_the_content_modules`, `::test_a_client_administrator_cannot_reach_users_or_settings`, `::test_only_a_super_administrator_restores_archived_content`, `::test_the_sidebar_only_offers_modules_the_role_can_open` | met |
| 2 | Content can be created, edited and published without a developer `[reconstructed]` | `CmsPageController`, the builder | `CreatePageTest::test_creating_a_page_opens_its_builder`, `PermissionsTest::test_a_client_administrator_can_edit_and_publish_a_page`, `CmsBuilderTest::test_publishing_promotes_the_draft_and_writes_one_revision` | met |
| 3 | The public website renders from stored content, not hardcoded markup `[reconstructed]` | `PageContentStore`, `resources/js/sections/` | `ContentStorageTest::test_a_published_tree_round_trips_byte_for_byte`, `CmsBuilderTest::test_the_builder_receives_the_real_home_sections`, `CreatePageTest::test_a_nested_page_renders_at_its_nested_url` | met |
| 4 | Drafts can be previewed, and a published version can be rolled back `[reconstructed]` | `CmsPreviewController`, `page_revisions` | `CmsPreviewTest::test_preview_renders_the_draft_not_the_published_tree`, `CmsRestoreTest::test_restoring_writes_the_snapshot_back_as_a_draft`, `::test_restoring_leaves_the_public_page_untouched`, `RevisionHistoryTest::test_publishing_a_changed_tree_records_a_new_version` | met |
| 5 | Blog articles can be written, categorised and published `[reconstructed]` | `blog_posts`, TipTap editor | `BlogTest::test_an_article_can_be_written_edited_and_published`, `::test_every_format_the_scope_asks_for_survives_saving`, `::test_only_published_articles_are_public` | met |
| 6 | FAQs can be managed and grouped `[reconstructed]` | `FaqController` | `FaqTest::test_it_creates_a_question_and_puts_it_last`, `::test_the_seeded_categories_are_exactly_the_scope_list_in_its_order`, `::test_a_category_can_be_hidden_without_touching_its_questions` | met |
| 7 | Testimonials publish only where the client has given permission `[reconstructed]` | `consent_confirmed_at`, `scopeActive` | `TestimonialTest::test_it_cannot_be_published_before_permission_is_recorded`, `::test_recording_permission_records_who_and_when`, `::test_withdrawing_permission_takes_it_off_the_website` | met |
| 8 | Images can be uploaded and reused without a developer `[reconstructed]` | `MediaController`, S3 | `MediaTest::test_it_signs_an_upload_and_generates_the_key_itself`, `::test_it_records_an_upload_that_arrived`, `::test_it_keeps_an_image_a_published_page_still_uses` | met |
| 9 | Navigation menus are editable `[reconstructed]` | `globals` setting | `NavigationTest::test_a_menu_change_reaches_the_website`, `::test_the_order_is_kept_exactly_as_given` | met |
| 10 | Wording that appears on every page is editable `[reconstructed]` | `globals` setting | `GlobalContentTest::test_a_change_reaches_every_page`, `::test_saving_leaves_the_menus_alone` | met |
| 11 | SEO text and sharing images are editable per page and article `[reconstructed]` | `seo_*` columns, `site` setting | `SeoControlsTest::test_a_page_can_name_a_different_canonical_address`, `SeoTest::test_a_page_shares_an_absolute_image_with_its_size`, `SettingsTest::test_the_default_description_fills_in_for_a_page_that_has_none` | met |
| 12 | Enquiries from the contact form are captured `[reconstructed]` | `EnquiryController`, `enquiries` | `EnquiryTest::test_an_enquiry_is_kept`, `::test_nothing_is_kept_without_consent` | **gap** — see below |
| 13 | Every change is attributable, and recently deleted content can be restored `[reconstructed]` | `activity_log`, soft deletes | `ActivityTest::test_it_records_who_did_it_and_when`, `::test_the_screen_lists_what_happened_newest_first`, `DeletedContentTest::test_a_deleted_question_leaves_the_website_but_can_be_brought_back` | met |
| 14 | *"Content editing does not allow users to break the approved website layout."* `[quoted]` | four server-side layers | see [Point 14](#point-14--the-layout-guarantee) | met |
| 15 | The administration interface is usable on desktop, laptop and tablet `[reconstructed]` | `resources/css/cms.css` responsive layer | measured in `c707a14`; **no automated test** | met, untested |

### Row 12 — the gap

Enquiries are captured, validated and consent-gated, but **there is no screen that lists them**, so
reading one means opening the database. `enquiries.handled_at` exists for a mark-as-handled control
that was never built, and nothing emails anyone on arrival.

This is defensible — §12 puts enquiry handling in CRM scope, and the CRM is outside the CMS — but
until SyncID exists, an enquiry nobody can see is close to an enquiry lost. Whether this fails the
criterion depends on wording nobody here has read. **Flagged for the scope diff.**

### Row 15 — met but unguarded

§15 was closed by measuring every admin screen in real 768px and 1024px viewports, and the numbers
are in `c707a14`'s message. Nothing stops a later change reintroducing a fixed width. Playwright is
in `package.json` and entirely unused — no config, no specs, no script. That is where a regression
test for this belongs.

Touch drag in the page builder is still broken: it uses the HTML5 drag API, where `dragstart` never
fires on a tablet. The builder is now *sized* for a tablet but section reordering by touch is not
possible. Depending on how the criterion is worded, this may downgrade row 15 to a gap.

### The likeliest reconstruction error

Fifteen criteria had to be allocated across roughly fourteen recoverable subjects, so at least one
row is probably split or merged wrongly. The most likely candidates: the dashboard (§3) has no row
of its own here, and "draft preview" and "version rollback" are merged into row 4 when they may be
two separate statements. Check those first.

## Point 14 — the layout guarantee

> *"Content editing does not allow users to break the approved website layout."*

`docs/TODO.md` recorded this as "not obviously covered by anything, and worth deciding deliberately
rather than assuming the section allowlist is enough". Having traced it: **the criterion is met**,
and by rather more than the allowlist.

The spec makes it an architectural commitment rather than a behaviour —
`docs/specs/page-section-module.spec.md` locks it in: *"The section-type registry (constrained Puck
config) is the guardrail that enforces 'predefined sections, no free-form builder' and 'editing
cannot break the approved layout'. No arbitrary containers, no raw-HTML block."*

Four layers enforce it:

| Layer | Mechanism | Where |
|---|---|---|
| Type allowlist | 30 named block types. No raw-HTML block exists, and none can be added through the CMS — adding one is a code change. | `PageContentStore::BLOCK_TYPES` |
| Nesting rules | A section takes blocks or a row; a row takes only columns; a column takes blocks or a row. Rows nest at most `MAX_ROW_DEPTH = 2`. | `PageContentStore::CHILD_TYPES` |
| Tree validation | Walks the submitted tree and rejects unknown types, illegal children, children on a leaf, and over-depth rows. | `SaveSectionsRequest::checkTree()` |
| Markup stripping | `strip_tags` over every string in the tree, so no `data` key can carry markup into a rendered page. | `SaveSectionsRequest::sanitise()` |

**What makes this a guarantee rather than a convention is that all four run on the server.** The
builder's own restrictions are a convenience; a hand-crafted POST is rejected on exactly the same
rules as a bad drag. `SaveSectionsRequest` is the single door, and both CMS controllers go through
`PageContentStore`.

Proven by, in `tests/Feature/CmsBuilderTest.php`:

- `test_it_rejects_a_section_type_outside_the_registry` — unknown type refused
- `test_a_near_miss_element_type_is_rejected` — a plausible-looking type is still refused
- `test_it_rejects_a_column_at_page_level`, `test_it_rejects_a_row_inside_a_row`,
  `test_it_rejects_a_section_nested_inside_a_section`, `test_it_rejects_a_leaf_as_a_direct_child_of_a_row`,
  `test_it_rejects_a_column_as_a_direct_child_of_a_section` — illegal nesting refused
- `test_it_rejects_a_third_level_of_row_nesting` — depth ceiling holds
- `test_it_rejects_children_on_a_leaf_block`, `test_it_rejects_a_grandchild_list` — leaves stay leaves
- `test_it_strips_markup_from_saved_strings` — no markup survives into a `data` key
- `test_the_js_type_mirror_matches_php` — keeps `resources/js/sections/childTypes.js` honest against
  the PHP, so the builder cannot drift into offering something the server will refuse

Two further protections sit outside the section tree: `Html::ALLOWED` bounds what an article body
may contain (`BlogTest::test_the_allowlist_keeps_styling_out_of_the_approved_design`), and
`PageContentStore::renderable()` drops illegal blocks at render time, so a tree that predates a
type's removal cannot break a live page (`CmsPreviewTest::test_preview_drops_hidden_and_illegal_blocks`).

**Conclusion: met.** Recorded here with the reasoning visible so it can be re-checked rather than
trusted.

## §17 — exclusions

§17 lists what is deliberately *not* built. Nothing needs building; the work is proving absence and
making sure an exclusion cannot be reintroduced by accident.

| Exclusion | Enforced by | Guard |
|---|---|---|
| Editing raw HTML | `Html::ALLOWED` — the allowlist is §5's editor list and nothing more. No source view exists in the editor (`RichTextEditor.jsx`). | `BlogTest::test_dangerous_markup_is_stripped_on_the_way_in`, `::test_the_allowlist_keeps_styling_out_of_the_approved_design`, `::test_a_reader_never_receives_unsafe_markup` |
| Scheduled publishing | `published_at` is the date readers see, never a scheduler. `BlogPost` deliberately ignores a future date. | `BlogTest::test_a_future_date_does_not_hide_a_published_article` |
| CRM integration and field mappings | §12 puts these outside the CMS. `handled_at` exists on `enquiries` but no CMS surface acts on it. | none needed — nothing to regress |

Both enforced exclusions already have named guard tests, so no new tests were written. The
allowlist one is the model to copy: widening `Html::ALLOWED` fails `BlogTest`, which is what stops
the exclusion eroding quietly.

**This list is certainly incomplete.** Only three exclusions are recoverable from the repo. The
real §17 will name others, and each will need the same treatment: name it, name what enforces it,
name the guard — and add a guard where an exclusion rests on convention alone.

## Open decisions

Two items bear on acceptance but are rulings to make, not work to carry out. Both are recorded in
`docs/TODO.md`:

- **SVG uploads are never inspected.** Only a super administrator can upload one, and it is served
  with `nosniff` and a locked-down CSP, so script inside cannot execute — but that safety rests on
  those headers surviving whatever CDN ends up in front of the route. Either parse the XML and
  reject scripts, or drop SVG support. Bears on rows 8 and 14.
- **Revision restore is not behind `content.restore`.** A client administrator can roll a page back
  to an old version but cannot unarchive one, which contradicts §2's split. Bears on rows 1 and 4.

## Verifying this document

1. **Diff against the real scope.** Put the scope at `docs/specs/cms-scope.md`, then compare §16's
   fifteen statements against the table above. Confirm the count is fifteen, replace every
   `[reconstructed]` wording with the real wording, and add any criterion missing entirely. Until
   this is done the document stays marked provisional.
2. **Confirm every cited test exists and passes.** The citations are the whole value here; a matrix
   naming a renamed or deleted test is worse than no matrix. Check mechanically rather than by eye:

   ```sh
   grep -oh 'test_[a-z0-9_]\{3,\}' docs/acceptance.md | sort -u > /tmp/cited.txt
   grep -roh 'function test_[a-z0-9_]*' tests/ | sed 's/function //' | sort -u > /tmp/actual.txt
   comm -23 /tmp/cited.txt /tmp/actual.txt   # must print nothing
   ```

   Last run: 55 citations, all present.

3. **`php artisan test`** — 453 passing. This document cites behaviour; it does not change any.
