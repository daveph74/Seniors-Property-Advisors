<?php

namespace App\Cms;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;

/**
 * Paging for the admin lists, counted then sliced — the same shape `ContentLibrary::posts()` uses
 * for the public blog, which was the only paging in the application before this.
 *
 * The rows stay a flat array and the paging facts travel beside them. Handing the front end a
 * paginator object instead would rename every list prop to `.data` and buy nothing.
 */
class Listing
{
    /** @var array<int, int> what the size selector may ask for */
    public const SIZES = [25, 50, 100];

    public const DEFAULT_SIZE = 25;

    /**
     * An allowlist, never the number that arrived. `?per_page=1000000` is otherwise a way to ask
     * the server for the whole table, which is the thing paging exists to stop.
     */
    public static function perPage(Request $request): int
    {
        $asked = $request->integer('per_page');

        return in_array($asked, self::SIZES, true) ? $asked : self::DEFAULT_SIZE;
    }

    /**
     * @return array{rows: Collection, meta: array}
     */
    public static function slice(Builder $query, Request $request): array
    {
        $perPage = self::perPage($request);
        $total = (clone $query)->toBase()->getCountForPagination();
        $lastPage = max(1, (int) ceil($total / $perPage));

        /* Clamped rather than trusted: deleting the last row on the last page would otherwise leave
           somebody on an empty page with no way back except editing the address. */
        $page = min(max(1, $request->integer('page') ?: 1), $lastPage);

        $rows = $query->skip(($page - 1) * $perPage)->take($perPage)->get();

        return [
            'rows' => $rows,
            'meta' => [
                'page' => $page,
                'perPage' => $perPage,
                'total' => $total,
                'lastPage' => $lastPage,
                'from' => $total === 0 ? 0 : ($page - 1) * $perPage + 1,
                'to' => ($page - 1) * $perPage + $rows->count(),
                'sizes' => self::SIZES,
            ],
        ];
    }
}
