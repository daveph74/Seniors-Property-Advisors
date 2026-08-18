<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const LINK = ['href' => '/faqs', 'label' => 'FAQs'];

    /**
     * The FAQ page had no way in: the footer carried an "FAQs" label pointing at "#", and the
     * Resources menu held only Blog. Site chrome lives in the `globals` setting and the
     * navigation module is still a prototype, so a migration remains the only way to change a
     * live menu — the same reasoning as the blog link.
     *
     * FAQs goes above Blog in the menu. Someone opening Resources with a question in mind wants
     * an answer, not an article, and the answers are the shorter read.
     *
     * The footer link already exists, so this repoints it rather than adding a second one, and
     * leaves it alone if it has been given a real address by hand.
     */
    public function up(): void
    {
        $this->change(function (array $globals) {
            foreach ($globals['nav']['links'] ?? [] as $i => $link) {
                if (($link['label'] ?? '') !== 'Resources' || $this->holds($link['children'] ?? [])) {
                    continue;
                }

                $globals['nav']['links'][$i]['children'] = [
                    self::LINK,
                    ...($link['children'] ?? []),
                ];
            }

            foreach ($globals['footer']['columns'] ?? [] as $c => $column) {
                foreach ($column['links'] ?? [] as $i => $link) {
                    if (($link['label'] ?? '') !== self::LINK['label'] || ($link['href'] ?? '') !== '#') {
                        continue;
                    }

                    $globals['footer']['columns'][$c]['links'][$i]['href'] = self::LINK['href'];
                }
            }

            return $globals;
        });
    }

    /**
     * Chrome, so reversing loses nothing that cannot be added again. The footer link goes back to
     * a placeholder rather than disappearing, which is how it arrived.
     */
    public function down(): void
    {
        $this->change(function (array $globals) {
            foreach ($globals['nav']['links'] ?? [] as $i => $link) {
                if (isset($link['children'])) {
                    $globals['nav']['links'][$i]['children'] = array_values(array_filter(
                        $link['children'],
                        fn ($child) => ! is_array($child) || ($child['href'] ?? null) !== self::LINK['href'],
                    ));
                }
            }

            foreach ($globals['footer']['columns'] ?? [] as $c => $column) {
                foreach ($column['links'] ?? [] as $i => $link) {
                    if (($link['href'] ?? '') === self::LINK['href']) {
                        $globals['footer']['columns'][$c]['links'][$i]['href'] = '#';
                    }
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

    private function holds(array $links): bool
    {
        foreach ($links as $link) {
            if (is_array($link) && ($link['href'] ?? null) === self::LINK['href']) {
                return true;
            }
        }

        return false;
    }
};
