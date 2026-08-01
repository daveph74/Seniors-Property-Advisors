<?php

namespace App\Content;

use Illuminate\Support\Str;

class StarterLayouts
{
    public const OPTIONS = [
        'blank' => 'Blank page',
        'standard' => 'Standard content',
        'service' => 'Service page',
        'landing' => 'Landing page',
        'blog' => 'Blog listing',
    ];

    public static function options(): array
    {
        return array_map(
            fn ($key, $label) => ['key' => $key, 'label' => $label],
            array_keys(self::OPTIONS),
            self::OPTIONS,
        );
    }

    public static function sections(string $key): array
    {
        return match ($key) {
            'standard' => self::standard(),
            'service' => self::service(),
            'landing' => self::landing(),
            'blog' => self::blog(),
            default => [],
        };
    }

    /**
     * The listing page. The blog-list block pulls published articles from the content
     * library, so the heading and intro stay editable while the cards stay automatic.
     */
    private static function blog(): array
    {
        return [
            self::block('blog-list', 'Blog articles', [
                'eyebrow' => 'Articles',
                'heading' => 'Advice for selling and downsizing',
                'headingEm' => '',
                'lead' => 'Straightforward guidance from our advisors, written for homeowners over 50 and the families helping them.',
                'category' => '',
                'limit' => 9,
                'showMore' => true,
                'showFilters' => true,
                'articles' => [],
            ]),
            self::blogCta(),
        ];
    }

    /**
     * The generic cta() is a placeholder for a blank page, which is right when nobody knows what
     * the page is for. A blog listing does know: whoever reaches the bottom has just been reading
     * about selling, so this one ships with real copy and the same two actions the home page
     * closes with, rather than "Add a short line about getting in touch".
     */
    private static function blogCta(): array
    {
        return self::block('cta', 'Call to action', [
            'eyebrow' => 'Ready when you are',
            'heading' => 'Talk it through with',
            'headingEm' => 'someone independent',
            'body' => 'Reading is a good place to start. When you are ready, a short conversation '
                .'will tell you where you stand — with no pressure and nothing to sign.',
            'buttons' => [
                ['label' => 'Get Started', 'variant' => 'secondary', 'action' => 'open-finder', 'arrow' => true],
                ['label' => 'Call 1300 277 228', 'variant' => 'ghost', 'href' => 'tel:1300277228', 'onNavy' => true],
            ],
            'trustMarks' => ['Independent advice', 'No agent commissions', 'Australian owned'],
        ]);
    }

    private static function standard(): array
    {
        return [
            self::section('Intro', [
                self::row([
                    self::column([
                        self::heading('Your heading goes here'),
                        self::richText('Introduce this page in a sentence or two. Select any block to edit it.'),
                    ]),
                ]),
            ]),
        ];
    }

    private static function service(): array
    {
        return [
            self::hero('The service you offer', 'Explain who it helps.'),
            self::section('What is included', [
                self::row([
                    self::column([
                        self::heading('What is included'),
                        self::block('benefit-list', 'Benefits', [
                            'items' => [],
                            'spaceAbove' => 'none',
                            'spaceBelow' => 'none',
                        ]),
                    ]),
                ]),
            ]),
            self::cta(),
        ];
    }

    private static function landing(): array
    {
        return [
            self::hero('A clear promise', 'Say what visitors get, in one line.'),
            self::block('trust-cards', 'Trust cards', [
                'eyebrow' => '', 'heading' => 'Why people choose us', 'headingEm' => '',
                'lead' => '', 'cta' => ['label' => '', 'href' => '', 'arrow' => true], 'items' => [],
            ]),
            self::block('process-steps', 'Process steps', [
                'eyebrow' => '', 'heading' => 'How it works', 'headingEm' => '',
                'lead' => '', 'items' => [],
            ]),
            self::cta(),
        ];
    }

    private static function hero(string $heading, string $lead): array
    {
        return self::block('hero', 'Hero banner', [
            'eyebrow' => '', 'heading' => $heading, 'headingEm' => '', 'subhead' => '', 'lead' => $lead,
            'ctas' => [], 'avatars' => [],
            'rating' => ['stars' => '', 'label' => '', 'note' => ''],
            'image' => ['src' => '', 'alt' => ''],
            'ratingCard' => ['label' => '', 'note' => ''],
            'savingCard' => ['label' => '', 'value' => '', 'note' => ''],
            'steps' => [],
        ]);
    }

    private static function cta(): array
    {
        return self::block('cta', 'Call to action', [
            'eyebrow' => '', 'heading' => 'Ready to talk?', 'headingEm' => '',
            'body' => 'Add a short line about getting in touch.',
            'buttons' => [], 'trustMarks' => [],
        ]);
    }

    private static function section(string $label, array $children): array
    {
        return self::block('section', $label, [
            'width' => 'standard', 'contentAlign' => 'left', 'height' => 'comfortable',
            'spacing' => 'medium', 'background' => 'white', 'textTheme' => 'dark',
        ], $children);
    }

    private static function row(array $children): array
    {
        return self::block('row', 'Row', [], $children);
    }

    private static function column(array $children): array
    {
        return self::block('column', 'Column 1', ['alignAcross' => 'fill', 'alignDown' => 'top'], $children);
    }

    private static function heading(string $text): array
    {
        return self::block('heading', 'Heading', [
            'heading' => $text, 'headingEm' => '', 'level' => 'h2',
            'align' => 'left', 'spaceAbove' => 'none', 'spaceBelow' => 'none',
        ]);
    }

    private static function richText(string $body): array
    {
        return self::block('rich-text', 'Rich text', [
            'body' => $body, 'align' => 'left', 'spaceAbove' => 'none', 'spaceBelow' => 'none',
        ]);
    }

    private static function block(string $type, string $label, array $data, ?array $children = null): array
    {
        $block = [
            'id' => $type.'-'.Str::lower(Str::random(6)),
            'type' => $type,
            'label' => $label,
            'active' => true,
            'anchor' => null,
            'data' => $data,
        ];

        return $children === null ? $block : $block + ['children' => $children];
    }
}
