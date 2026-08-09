# Security review — pre-launch

Whole-application pass over the public site and the CMS: the public form and suburb lookup, file
upload and SVG handling, the HTML purifier, authentication and sessions, the permission matrix,
mass assignment, and what the media route serves.

**Findings only. Nothing here has been fixed** — several are deliberate trade-offs already recorded
in the scope or in `docs/TODO.md`, and which of the rest are worth acting on is a decision, not a
defect list.

Reviewed at commit `01772f3`, on top of the search, notifications, enquiry and pagination work.

---

## Findings

### 1. A visitor-supplied value is interpolated into an outbound Google URL — medium

`app/Http/Controllers/SuburbLookupController.php:150` builds the request as
`self::DETAILS_URL.$placeId`, and `place_id` is validated only as `string|max:255`
(line 34). A value containing `../` walks back up the path, so a request can be steered to a
different endpoint on `places.googleapis.com` **with the site's API key attached**.

The host is fixed, so this is not full SSRF — the reachable surface is other Google Places
endpoints. But this is an unauthenticated public endpoint (throttled to 60/minute), and the key is
billable.

`rawurlencode($placeId)`, or a `regex:/^[A-Za-z0-9_-]+$/` rule, closes it.

### 2. Page revision restore is not behind `content.restore` — medium

`routes/web.php:61`. A client administrator can roll any page back to an earlier published version,
but the same account cannot unarchive a page (`routes/web.php:70`) and cannot delete anything. §2
puts restoring archived content with super administrators only, so the two sit either side of a line
that was meant to be one line.

Restoring a revision is arguably as consequential as unarchiving — it replaces the current draft.
Already carried in `docs/TODO.md` as an open permissions decision; this review agrees it is real.

### 3. SVG bytes are never inspected — medium, currently accepted

`MediaController::store()` sets `$bytes = $extension === 'svg' ? null : …`, which skips the byte
sniff, the megapixel guard and the optimiser for SVGs entirely. Every other format is read and
rejected if the bytes disagree with the extension.

Two things hold the risk down: only a super administrator may upload one
(`media.upload_svg`, `sign()`), and `show()` serves every file with `X-Content-Type-Options: nosniff`
and `Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'`.

The exposure is that this safety lives **entirely in response headers**, so it depends on those
headers surviving whatever CDN or proxy ends up in front of the route. `docs/TODO.md` already states
the choice: parse the XML and reject scripts, or drop SVG support.

### 4. An enquiry is marked read by a GET — low

`EnquiryController::index()` writes `read_at` when `?open={id}` is present. `SESSION_SAME_SITE` is
`lax`, so a cross-site sub-resource (an `<img>` tag) will not carry the session cookie — but a link
an administrator clicks will, and link prefetching by a browser or extension would too.

Impact is confined to the unread badge; no content changes and nothing is disclosed. Noted because
it is a state change on a safe method, which is worth knowing before anything else copies the
pattern.

### 5. `target="_blank"` is allowed without a forced `rel` — low

`app/Content/Html.php` sets `Attr.AllowedFrameTargets => ['_blank']` with `HTML.Nofollow => false`,
so an editor's link can open a new tab without `rel="noopener"`. Every current browser implies
`noopener` for `target="_blank"`, so this is close to historical. `HTML.TargetNoopener` would settle
it permanently.

### 6. Password policy is length only — low

`Password::min(10)` in `SaveUserRequest` and `UpdatePasswordRequest`. No breach check
(`->uncompromised()`), no complexity rule. With two administrator accounts and a 5-attempt
rate limit this is defensible; `->uncompromised()` is one call and would rule out the passwords that
actually get guessed.

### 7. Every media file is world-readable — low, by design

`/media/{key}` sits outside the authenticated group (`routes/web.php:162`). Upload keys are ULIDs
and unguessable; seeded keys (`2026/08/rachel.jpg`) are not. For a marketing site's images this is
the intent.

Worth stating plainly for whoever maintains it: **the media library is public**. Nothing
confidential should ever be uploaded to it, and the upload allowlist being images-only is what keeps
that true.

### 8. Deployment gates are documented but unticked — informational

- `SESSION_SECURE_COOKIE=true` once served over HTTPS (`config/session.php:172` defaults to unset).
- `APP_DEBUG=false` in production — `.env.example` ships `true`, correctly, for local.
- `SESSION_LIFETIME` chosen deliberately rather than left at 120 minutes.
- The Google Places key should be IP-restricted at the Google console, per `.env.example`.

None is a code defect. All four are things that have to be true on the day.

---

## What holds up

Recorded so a later reviewer knows it was checked, not skipped.

- **No raw SQL anywhere** except `app/Cms/Like.php:40`, which binds its pattern and takes its column
  list from caller constants, with the explicit `ESCAPE` clause SQLite needs.
- **Sign-in** rate limits on email + IP (5) *and* route (10/min), returns one generic failure for
  both wrong-email and wrong-password so accounts cannot be enumerated, regenerates the session, and
  refuses deactivated accounts.
- **Upload** is extension-allowlisted before a presigned URL is issued, then the bytes are sniffed on
  the way back and the object is deleted if they disagree with the name; a megapixel guard blocks
  decompression bombs.
- **Media serving** resolves the key against the database before touching the disk, so no path
  traversal reaches storage.
- **Article HTML** is purified on the way in against a tight allowlist with schemes limited to
  http/https/mailto/tel, which is what makes the single `dangerouslySetInnerHTML` in
  `Pages/Article.jsx:98` safe.
- **Section trees** run every string through `strip_tags` (`SaveSectionsRequest::sanitise`).
- **Mass assignment**: no model uses `$guarded = []`; no `env()` call outside `config/`.
- **The permission middleware** checks `is_active` as well as the ability, so disabling an account
  takes effect on the next request rather than the next login.
- **Deleting an enquiry** deliberately keeps the sender's name out of the audit log — erasing
  somebody while minting a permanent copy of their name would not be erasing them.
- **Pagination** takes its page size from an allowlist, so `?per_page=1000000` cannot be used to pull
  a whole table.

---

## Not covered

- No dependency vulnerability scan (`composer audit`, `npm audit`) — worth running separately.
- No live testing against a deployed environment; this is a code review.
- Infrastructure: S3 bucket policy, CDN configuration and TLS are outside the repository.
