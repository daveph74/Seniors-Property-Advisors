# Seniors Property Advisors — Design Overview

Derived from the official **Seniors Property Advisors Brand Style Guide 2026**
(prepared by That Marketing Company). This document is the source of truth for
brand colour, typography, logo usage and tone when building or extending the
Agent Finder product.

> **Note on naming:** The style guide names the brand **Seniors Property
> Advisors** (SPA). Earlier prototype copy used "Australian Seniors Property
> Advisors (ASPA)"; treat that as legacy and prefer "Seniors Property Advisors"
> going forward.

## Brand messaging

The brand speaks to Australians over 60 and the families supporting them.
Positioning line from the guide:

> **Trusted Property Guidance for Australians over 60** — helping seniors and
> families find carefully vetted real estate agents for downsizing, retirement
> living, and aged care transitions.

Supporting messages seen throughout the guide:

- "You should never navigate selling alone."
- "Selling your home later in life?"
- "Carefully matched. Personally supported."
- "For many Australians, selling the family home can feel overwhelming. We
  provide calm, trusted support by connecting you with experienced agents."

Primary call to action: **CALL US TODAY**.

Tone: calm, supportive, trustworthy. Reassure; never pressure. Lead with ease
and confidence, not sales urgency.

## Colour palette

The five-colour palette from the guide. Colours may be used at reduced opacity
("as percentages") when needed. Derive tints/shades from these — do not
introduce new hues.

| Role | HEX | RGB | CMYK | Usage |
|------|-----|-----|------|-------|
| Primary navy | `#0D223F` | 13, 34, 63 | 98, 84, 46, 52 | Headers, footers, dark panels, primary text, primary buttons |
| Mid blue | `#5894D5` | 88, 148, 213 | 65, 33, 0, 0 | Primary accent — buttons, links, highlights, logo mark outer |
| Pale blue | `#8FC2F7` | 143, 194, 247 | 40, 14, 0, 0 | Soft backgrounds, tints, secondary highlights, "over 60" accent word |
| Cream | `#FFF9EE` | 255, 249, 238 | 0, 2, 5, 0 | Warm section background / surface |
| Off-white | `#FFFDFA` | 255, 253, 250 | 0, 0, 1, 0 | Base page background |

### Token mapping (for the implementation)

The React implementation centralises the palette in `resources/css/app.css`
under `:root`. To adopt the exact style-guide palette:

```css
--navy: #0D223F;   /* primary navy   */
--blue: #5894D5;   /* mid blue accent */
```

Suggested derived tokens: pale-blue tint `#8FC2F7` for soft washes, `#FFF9EE`
(cream) or `#FFFDFA` (off-white) for warm surfaces in place of the current cool
`--blue-wash` values.

## Typography

The brand font is **Arboria** — chosen for "its balance of warmth and high
legibility."

- **Headings:** Arboria Bold. Confident, tight leading, sentence case.
- **Body:** Arboria Regular for calm, legible paragraphs.
- Arboria is a commercial typeface (not on Google Fonts). Production must
  license and self-host it (e.g. via Adobe Fonts / a webfont licence). Until
  then, a close, freely available fallback such as **DM Sans** (currently used
  in the prototype) keeps the airy, geometric feel.

Fallback stack: `"Arboria", "DM Sans", -apple-system, system-ui, sans-serif`.

## Logo

- **Master logo:** pentagon mark (mid-blue `#5894D5` outer ring enclosing a
  navy `#0D223F` inner pentagon) beside the wordmark "Seniors" (mid blue) /
  "Property Advisors" (navy).
- Use the master logo as supplied (EPS/AI for print, PNG for transparent web,
  JPG for Office/online, PDF for vector reference). Never recreate it.
- **Approved backgrounds:** white, pale blue `#8FC2F7`, navy `#0D223F`
  (reversed to white), or photography with enough clear space and contrast.
- **Clear space:** maintain a margin of `x` (the cap height of the mark) on all
  sides. **Minimum size:** ~20mm wide mark.
- **Don'ts:** don't distort/stretch, rotate, recolour backgrounds outside the
  palette, change the fonts, add drop-shadow/bevel/emboss, or place on busy
  imagery.

## Layout & imagery

- Generous whitespace; calm, uncluttered sections.
- Warm, authentic photography of seniors (and their adult children) in positive,
  supported moments — reviewing paperwork with an advisor, at home, together.
  Avoid generic stock.
- The pentagon mark is reused as a subtle decorative/watermark motif behind
  panels and cards.
- Two-column splits alternating copy and imagery; full-width navy or cream
  feature bands.

## Motion & interactions

Favour smooth, gentle transitions over flashy animation — soft hover shade
changes, subtle fades on scroll, easing that feels unhurried. Respect
`prefers-reduced-motion`.

## Credits

Brand guide by **That Marketing Company** (03 9775 1841), © 2026.
