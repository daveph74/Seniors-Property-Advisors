/**
 * The ids to post when someone moves a row from one position to another.
 *
 * Only the visible rows are sent, which is safe because the reorder endpoints redistribute the
 * sort_order slots those rows already occupy — a row hidden by a search or a filter keeps its
 * position, and a drag can only ever land between two rows the user can actually see.
 *
 * Returns null when nothing would change.
 */
export default function reorderVisible(shown, from, to) {
    if (from === to || from < 0 || to < 0 || from >= shown.length || to >= shown.length) {
        return null;
    }

    const ids = shown.map((row) => row.id);
    const [moved] = ids.splice(from, 1);

    ids.splice(to, 0, moved);

    return ids;
}
