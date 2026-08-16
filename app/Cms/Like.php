<?php

namespace App\Cms;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;

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
        return $query->where(function (Builder $inner) use ($term, $columns) {
            foreach ($columns as $column) {
                self::orContains($inner->getQuery(), $column, $term);
            }
        });
    }

    /**
     * One column contains this text. Takes either builder, because the closures the media usage scan
     * hands around are given an Eloquent builder while its own inner queries are not.
     */
    public static function contains(QueryBuilder|Builder $query, string $column, string $needle): void
    {
        $query->whereRaw(self::fragment($query, $column), ['%'.self::escape($needle).'%']);
    }

    public static function orContains(QueryBuilder|Builder $query, string $column, string $needle): void
    {
        $query->orWhereRaw(self::fragment($query, $column), ['%'.self::escape($needle).'%']);
    }

    /**
     * The comparison itself, and the two things about it that are not portable.
     *
     * The column is quoted by the connection's own grammar rather than pasted in: the media table
     * has a column called `key`, a reserved word everywhere except SQLite, so unquoted it answered
     * every search with a syntax error the moment this met MySQL.
     *
     * And the `ESCAPE` clause is doing a second job besides the wildcards. MySQL treats a backslash
     * as an escape character inside a LIKE pattern and SQLite does not, so a search for the
     * JSON-escaped `\/media\/…` — which is how a section tree stores an image address — matched on
     * one engine and quietly matched nothing on the other. Naming an escape character makes the
     * backslash a literal on both.
     */
    private static function fragment(QueryBuilder|Builder $query, string $column): string
    {
        $grammar = ($query instanceof Builder ? $query->getQuery() : $query)->getGrammar();

        return $grammar->wrap($column)." like ? escape '".self::ESCAPE."'";
    }
}
