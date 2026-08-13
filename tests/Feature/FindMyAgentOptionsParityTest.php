<?php

namespace Tests\Feature;

use App\Enquiries\FindMyAgentOptions;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The answers exist twice — in PHP because the server validates them and the CMS prints them, and in
 * JavaScript because the browser draws the cards. Two copies of anything drift, and this pair would
 * drift silently: the server would refuse an answer the form had just offered, and the person filling
 * it in would be told to choose one of the options listed while looking at the option they chose.
 *
 * So this reads the JavaScript and holds it to the PHP, which is the one that decides.
 */
class FindMyAgentOptionsParityTest extends TestCase
{
    private const MIRROR = 'resources/js/components/findMyAgentOptions.js';

    public static function catalogues(): array
    {
        return [
            'property types' => ['PROPERTY_TYPES', FindMyAgentOptions::PROPERTY_TYPES],
            'timelines' => ['TIMELINES', FindMyAgentOptions::TIMELINES],
            'best times' => ['BEST_TIMES', FindMyAgentOptions::BEST_TIMES],
        ];
    }

    #[DataProvider('catalogues')]
    public function test_the_browser_offers_exactly_what_the_server_accepts(string $name, array $expected): void
    {
        $this->assertSame(
            $expected,
            $this->parse($name),
            "resources/js/components/findMyAgentOptions.js no longer matches FindMyAgentOptions::{$name}.",
        );
    }

    public function test_the_mirror_is_where_this_test_thinks_it_is(): void
    {
        /* Renaming or moving the file would otherwise turn every assertion above into a silent pass
           against an empty parse. */
        $this->assertFileExists(base_path(self::MIRROR));
    }

    /** @return array<string, string> value => label, in the order the file declares them */
    private function parse(string $name): array
    {
        $source = file_get_contents(base_path(self::MIRROR));

        preg_match('/export const '.preg_quote($name, '/').' = \[(.*?)\];/s', $source, $block);

        preg_match_all(
            "/\{\s*value:\s*'([^']+)',\s*label:\s*'([^']+)'/",
            $block[1] ?? '',
            $found,
            PREG_SET_ORDER,
        );

        return array_reduce(
            $found,
            fn (array $carry, array $match) => $carry + [$match[1] => $match[2]],
            [],
        );
    }
}
