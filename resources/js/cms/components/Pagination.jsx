/**
 * Sits under a list, never inside one — `.cms-faq-list` and the media grid both clip their overflow
 * to keep their rounded corners, so a bar rendered within either would be cut off.
 *
 * Renders nothing at all while everything fits on the smallest page. A pager offering one page, and
 * a size selector for a list shorter than the smallest size, are two controls that cannot do
 * anything.
 */
function pageNumbers(page, lastPage) {
    if (lastPage <= 7) return Array.from({ length: lastPage }, (_, i) => i + 1);

    const around = [page - 1, page, page + 1].filter((n) => n > 1 && n < lastPage);
    const withEnds = [1, ...around, lastPage];

    /* A gap marker wherever a number was skipped, so 1 … 6 7 8 … 40 reads as the jump it is. */
    return withEnds.flatMap((n, i) => (i > 0 && n - withEnds[i - 1] > 1 ? ['gap', n] : [n]));
}

export default function Pagination({ meta, onPage, onPerPage, noun = 'items' }) {
    if (! meta) return null;

    const { page, perPage, total, lastPage, from, to, sizes = [] } = meta;

    if (total <= (sizes[0] ?? 0)) return null;

    return (
        <div className="cms-pager">
            <span className="cms-pager__count">
                Showing {from}–{to} of {total} {noun}
            </span>

            <label className="cms-pager__size">
                <span className="cms-sr-only">How many to show at a time</span>
                <select
                    className="cms-select cms-select--sm"
                    value={perPage}
                    onChange={(e) => onPerPage(Number(e.target.value))}
                >
                    {sizes.map((size) => <option key={size} value={size}>{size} per page</option>)}
                </select>
            </label>

            {lastPage > 1 ? (
                <div className="cms-pager__pages">
                    <button
                        type="button"
                        className="cms-btn cms-btn--sm"
                        disabled={page <= 1}
                        onClick={() => onPage(page - 1)}
                    >
                        Previous
                    </button>

                    {pageNumbers(page, lastPage).map((n, i) => (n === 'gap' ? (
                        <span key={`gap-${i}`} className="cms-pager__gap">…</span>
                    ) : (
                        <button
                            type="button"
                            key={n}
                            className={`cms-pager__page${n === page ? ' cms-pager__page--current' : ''}`}
                            aria-current={n === page ? 'page' : undefined}
                            onClick={() => onPage(n)}
                        >
                            {n}
                        </button>
                    )))}

                    <button
                        type="button"
                        className="cms-btn cms-btn--sm"
                        disabled={page >= lastPage}
                        onClick={() => onPage(page + 1)}
                    >
                        Next
                    </button>
                </div>
            ) : null}
        </div>
    );
}
