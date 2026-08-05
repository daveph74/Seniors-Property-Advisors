# Seniors Property Advisors — Custom CMS Development Scope

Transcribed from `Seniors Property Advisors CMS Development Scope.pdf` so the numbered sections
referenced throughout `CLAUDE.md`, `docs/TODO.md` and the code comments resolve to something in the
repository. The PDF remains the authority; this is a faithful copy for reference.

## 1. Objective

Build a custom CMS for the Seniors Property Advisors website that allows authorised client users
and the RedHQ team to manage website content without requiring developer access.

The CMS must manage: website pages and page sections, blog articles, FAQs, testimonials.

The CMS should be simple, structured and safe for non-technical users. Users should edit predefined
content areas rather than directly editing raw HTML or page layouts.

## 2. User Roles and Permissions

**Super Administrator** — for the RedHQ development and support team. Can access all CMS modules;
create, edit, publish and delete all content; manage CMS users; manage user roles and permissions;
restore archived content; access system-level settings; override publishing restrictions where
required.

**Client Administrator** — for authorised Seniors Property Advisors team members. Can create and
edit pages and page sections; create and manage blog articles, FAQs and testimonials; upload and
select media; save content as draft; publish and unpublish content; preview content before
publishing.

Client administrators must **not** have access to: application configuration, developer settings,
database or infrastructure settings, code-level templates, super administrator accounts.

## 3. CMS Dashboard

Quick links to each content module, recently edited content, draft content awaiting publication,
recently published blog articles, basic content counts, links to view the live website.

Suggested counts: published pages, draft pages, published blog articles, draft blog articles,
active FAQs, active testimonials.

Detailed website analytics are not needed unless added as a separate scope item.

## 4. Page Management

Expected pages may include Home, About Us, Our Services, How It Works, Resources, FAQs, Contact,
Privacy Policy, Terms and Conditions. The final list is confirmed at the design and content stage.

**Page fields:** page title, URL slug, navigation label, page status, SEO title, SEO description,
social sharing image, page sections, last updated date, last updated by.

**Page statuses:** draft, published, archived. Archived pages must not be publicly accessible but
remain available to administrators.

**Page sections.** Pages should be built from predefined section types rather than unrestricted
page-builder components. Potential types include hero banner, text and image, rich text content,
service cards, feature or benefit cards, call-to-action banner, statistics, testimonials, FAQs,
process steps, team introduction, contact form section, blog article listing.

Each section should support a heading, supporting text, images and links/buttons where applicable,
display order, and an active or inactive status.

Users must be able to reorder sections using drag-and-drop controls **or clear ordering controls**.

> The CMS should prevent users from creating layouts that break the approved website design.

## 5. Blog Management

**Article fields:** title, URL slug, short summary, main content, featured image, author name,
published date, category, status, SEO title, SEO description, social sharing image.

**Statuses:** draft, published, archived.

**Categories:** create, rename, assign one or more per article, reorder where required, disable
unused ones.

**Editor** must support headings, paragraphs, bold and italic, ordered and unordered lists, links,
images, quotes, and basic tables where practical. The editor must sanitise content and prevent
unsafe HTML or scripts from being saved.

**Listing:** blog listing page, individual article pages, category filtering if in the approved
design, pagination or load-more, published date, featured image, article summary, related articles
where practical. Only published articles appear publicly.

## 6. FAQ Management

**Fields:** question, answer, category, display order, active or inactive status, optional page
assignment.

**Example categories:** General, Selling, Downsizing, Fees, The advisory process, Property agents,
Legal and financial considerations. Categories must remain editable through the CMS.

**Display:** show all active FAQs, filter by category, show selected FAQs on individual pages,
control order, hide an FAQ without permanently deleting it.

## 7. Testimonial Management

**Fields:** client name, testimonial text, client location or suburb, optional client image,
optional rating, optional short heading, display order, featured status, active or inactive status.

**Display:** featured testimonials on the home page, selected testimonials within page sections, a
listing or slider where included in the design, manual ordering, hiding without deleting.

> Client names and images must only be published where the client has permission to use them.

## 8. Media Library

Shared library for images used across pages, blogs and testimonials. Supports image upload,
preview, file name, alternative text, caption where required, file size validation, file type
validation, search, reuse of existing images, and deletion protection where an image is in use.

Formats: JPG, PNG, WebP. SVG upload should only be available to trusted administrators if required.
Images should be resized or optimised automatically where practical.

## 9. Content Preview

Users must be able to preview draft content before publishing: draft pages, draft blog articles,
updated page sections, and desktop and mobile layouts where practical.

Preview URLs must not be publicly indexed by search engines.

## 10. Publishing Behaviour

Publishing must make the content available publicly, record the publishing date and the publishing
user, clear any relevant cache, and preserve the URL unless intentionally changed.

If a published slug changes, the system should either create a redirect from the old URL or warn
the user that changing it may break existing links.

## 11. SEO Controls

Pages and articles support editable SEO title, meta description, URL slug, social sharing image,
canonical URL where required, and search engine indexing control where required.

The public site should generate page titles, meta descriptions, Open Graph metadata, a structured
heading hierarchy and canonical links. Articles should include article structured data where
practical. FAQs may include FAQ structured data where appropriate.

## 12. Forms

Contact and lead enquiry forms are part of the **broader website and CRM scope, not the
content-management modules.**

The CMS may allow editing of limited form-related content: form heading, introductory text,
confirmation message, privacy consent wording.

CMS users must not be able to modify form field mappings, SyncID integration logic, notification
routing, validation logic, or automation workflows. These remain with the development team.

## 13. Audit History

Record key CMS actions: content created, edited, published, unpublished, archived, deleted; the
user responsible; the date and time.

A full content-version comparison interface is not required unless specifically approved, but the
system should retain enough information to identify who changed content. Where practical, provide
the ability to restore recently deleted or archived content.

## 14. Validation and Safety

The CMS must validate required fields, prevent duplicate URL slugs, prevent unauthorised access,
sanitise rich-text content, validate uploaded files, prevent executable files from being uploaded,
provide confirmation before destructive actions, display clear validation errors, and prevent
unpublished content from being publicly accessible.

## 15. Responsive Administration Interface

The CMS should be usable on desktop, laptop and tablet. Mobile support should cover basic content
updates, but complex page-section management may be optimised primarily for desktop and tablet use.

## 16. Acceptance Criteria

The CMS will be considered complete when:

1. Super administrators and client administrators can securely sign in.
2. Permissions correctly limit access based on user role.
3. Users can create, edit, reorder, publish and archive page content.
4. Users can manage predefined page sections.
5. Users can create and publish blog articles.
6. Users can manage blog categories.
7. Users can create, order and disable FAQs.
8. Users can create, order and disable testimonials.
9. Users can upload and reuse images.
10. Draft content can be previewed before publication.
11. Only published and active content appears publicly.
12. SEO fields are reflected correctly on the public website.
13. CMS changes are recorded against the responsible user.
14. Content editing does not allow users to break the approved website layout.
15. Publishing updates the website without requiring a code deployment.

## 17. Out of Scope

Unless added separately, the following are excluded:

- Full drag-and-drop website builder
- Editing raw HTML, CSS or JavaScript
- Custom page templates created by client users
- Website analytics dashboard
- Email marketing platform
- Newsletter sending
- Customer login portal
- Membership management
- Multilingual content
- Advanced content approval workflows
- Scheduled publishing
- AI content generation
- Full document or file-management system
- Changes to SyncID workflows or CRM automations
- Ongoing content entry after the initial agreed content population

## 18. Development Notes

The CMS should be developed as part of the existing website application. The implementation should
use structured database-backed content; keep content separate from frontend presentation; use
reusable frontend components; follow the approved website design system; avoid hard-coded page copy
where the content is intended to be editable; include database migrations and seed data where
required; include automated tests for permissions and core content workflows; and include clear
handover notes for future developers.

The developer should confirm the proposed database structure and CMS screens before completing the
full implementation.
