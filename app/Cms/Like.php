<?php

namespace App\Cms;

use Illuminate\Database\Eloquent\Builder;

/**
 * Substring matching across a few columns, with the one trap it carries stated once.
 *
 * `%` and `_` are wildcards to LIKE, so a term containing either has to be escaped or a search for
 * "50%" quietly matches every row. The `ESCAPE` clause is not optional either: SQLite has no default
 * escape character, so escaping without declaring one leaves the wildcards live and passes the
 * escape character itself through as a literal to match on.
 *
 * None of this can use an index — `like '%term%'` is a scan on every engine, and the long text
 * columns are the expensive part. That is fine at this size and simpler than a full-text index, but
 * it is a scan, not a lookup.
 */
class Like
{
    private const ESCAPE = '!';

    public static function escape(string $term): string
    {
        return str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $term);
    }

    /**
     * Adds `(col like ? or col like ? ...)` to the query. Column names are the caller's own
     * constants, never user input.
     *
     * @param  array<int, string>  $columns
     */
    public static function any(Builder $query, string $term, array $columns): Builder
    {
        $pattern = '%'.self::escape($term).'%';

        return $query->where(function (Builder $inner) use ($pattern, $columns) {
            foreach ($columns as $column) {
                $inner->orWhereRaw("{$column} like ? escape '".self::ESCAPE."'", [$pattern]);
            }
        });
    }
}
