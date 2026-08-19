<?php

namespace App\Content;

use App\Models\Page;
use App\Models\Setting;

/**
 * Site settings: the defaults and switches a super administrator sets once.
 *
 * Its own `settings` row rather than a corner of `globals`, because the two are reached by
 * different people. `globals` is wording a client administrator edits — the footer blurb, the
 * announcement bar. Most of this is super-administrator territory (`settings.manage`).
 *
 * The SEO defaults are the exception, and they are why `merge()` exists. They are edited on the SEO
 * screen under `seo.manage`, so this row now has two writers — which used to be stated here as the
 * thing that must never happen, because `/cms/settings` replaced the row wholesale and a save from
 * either screen would have erased the other's half of it. Both writers go through `merge()` now, so
 * a save says what it changes rather than what the whole row should be.
 *
 * Nothing here duplicates `globals`. The phone number, address and copyright line live there and
 * stay there; a value stored twice is a value that disagrees with itself.
 */
class Site
{
    public const KEY = 'site';

    public static function all(): array
    {
        return Setting::find(self::KEY)?->value ?? [];
    }

    /**
     * Writes the keys given and leaves the rest alone.
     *
     * Two screens edit this row under two abilities, so a writer that sent the whole thing would be
     * sending its own idea of the half it cannot see — and the SEO screen has no tracking ids to
     * send, so a save there would have cleared them. Top-level keys only: every one of them is a
     * whole area of the screen, and a caller wanting to change one field inside `seo` reads the
     * group, changes it, and passes the group back.
     *
     * @param  array<string, mixed>  $changes
     * @return array<string, mixed> the row as it now stands
     */
    public static function merge(array $changes): array
    {
        $value = array_replace(self::all(), $changes);

        Setting::updateOrCreate(['key' => self::KEY], ['value' => $value]);

        return $value;
    }

    /**
     * What the public pages need. Read on every request like `globals()` — there is no cache to
     * stale, and a settings change is meant to be immediate.
     */
    public static function forPublic(): array
    {
        $site = self::all();

        return [
            'disclaimer' => $site['legal']['disclaimer'] ?? null,
            'privacyUrl' => self::pageUrl($site['legal']['privacyPage'] ?? null),
            'social' => array_values(array_filter([
                self::link('Facebook', $site['social']['facebook'] ?? null),
                self::link('LinkedIn', $site['social']['linkedin'] ?? null),
            ])),
        ];
    }

    /** The defaults `Seo::head()` falls back to. */
    public static function seoDefaults(): array
    {
        $site = self::all();

        return [
            'name' => $site['name'] ?? null,
            'titleFormat' => $site['seo']['titleFormat'] ?? null,
            'description' => $site['seo']['description'] ?? null,
            'image' => $site['seo']['image'] ?? null,
        ];
    }

    /** The two id formats, used to validate on the way in and again on the way out. */
    public const GA4 = '/^G-[A-Z0-9]{4,20}$/';

    public const GTM = '/^GTM-[A-Z0-9]{4,20}$/';

    /**
     * Analytics ids, re-checked against their format here.
     *
     * Validation at the write is the first guard; this is the second, for a row edited by hand or
     * restored from an older shape. Blade's escaping is a third — a quote comes out as `&#039;`,
     * and entities are not decoded inside a `<script>`, so an injected statement lands as inert
     * text rather than running. Relying on that alone would still be wrong: it would leave a
     * broken analytics tag on every page, and it holds only while this stays a `{{ }}` echo.
     */
    public static function tracking(): array
    {
        $site = self::all();

        return array_filter([
            'ga4' => self::matching($site['tracking']['ga4'] ?? null, self::GA4),
            'gtm' => self::matching($site['tracking']['gtm'] ?? null, self::GTM),
        ]);
    }

    private static function matching(?string $value, string $pattern): ?string
    {
        $value = trim((string) $value);

        return preg_match($pattern, $value) === 1 ? $value : null;
    }

    /**
     * Only a published page. An unpublished one is not on the website, so linking a reader to it
     * from a consent line would be a 404 at the exact moment they are being asked to agree to it.
     */
    private static function pageUrl(?int $pageId): ?string
    {
        if ($pageId === null) {
            return null;
        }

        return Page::where('id', $pageId)->where('status', 'published')->value('url');
    }

    private static function link(string $label, ?string $href): ?array
    {
        return filled($href) ? ['label' => $label, 'href' => $href] : null;
    }
}
