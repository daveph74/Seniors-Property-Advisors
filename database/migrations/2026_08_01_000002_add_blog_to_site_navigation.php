<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const LINK = ['href' => '/blog', 'label' => 'Blog'];

    /**
     * The blog had no way in — nothing linked to /blog. Site chrome lives in the `globals`
     * setting, and the navigation module is still a prototype, so a migration is the only way
     * to change a live menu today.
     *
     * It adds the link only when one is not already there, so re-running cannot duplicate it
     * or undo a hand edit — the same rule the default home page migration follows.
     */
    public function up(): void
    {
        $this->change(function (array $globals) {
            $links = $globals['nav']['links'] ?? [];

            if (! $this->holdsBlog($links)) {
                $links[] = self::LINK;
                $globals['nav']['links'] = $links;
            }

            foreach ($globals['footer']['columns'] ?? [] as $i => $column) {
                if (($column['heading'] ?? '') !== 'Resources' || $this->holdsBlog($column['links'] ?? [])) {
                    continue;
                }

                $globals['footer']['columns'][$i]['links'][] = self::LINK;
            }

            return $globals;
        });
    }

    /**
     * Safe to reverse, unlike page content: this is chrome, and removing a menu link loses
     * nothing that cannot be added again.
     */
    public function down(): void
    {
        $this->change(function (array $globals) {
            if (isset($globals['nav']['links'])) {
                $globals['nav']['links'] = $this->without($globals['nav']['links']);
            }

            foreach ($globals['footer']['columns'] ?? [] as $i => $column) {
                if (isset($column['links'])) {
                    $globals['footer']['columns'][$i]['links'] = $this->without($column['links']);
                }
            }

            return $globals;
        });
    }

    private function change(callable $edit): void
    {
        $row = DB::table('settings')->where('key', 'globals')->first();

        if ($row === null) {
            return;
        }

        $globals = json_decode($row->value ?? '[]', true);

        if (! is_array($globals) || $globals === []) {
            return;
        }

        DB::table('settings')->where('key', 'globals')->update([
            'value' => json_encode($edit($globals)),
            'updated_at' => now(),
        ]);
    }

    private function holdsBlog(array $links): bool
    {
        foreach ($links as $link) {
            if (is_array($link) && ($link['href'] ?? null) === self::LINK['href']) {
                return true;
            }
        }

        return false;
    }

    private function without(array $links): array
    {
        return array_values(array_filter(
            $links,
            fn ($link) => ! is_array($link) || ($link['href'] ?? null) !== self::LINK['href'],
        ));
    }
};
