# Spec — Page & Section Management Module (SPA CMS)

Part of Phase 2 of the Seniors Property Advisors CMS. Both roles (super admin,
client admin) can edit; archived/draft pages are never publicly accessible.

## Architectural decision (locked)

- Store each page's sections as a **JSON column**, not normalised `page_sections`
  rows. Puck outputs a clean JSON tree; the platform is JSON-snapshot based.
  Display order = array order; per-section active state = a prop.
- The **section-type registry (constrained Puck config)** is the guardrail that
  enforces "predefined sections, no free-form builder" and "editing cannot break
  the approved layout". No arbitrary containers, no raw-HTML block.

## W1 — Data model & migrations

- `pages`: id, title, slug (unique), nav_label, status (draft|published|archived),
  seo_title, seo_description, social_image_id (→ media), draft_content (JSON),
  published_content (JSON, nullable), last_updated_by, published_by, published_at,
  deleted_at, timestamps.
- `page_revisions`: page_id, content (JSON snapshot), action, user_id, created_at.
  One row per publish — satisfies audit + restore without a diff UI.
- `redirects`: from_slug, to_slug, page_id — created on published-slug change.
- Seed the confirmed page list.

## W2 — Section type registry (Puck config)

Fixed field schema per section type. Confirm the final inventory against the
approved design before building — each type is its own field schema + public
component.

Candidate types: hero banner, text + image, rich text, service cards,
feature/benefit cards, CTA banner, statistics, testimonials, FAQs, process steps,
team intro, contact form section, blog listing.

- Common prop contract: heading, supportingText, image(s), links/buttons,
  displayOrder (implicit), active (custom eye-toggle field).
- Media props use a custom Puck field that opens the media library — never a free
  text URL.
- Lock the config to only these components.

## W3 — Backend API (Laravel)

- Page CRUD, gated by the permission layer. Archived pages blocked at route/query.
- Slug: unique validation; on published-slug change, create a redirect row (and
  warn the user).
- saveDraft → writes draft_content + last_updated_by.
- publish → copy draft_content into published_content, stamp published_by/at,
  write page_revisions snapshot, clear cache.
- unpublish / archive / restore with confirmation semantics.
- Server-side rich-text sanitisation on save.

## W4 — Admin UI (React)

- Page list: status filter, last-updated-by/date, quick actions.
- Page settings panel: title, slug, nav label, status, SEO fields, social image.
- Section editor: Puck canvas, drag-to-reorder, palette limited to the registry,
  per-section active toggle.
- Slug-change warning modal wired to redirect logic.
- Preview / Publish buttons.

## W5 — Public rendering

- Resolver maps published_content JSON → React section components (same prop
  contract as the Puck components — one component set for editor and site).
- Render only active sections from published_content; never draft_content.
- Archived/draft pages → 404 publicly, reachable in admin.

## Build order within module

W2 + W1 first (the contract) → W5 (see real output early) → W3 → W4.
Stub saveDraft/publish here so the module is testable end-to-end; the full
cache-clearing publish pipeline is formally Phase 3.

## Definition of done

- [ ] Both roles can create/edit/reorder/publish/archive a page
- [ ] Sections limited to the registry; layout cannot be broken
- [ ] Slug change creates a redirect (or warns)
- [ ] Draft never leaks to public; archived returns 404 publicly
- [ ] Each publish writes a revision snapshot
- [ ] Permission tests + core content-workflow tests pass
