# Acceptance evidence — scope §16 and §17

Checked against the real scope, now transcribed at `docs/specs/cms-scope.md`. Every criterion below
is **quoted verbatim** from §16; every exclusion is quoted from §17.

This answers one question per criterion: **how do we know?** Each row names the thing that
satisfies it and the test that proves it. A row with no named test would be written as a gap.

**All fifteen criteria are met.** Eighty-two cited tests, all verified present in `tests/`.

## §16 — Acceptance criteria

> "The CMS will be considered complete when:"

| # | Criterion | Satisfied by | Evidence |
|---|---|---|---|
| 1 | Super administrators and client administrators can securely sign in. | `Auth\LoginController`, `auth.session` on `/cms` | `AuthTest::test_correct_details_sign_a_user_in`, `::test_a_wrong_password_is_refused`, `::test_a_deactivated_account_cannot_sign_in`, `::test_repeated_failures_are_rate_limited`, `::test_a_guest_is_sent_to_the_sign_in_page`, `::test_signing_out_ends_the_session` |
| 2 | Permissions correctly limit access based on user role. | `app/Auth/Permissions.php`, `permit:` middleware | `PermissionsTest::test_a_client_administrator_reaches_the_content_modules`, `::test_a_client_administrator_cannot_reach_users_or_settings`, `::test_a_super_administrator_reaches_users_and_settings`, `::test_only_a_super_administrator_restores_archived_content`, `::test_the_sidebar_only_offers_modules_the_role_can_open` |
| 3 | Users can create, edit, reorder, publish and archive page content. | `CmsPageController`, builder, `page_revisions` | `CreatePageTest::test_creating_a_page_opens_its_builder`, `PageDetailsTest::test_a_page_can_be_renamed`, `PageActionsTest::test_publishing_from_the_list_promotes_the_draft`, `::test_archiving_hides_the_page_and_can_be_undone`, `HomePageTest::test_it_passes_every_section_in_content_order`, `SectionDiffTest::test_it_reports_a_move_without_reporting_an_edit` |
| 4 | Users can manage predefined page sections. | `PageContentStore::BLOCK_TYPES`, `SettingsPanel` | `CmsBuilderTest::test_the_scoped_section_types_are_registered`, `::test_it_saves_a_section_with_nested_children`, `::test_a_section_persists_its_anchor`, `SectionFieldsTest::test_a_hero_persists_its_nested_content`, `::test_agent_compare_persists_its_labels_sort_and_filter_flags` |
| 5 | Users can create and publish blog articles. | `blog_posts`, TipTap editor | `BlogTest::test_an_article_can_be_written_edited_and_published`, `::test_every_format_the_scope_asks_for_survives_saving` |
| 6 | Users can manage blog categories. | `blog_categories` + pivot | `BlogTest::test_categories_can_be_created_reordered_and_disabled`, `::test_an_article_can_hold_more_than_one_category`, `::test_a_disabled_category_disappears_from_filters_but_keeps_its_articles_live`, `::test_deleting_a_category_refiles_its_articles_rather_than_orphaning_them` |
| 7 | Users can create, order and disable FAQs. | `FaqController` | `FaqTest::test_it_creates_a_question_and_puts_it_last`, `::test_it_reorders`, `::test_it_updates_and_hides_a_question`, `::test_a_category_can_be_hidden_without_touching_its_questions` |
| 8 | Users can create, order and disable testimonials. | `testimonials` | `TestimonialTest::test_it_saves_every_field_the_scope_asks_for`, `::test_it_reorders`, `::test_toggling_one_flag_leaves_the_other_alone` |
| 9 | Users can upload and reuse images. | `MediaController`, S3 | `MediaTest::test_it_signs_an_upload_and_generates_the_key_itself`, `::test_it_records_an_upload_that_arrived`, `::test_the_picker_library_lists_images_and_leaves_out_other_files`, `::test_it_keeps_an_image_a_published_page_still_uses` |
| 10 | Draft content can be previewed before publication. | `CmsPreviewController` | `CmsPreviewTest::test_preview_renders_the_draft_not_the_published_tree`, `::test_preview_is_never_cached`, `BlogTest::test_a_draft_is_previewable_before_publishing` |
| 11 | Only published and active content appears publicly. | `renderable()`, `scopeActive`, status columns | `CreatePageTest::test_a_new_page_is_not_public_until_it_is_published`, `PageActionsTest::test_unpublishing_takes_the_page_off_the_public_site`, `BlogTest::test_only_published_articles_are_public`, `TestimonialTest::test_the_library_only_carries_consented_active_testimonials_in_order`, `FaqTest::test_the_library_only_offers_active_questions_in_order`, `HomePageTest::test_it_drops_inactive_and_unregistered_sections` |
| 12 | SEO fields are reflected correctly on the public website. | `seo_*` columns, `site` setting, blade head | `SeoControlsTest::test_the_sharing_tags_reach_a_scraper_that_runs_no_javascript`, `::test_a_page_can_name_a_different_canonical_address`, `::test_a_page_can_be_hidden_from_search_engines_and_shown_again`, `SeoTest::test_a_page_shares_an_absolute_image_with_its_size`, `SettingsTest::test_the_title_pattern_reaches_the_delivered_html` |
| 13 | CMS changes are recorded against the responsible user. | `activity_log` | `ActivityTest::test_it_records_who_did_it_and_when`, `::test_the_screen_lists_what_happened_newest_first`, `PermissionsTest::test_a_change_is_recorded_against_the_signed_in_user`, `BlogTest::test_the_change_is_recorded_against_the_signed_in_user` |
| 14 | Content editing does not allow users to break the approved website layout. | four server-side layers | see [Criterion 14](#criterion-14--the-layout-guarantee) |
| 15 | Publishing updates the website without requiring a code deployment. | `PageContentStore`, cache clearing | `CmsBuilderTest::test_saving_a_draft_does_not_change_the_public_page`, `::test_publishing_promotes_the_draft_and_writes_one_revision`, `PageActionsTest::test_unpublishing_clears_the_cached_public_page`, `GlobalContentTest::test_a_change_reaches_every_page` |

## Criterion 14 — the layout guarantee

> "Content editing does not allow users to break the approved website layout."

`docs/TODO.md` recorded this as "not obviously covered by anything, and worth deciding deliberately
rather than assuming the section allowlist is enough". Having traced it: **met**, and by rather
more than the allowlist.

§4 states the same requirement as a design rule — *"The CMS should prevent users from creating
layouts that break the approved website design"* — and `docs/specs/page-section-module.spec.md`
locks the mechanism in: *"The section-type registry (constrained Puck config) is the guardrail that
enforces 'predefined sections, no free-form builder'. No arbitrary containers, no raw-HTML block."*

Four layers enforce it:

| Layer | Mechanism | Where |
|---|---|---|
| Type allowlist | 30 named block types. No raw-HTML block exists, and none can be added through the CMS — adding one is a code change. | `PageContentStore::BLOCK_TYPES` |
| Nesting rules | A section takes blocks or a row; a row takes only columns; a column takes blocks or a row. Rows nest at most `MAX_ROW_DEPTH = 2`. | `PageContentStore::CHILD_TYPES` |
| Tree validation | Walks the submitted tree and rejects unknown types, illegal children, children on a leaf, and over-depth rows. | `SaveSectionsRequest::checkTree()` |
| Markup stripping | `strip_tags` over every string in the tree, so no `data` key can carry markup into a rendered page. | `SaveSectionsRequest::sanitise()` |

**What makes this a guarantee rather than a convention is that all four run on the server.** The
builder's own restrictions are a convenience; a hand-crafted POST is refused on exactly the same
rules as a bad drag. `SaveSectionsRequest` is the single door, and both CMS controllers go through
`PageContentStore`.

Proven in `tests/Feature/CmsBuilderTest.php`:

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
`renderable()` drops illegal blocks at render time, so a tree predating a type's removal cannot
break a live page (`HomePageTest::test_it_drops_a_row_nested_three_deep`).

**Conclusion: met.** Recorded with the reasoning visible so it can be re-checked rather than trusted.

## §17 — Out of scope

Fifteen exclusions. Nothing needs building; the work is confirming absence and noting what would
stop each being reintroduced by accident.

| Exclusion | Status | What holds the line |
|---|---|---|
| Full drag-and-drop website builder | absent | The builder is a **constrained** registry, not a free-form canvas — see criterion 14. Note §4 *requires* drag-and-drop **reordering**; what is excluded is a general website builder, not the drag controls. |
| Editing raw HTML, CSS or JavaScript | absent | `Html::ALLOWED`; no source view in `RichTextEditor.jsx`; `sanitise()` strips tags from section data. `BlogTest::test_dangerous_markup_is_stripped_on_the_way_in`, `::test_a_reader_never_receives_unsafe_markup` |
| Custom page templates created by client users | absent | Starter layouts are fixed in code and validated. `CreatePageTest::test_an_unknown_parent_or_layout_is_rejected`, `::test_every_starter_layout_saves_cleanly` |
| Website analytics dashboard | absent | Settings hold analytics ids only; no dashboard exists. `SettingsTest::test_analytics_loads_for_a_reader_and_not_inside_the_admin` |
| Email marketing platform | absent | Nothing built |
| Newsletter sending | absent | Nothing built |
| Customer login portal | absent | Sign-in is staff-only; the public site stays open to guests. `AuthTest::test_the_public_website_stays_open_to_guests` |
| Membership management | absent | Nothing built |
| Multilingual content | absent | Single-locale content model |
| Advanced content approval workflows | absent | Publishing is immediate; there is no approval chain |
| Scheduled publishing | absent | `published_at` is a display date, never a scheduler. `BlogTest::test_a_future_date_does_not_hide_a_published_article` |
| AI content generation | absent | Nothing built |
| Full document or file-management system | absent | The library is images only. `MediaTest::test_it_refuses_to_sign_anything_that_is_not_an_image`, `::test_the_picker_library_lists_images_and_leaves_out_other_files` |
| Changes to SyncID workflows or CRM automations | absent | No SyncID integration exists. §12 keeps field mappings and routing with the development team |
| Ongoing content entry after the initial agreed content population | n/a | Commercial, not code |

Five exclusions have real guard tests. The allowlist one is the model: widening `Html::ALLOWED`
fails `BlogTest`, which is what stops an exclusion eroding quietly.

## Notes on things that are *not* acceptance failures

Two items previously carried as possible gaps turned out not to bear on §16 at all:

- **Enquiries cannot be read in the CMS.** §12 puts contact and lead enquiry forms in "the broader
  website and CRM scope, **not** the content-management modules", and no §16 criterion mentions
  them. Still worth building for the reason in `docs/TODO.md` — an enquiry nobody can see is close
  to an enquiry lost — but it does not block acceptance.
- **The responsive admin interface.** §15 requires it and it is built, but it is not one of the
  fifteen acceptance criteria. Its remaining weaknesses — no automated test, and no touch drag in
  the page builder — are quality gaps against §15, not acceptance failures. §15 also explicitly
  allows that "complex page-section management may be optimised primarily for desktop and tablet".

## §18 development notes

Not acceptance criteria, but the scope asks for them and they are worth recording:

- *"Include automated tests for permissions and core content workflows"* — 453 tests, with
  `PermissionsTest` and `AuthTest` covering the permissions half directly.
- *"Include clear handover notes for future developers"* — `CLAUDE.md`, `docs/TODO.md`, this
  document, and `docs/specs/`.

## Re-verifying this document

The citations are the value here; a matrix naming a renamed or deleted test is worse than no
matrix. Check mechanically rather than by eye:

```sh
grep -oh 'test_[a-z0-9_]\{3,\}' docs/acceptance.md | sort -u > /tmp/cited.txt
grep -roh 'function test_[a-z0-9_]*' tests/ | sed 's/function //' | sort -u > /tmp/actual.txt
comm -23 /tmp/cited.txt /tmp/actual.txt   # must print nothing
```

Last run: 82 citations, all present. `php artisan test` — 453 passing.
