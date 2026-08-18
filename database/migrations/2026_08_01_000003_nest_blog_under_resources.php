<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const CHILD = ['href' => '/blog', 'label' => 'Blog'];

    private const PARENT_LABEL = 'Resources';

    /**
     * Blog was added to the top level, which pushed the menu 78px past the content column and
     * left the "Find My Agent" button hanging outside the grid. It belongs under Resources —
     * which is also where FAQs and the guides will go, so the menu stops growing sideways.
     *
     * Idempotent: it only moves a top-level /blog link, and only adds the child when Resources
     * does not already have one, so re-running cannot duplicate it or undo a hand edit.
     */
    public function up(): void
    {
        $this->change(function (array $globals) {
            $links = $globals['nav']['links'] ?? [];
            $rebuilt = [];

            foreach ($links as $link) {
                if (($link['href'] ?? null) === self::CHILD['href']) {
                    continue;
                }

                if (($link['label'] ?? null) === self::PARENT_LABEL) {
                    $link['href'] = null;
                    $link['children'] = $this->withChild($link['children'] ?? []);
                }

                $rebuilt[] = $link;
            }

            $globals['nav']['links'] = $rebuilt;

            return $globals;
        });
    }

    /**
     * Puts Blog back at the top level, so the menu is the shape it was before.
     */
    public function down(): void
    {
        $this->change(function (array $globals) {
            $rebuilt = [];

            foreach ($globals['nav']['links'] ?? [] as $link) {
                if (($link['label'] ?? null) === self::PARENT_LABEL) {
                    $link['href'] = '#';
                    unset($link['children']);
                }

                $rebuilt[] = $link;
            }

            $rebuilt[] = self::CHILD;
            $globals['nav']['links'] = $rebuilt;

            return $globals;
        });
    }

    private function withChild(array $children): array
    {
        foreach ($children as $child) {
            if (is_array($child) && ($child['href'] ?? null) === self::CHILD['href']) {
                return $children;
            }
        }

        $children[] = self::CHILD;

        return $children;
    }

    private function change(callable $edit): void
    {
        $row = DB::table('settings')->where('key', 'globals')->first();

        if ($row === null) {
            return;
        }

        $globals = json_decode($row->value ?? '[]', true);

        if (! is_array($globals) || ! isset($globals['nav']['links'])) {
            return;
        }

        DB::table('settings')->where('key', 'globals')->update([
            'value' => json_encode($edit($globals)),
            'updated_at' => now(),
        ]);
    }
};
