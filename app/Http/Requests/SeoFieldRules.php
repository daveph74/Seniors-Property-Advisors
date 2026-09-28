<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;

/**
 * What an SEO field may hold, stated once.
 *
 * Four requests write these values — a page's details, an article, the site-wide defaults and the
 * SEO screen's own narrow patch — and a limit is the sort of thing that gets tightened in one of
 * them and nowhere else. The symptom is a description that saves from the builder and is refused
 * from the SEO screen, which reads as one of the two screens being broken rather than as a rule
 * that has come apart.
 *
 * The SVG check is delegated rather than copied: `SavePageDetailsRequest::isSvg()` was already the
 * one implementation, shared with the article request.
 */
trait SeoFieldRules
{
    /** A search result shows roughly 155 characters; 320 is what may be stored. */
    public const DESCRIPTION_MAX = 320;

    public const TITLE_MAX = 160;

    public const IMAGE_MAX = 400;

    /** @return array<string, array<int, string>> */
    protected function seoRules(string $prefix = 'seo.'): array
    {
        return [
            $prefix.'title' => ['nullable', 'string', 'max:'.self::TITLE_MAX],
            $prefix.'description' => ['nullable', 'string', 'max:'.self::DESCRIPTION_MAX],
            $prefix.'image' => ['nullable', 'string', 'max:'.self::IMAGE_MAX],
            $prefix.'canonical' => ['nullable', 'string', 'max:400', 'url'],
            $prefix.'noindex' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * A sharing image may not be an SVG: social networks refuse to draw one, so the picture is
     * stored, published, and silently missing from every card the link appears in.
     */
    protected function refuseSvgImage(Validator $validator, string $key): void
    {
        if (SavePageDetailsRequest::isSvg($this->input($key))) {
            $validator->errors()->add(
                $key,
                'A sharing image cannot be an SVG — social networks will not show it. Use a JPG or PNG.',
            );
        }
    }
}
