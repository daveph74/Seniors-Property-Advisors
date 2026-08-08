# Staged: the `cms-admin-prototype` public-page work

**Nothing in this folder is wired into the application.** No route, import or autoload path reaches
it; the suite and the Vite build cannot see it. It is a staging area for work that lives on the
`cms-admin-prototype` branch and never reached `development`, kept here so it can be implemented
deliberately rather than merged wholesale.

Every file was extracted with `git show cms-admin-prototype:<path>` and is **byte-identical to the
branch**, so it can be diffed against the source at any time:

```sh
git show cms-admin-prototype:resources/js/data/content.jsx | diff - docs/new-pages/content/content.jsx
```

## What is actually new here

The branch is 33 files and +2359 lines, but most of it duplicates what already ships. **Seven of
its eight sections already exist as CMS section types**, rendering on the live site today —
verified against `PageContentStore::BLOCK_TYPES` and `resources/js/sections/registry.jsx`:

| Staged in `sections/` | Already in the CMS as | Block type |
|---|---|---|
| `Hero.jsx` | `resources/js/sections/HeroSection.jsx` | `hero` |
| `TrustCards.jsx` | `TrustCardsSection.jsx` | `trust-cards` |
| `HowItWorks.jsx` | `ProcessStepsSection.jsx` | `process-steps` |
| `WhyAgentFinder.jsx` | `WhyListSection.jsx` | `why-list` |
| `CompareAgents.jsx` | `AgentCompareSection.jsx` | `agent-compare` |
| `ForFamilies.jsx` | `FamilySection.jsx` | `family` |
| `FinalCta.jsx` | `CtaSection.jsx` | `cta` |
| `HeroFullBleed.jsx` | — **no equivalent** | would be new |

**So the four pages are not a component port. They are content** — arrangements of section types
that already exist, plus copy currently sitting in `content/content.jsx`.

Three things are genuinely new: the **suburb autocomplete**, the **full-bleed hero**, and the
**mobile nav / modal-form CSS**.

## What is in here

| Folder | What it is | Status |
|---|---|---|
| `suburb-lookup/` | Google Places proxy, its React field, the modal it feeds, config diffs, 177-line test | **port close to as-is** |
| `sections/` | The eight prototype sections | reference for seven, source for `HeroFullBleed` |
| `content/content.jsx` | The page copy — headings, body, steps, cards | **the payload**: becomes seed content |
| `pages/` | The six Inertia page wrappers | reference only — see below |
| `layout/` | `Nav`, `Footer`, `Topbar`, `PublicLayout`, `nav.js` | reference; CSS and mobile drawer are the useful parts |
| `styles/app.css.diff` | ~300 lines: `.combo`, `.hero-full`, `.nav-mobile`, `.modal`, `.field` | port the rules the chosen items need |
| `tests/PublicPagesTest.php` | Asserts the static routes render | rewrite if the pages become CMS content |
| `routes.diff` | The routes the prototype added | **do not port** — see below |

## Recommended implementation

A recommendation, not a decision. Each item is independent and gets its own branch and commit.

### 1. The four pages, as CMS content

Seed them as section trees built from the seven existing types, with `content/content.jsx` as their
data. `resources/content/pages/home.json` is exactly this shape already, and `ContentSeeder` loads
it keyed on slug and idempotently.

Page state in the database today:

| Page | Slug | Status |
|---|---|---|
| How it works | `how-it-works` | exists, draft (id 5) — populate it |
| Why Agent Finder | `why-agent-finder` | does not exist — create |
| Compare agents | `compare-agents` | does not exist — create |
| For families | `for-families` | does not exist — create |

No new components and no new routes: `PageController` already serves any published page at its
slug. Every word stays editable in the CMS, which is what §18 asks for and what the prototype's
static version gives up.

### 2. Suburb autocomplete

The most portable thing here, and the best built. `SuburbLookupController` keeps the Google Places
key server-side, caches, throttles at 60/min, and degrades to an empty-but-successful payload on any
outage so the field falls back to free text — a Places failure can never block the form. It arrives
with its own test.

Needs `GOOGLE_PLACES_API_KEY` (see `env-example.diff`) and the `config/services.php` entry
(`services.php.diff`). The `.combo` rules in `styles/app.css.diff` are its styling.

### 3. Full-bleed hero

The only section with no CMS equivalent, so it becomes a new block type. Per `CLAUDE.md` that means
`PageContentStore::BLOCK_TYPES`, `resources/js/sections/childTypes.js` and the registry — never the
database. `CmsBuilderTest::test_the_js_type_mirror_matches_php` will hold the JS and PHP in step.

Its background was `public/images/full_bg.png` on the branch, **a 1.7MB PNG** — a photograph in a
lossless format, which is where nearly all of that went. **Done:** it is now `hero-home.jpg` in the
media library at 1600×900, about 125KB, seeded by `MediaSeeder`.

Note that `php artisan media:optimise` would **not** have helped, whatever this file said before:
`ImageOptimiser::optimise()` returns early for anything 2400px or smaller on its long edge, and it
cannot change format. The work was a one-off resize and a PNG-to-JPEG conversion.

### 4. Mobile nav and modal CSS

Worth taking regardless of the rest — the public nav has no mobile drawer today. `.nav-toggle` and
`.nav-mobile` in `styles/app.css.diff`, plus the modal's form rules (`.field`, `.err-msg`, `.req`).

## What was deliberately left out

- **`public/images/full_bg.png`** — 1.7MB. Too big to stage, and too big to commit unoptimised.
  Since resolved: it is `hero-home.jpg` in the media library, with `share-card.jpg` cropped from it.
- **`.gitignore` changes** — unrelated agent-tooling entries.
- **`nav.js`** (staged for reference only, **do not port**) — it hardcodes the menu, and the
  Navigation module already owns menus from the database. Two sources would disagree.
- **`routes.diff`** (staged for reference only, **do not port**) — its
  `Route::get('/how-it-works', fn () => Inertia::render(...))` lines would shadow the CMS page at
  the same slug. The prototype knew this: its own comment keeps the landing page off `/` "so the CMS
  home page stays the address readers reach".
- **`pages/*.jsx`** — the wrappers exist only to render the hardcoded sections. Useful to read for
  section order; nothing to merge.

## One thing worth taking on its own

`config/inertia.php` on the branch is unrelated to the pages and fixes a real bug. The package
default resolves `resources/js/pages`; this project uses `Pages`, and on a case-sensitive filesystem
the lowercase default silently resolves nothing — which makes `assertInertia(...)->component(...)`
fail. It does not bite on Windows, so it will surface first in CI or on a Linux host.

```sh
git show cms-admin-prototype:config/inertia.php > config/inertia.php
```

Worth doing independently of everything above.
