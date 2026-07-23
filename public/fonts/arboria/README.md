# Arboria brand font

Arboria is the Seniors Property Advisors brand typeface. It is a **commercial
font** and is not included in this repo. Purchase/licence it (e.g. via Adobe
Fonts or the foundry) and drop the self-hosted webfont files here.

Expected files (referenced by the `@font-face` rules in
`resources/css/app.css`):

```
public/fonts/arboria/
├── Arboria-Book.woff2     # weight 400
├── Arboria-Book.woff
├── Arboria-Medium.woff2   # weight 500
├── Arboria-Medium.woff
├── Arboria-Bold.woff2     # weight 700
└── Arboria-Bold.woff
```

Until these exist, the site falls back to **DM Sans** automatically — no build
error, no broken layout. Once added, Arboria renders with no further code
changes.

If your licence ships different weight names (e.g. Regular instead of Book),
either rename the files to match the list above or update the `src` URLs in
`resources/css/app.css`.
