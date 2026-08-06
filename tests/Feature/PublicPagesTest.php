<?php

namespace Tests\Feature;

use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class PublicPagesTest extends TestCase
{
    /**
     * Each public section is its own route. Without this, renaming a route or a
     * page component fails silently — nothing else asserts the wiring.
     *
     * @return array<string, array{string, string}>
     */
    public static function publicRoutes(): array
    {
        return [
            'home' => ['/', 'Home'],
            'how it works' => ['/how-it-works', 'HowItWorks'],
            'why agent finder' => ['/why-agent-finder', 'WhyAgentFinder'],
            'compare agents' => ['/compare-agents', 'CompareAgents'],
            'for families' => ['/for-families', 'ForFamilies'],
            'hero preview' => ['/hero-preview', 'HeroPreview'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('publicRoutes')]
    public function test_it_renders_the_expected_inertia_page(string $uri, string $component): void
    {
        $this->get($uri)
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->component($component));
    }
}
