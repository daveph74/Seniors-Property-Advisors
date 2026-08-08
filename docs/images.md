# Where the pictures come from

The seeded pages used to hotlink six photographs from `images.unsplash.com`. That meant a site which
otherwise makes no third-party request pulled six images off somebody else's host, none of them
swappable from the CMS, none counted by `MediaController::usage()`, and none guaranteed to still be
there next year.

They are now ordinary media rows. The source files are committed under `resources/media/` — seeder
input, exactly as `resources/content/` is — and `MediaSeeder` writes them into the media bucket under
fixed keys under `2026/08/`, which is what lets a committed page file name the address it wants.

**`docker compose up -d` is required for these to render.** The bytes live on the `s3` disk like every
other upload. Without it the rows still seed and the sizes are still correct, so shared links keep
their `og:image:width`, but the pictures themselves 404.

## The set

| key | what it is | size |
|---|---|---|
| `hero-home.jpg` | Home page hero background | 1600×900 |
| `share-card.jpg` | Default sharing image, cropped from the hero | 1200×630 |
| `advisor-meeting.jpg` | An advisor showing a couple a tablet — home, "How it works, in short" | 1200×900 |
| `family-paperwork.jpg` | `/for-families` | 1200×1000 |
| `home-exterior.jpg` | `/why-agent-finder` | 1140×1425 |
| `rachel.jpg` | Testimonial portrait on `/for-families` | 160×160 |
| `agent-sw.jpg`, `agent-jp.jpg`, `agent-em.jpg` | The three illustrated agents on `/compare-agents` | 160×160 |

The large ones are cropped to the aspect ratio of the box they sit in, so the browser is not
discarding pixels we paid to send. `hero-home.jpg` is a **pure downscale with no crop** — the scrim
stops in `app.css` were set by measurement against the bright window in that frame, and moving the
crop would make them wrong.

## Provenance

- `hero-home.jpg` / `share-card.jpg` — from `full_bg.png`, committed with the original hero design.
- `advisor-meeting.jpg` — from `hero-advisor.jpg`, likewise.
- `family-paperwork.jpg`, `home-exterior.jpg`, `rachel.jpg` — Unsplash photo ids `1559925393`,
  `1556909114` and `1494790108377`, fetched 7 August 2026 under the Unsplash licence, which permits
  commercial use without attribution.

## The three agent avatars are not photographs

They are generated: initials in white on a brand navy, 160×160.

The comparison table on `/compare-agents` is a **worked example** — the agents, suburbs, commission
rates and sales figures are invented to show the format, and the section now says so. Putting real
people's faces next to invented commission rates and advisor verdicts is a likeness problem that
labelling the table does not fix, so the illustration is illustrated throughout. If real agents are
ever shown there, real photographs go with them.

## Adding or replacing one

Through the CMS, like any other image: upload to the media library and point the field at the new
`/media/...` address. Nothing here is special-cased.

To change one of the seeded defaults for *new* installations as well, replace the file in
`resources/media/` and re-run `php artisan db:seed --class=MediaSeeder`, which is idempotent on key.
