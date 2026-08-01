/**
 * The ids to send when someone nudges a row up or down on a screen that can be searched or filtered.
 *
 * The arrows used to reorder the *visible* list, which wrote positions 1..n for those rows alone and
 * silently collided with the positions of every row the filter had hidden. So the move is made
 * inside the full list, swapping with the nearest neighbour that is actually visible — the visible
 * order changes the way the arrow promises, and nothing hidden moves.
 *
 * Returns null when there is nowhere to go, so the caller can do nothing.
 */
export default function reorderWithinAll(all, shown, index, delta) {
    const item = shown[index];
    const neighbour = shown[index + delta];

    if (! item || ! neighbour) return null;

    const ids = all.map((row) => row.id);
    const from = ids.indexOf(item.id);
    const to = ids.indexOf(neighbour.id);

    if (from < 0 || to < 0) return null;

    [ids[from], ids[to]] = [ids[to], ids[from]];

    return ids;
}
