<?php

namespace App\Enquiries;

/**
 * The questions Agent Finder asks, and the answers it accepts.
 *
 * Scope §12 keeps form fields and their validation in code, so this is where they live — one
 * catalogue read by the form request, by the CMS presenter, and mirrored in
 * `resources/js/components/findMyAgentOptions.js` for the browser. `FindMyAgentOptionsParityTest`
 * holds the two copies together; nothing else can.
 *
 * Keys are stored, labels are not. The form used to send the *position* of the chosen card, which
 * made the order of a JavaScript array the meaning of every answer already in the database —
 * reordering the cards would silently rewrite history, and no test could have caught it. A key
 * survives reordering, and re-wording an answer stays a display change rather than a data migration.
 */
class FindMyAgentOptions
{
    public const PROPERTY_TYPES = [
        'house' => 'House',
        'townhouse' => 'Townhouse',
        'apartment' => 'Apartment',
        'acreage' => 'Acreage',
    ];

    public const TIMELINES = [
        'within_3_months' => 'Now',
        'in_3_6_months' => 'In 3 – 6 months',
        'in_6_12_months' => 'In 6 – 12 months',
        'just_exploring' => 'Just exploring',
    ];

    public const BEST_TIMES = [
        'morning' => 'Morning',
        'afternoon' => 'Afternoon',
        'evening' => 'Evening',
    ];

    /**
     * What the CMS shows, in the order it reads best: where the property is, what it is, when they
     * are hoping to sell, when to ring.
     *
     * Only answers that are actually present are returned, so a contact-form enquiry produces an
     * empty list and the screen renders nothing rather than a row of dashes. An answer this catalogue
     * no longer recognises falls back to its stored key — losing the wording is better than dropping
     * the fact that they answered.
     */
    public static function describe(?array $details): array
    {
        if ($details === null) {
            return [];
        }

        $answers = [
            ['label' => 'Suburb', 'value' => self::place($details['location'] ?? null)],
            ['label' => 'Property type', 'value' => self::label(self::PROPERTY_TYPES, $details['property_type'] ?? null)],
            ['label' => 'Looking to sell', 'value' => self::label(self::TIMELINES, $details['timeline'] ?? null)],
            ['label' => 'Best time to call', 'value' => self::label(self::BEST_TIMES, $details['best_time'] ?? null)],
        ];

        return array_values(array_filter($answers, fn (array $answer) => filled($answer['value'])));
    }

    public static function label(array $group, ?string $key): ?string
    {
        return $key === null ? null : ($group[$key] ?? $key);
    }

    /** "Mosman NSW 2088" from whichever parts the lookup returned — it may only have a name. */
    private static function place(?array $location): ?string
    {
        if ($location === null) {
            return null;
        }

        $parts = array_filter([
            $location['suburb'] ?? null,
            $location['state'] ?? null,
            $location['postcode'] ?? null,
        ], 'filled');

        return $parts === [] ? null : implode(' ', $parts);
    }
}
