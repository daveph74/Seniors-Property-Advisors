import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import reorderVisible from './reorderVisible';

const THRESHOLD = 5;
const EDGE_BAND = 80;

/**
 * Drag-to-reorder for a flat list, by pointer or by keyboard.
 *
 * Pointer events rather than the HTML5 drag API the page builder uses: dragstart never fires on
 * iPad, and this list has to be reorderable on a tablet. Nothing moves under the pointer while
 * dragging — a line marks where the row will land — which is the same language the builder speaks,
 * and it means the rectangles measured when the drag begins stay true for the whole gesture.
 *
 * `axis` is 'y' for a stacked list or 'grid' for a wrapping one, where the midpoint test runs
 * horizontally because cells flow left to right.
 */
export default function useSortableList({ items, axis = 'y', name = 'sortable', labelFor = () => '', onReorder }) {
    const instructionsId = `${name}-instructions`;
    const containerRef = useRef(null);
    const boxes = useRef([]);
    const pointer = useRef(null);
    const scrolling = useRef(null);
    const refocusId = useRef(null);

    const [activeId, setActiveId] = useState(null);
    const [dropAt, setDropAt] = useState(null);
    const [grabbed, setGrabbed] = useState(null);
    const [pending, setPending] = useState(null);
    const [liveMessage, setLiveMessage] = useState('');

    const signature = items.map((row) => row.id).join(',');

    /*
     * The row lands where it was dropped straight away and the save happens behind it. Waiting for
     * the round-trip before moving anything makes a drag feel like it did not take.
     *
     * `pending` only bridges the gap until the response lands — `settle` clears it, after which the
     * server's order is the one on screen, so a failed save corrects itself rather than leaving a
     * lie in front of the editor.
     */
    const order = useMemo(() => {
        if (grabbed) {
            const next = items.slice();
            const [moved] = next.splice(grabbed.from, 1);

            next.splice(grabbed.to, 0, moved);

            return next;
        }

        if (pending) {
            const known = new Map(items.map((row) => [String(row.id), row]));
            const moved = pending.map((id) => known.get(String(id))).filter(Boolean);
            const rest = items.filter((row) => ! pending.some((id) => String(id) === String(row.id)));

            if (moved.length > 0) return [...moved, ...rest];
        }

        return items;
    }, [items, grabbed, pending]);

    const at = useCallback((id) => order.findIndex((row) => String(row.id) === String(id)), [order]);
    const say = useCallback((text) => setLiveMessage(text), []);

    const place = useCallback((index) => `Position ${index + 1} of ${order.length}.`, [order.length]);

    const stopScrolling = () => {
        if (scrolling.current !== null) cancelAnimationFrame(scrolling.current);
        scrolling.current = null;
    };

    const finish = useCallback(() => {
        pointer.current = null;
        stopScrolling();
        setActiveId(null);
        setDropAt(null);
    }, []);

    /* Document coordinates, not viewport: auto-scrolling moves the page under the pointer and
       viewport-relative rectangles would quietly go stale mid-drag. */
    const measure = useCallback(() => {
        const nodes = containerRef.current?.querySelectorAll('[data-sort-id]') ?? [];

        boxes.current = [...nodes].map((node) => {
            const box = node.getBoundingClientRect();

            return {
                id: node.dataset.sortId,
                top: box.top + window.scrollY,
                bottom: box.bottom + window.scrollY,
                left: box.left + window.scrollX,
                right: box.right + window.scrollX,
            };
        });
    }, []);

    const hit = useCallback((x, y) => {
        const list = boxes.current;

        if (list.length === 0) return null;

        let index = list.findIndex((b) => (axis === 'grid'
            ? x >= b.left && x <= b.right && y >= b.top && y <= b.bottom
            : y >= b.top && y <= b.bottom));

        if (index < 0) {
            let best = Infinity;

            list.forEach((b, i) => {
                const dx = x - (b.left + b.right) / 2;
                const dy = y - (b.top + b.bottom) / 2;
                const distance = axis === 'grid' ? dx * dx + dy * dy : dy * dy;

                if (distance < best) {
                    best = distance;
                    index = i;
                }
            });
        }

        const box = list[index];
        const edge = axis === 'grid'
            ? (x < (box.left + box.right) / 2 ? 'before' : 'after')
            : (y < (box.top + box.bottom) / 2 ? 'before' : 'after');

        return { index, edge };
    }, [axis]);

    const track = useCallback((clientX, clientY) => {
        const next = hit(clientX + window.scrollX, clientY + window.scrollY);

        if (! next) return;

        setDropAt((prev) => (prev && prev.index === next.index && prev.edge === next.edge ? prev : next));
    }, [hit]);

    const autoScroll = useCallback(() => {
        const step = () => {
            const held = pointer.current;

            if (! held?.dragging) return;

            const top = held.clientY - EDGE_BAND;
            const bottom = held.clientY - (window.innerHeight - EDGE_BAND);
            const by = top < 0 ? Math.max(-18, top / 4) : (bottom > 0 ? Math.min(18, bottom / 4) : 0);

            if (by !== 0) {
                window.scrollBy(0, by);
                track(held.clientX, held.clientY);
            }

            scrolling.current = requestAnimationFrame(step);
        };

        stopScrolling();
        scrolling.current = requestAnimationFrame(step);
    }, [track]);

    const finalise = useCallback((ids, id, label, index) => {
        refocusId.current = id;
        setPending(ids);
        onReorder(ids);
        say(`Order updated. ${label} is now ${index + 1} of ${ids.length}.`);
    }, [onReorder, say]);

    /* Dropping reorders what is on screen, which during a pending save is `order` rather than the
       items prop — so a second drag before the first response lands still moves the row the editor
       is looking at. */
    const dropped = useCallback((from, to, id) => {
        const ids = reorderVisible(order, from, to);

        if (! ids) return;

        finalise(ids, id, labelFor(order[from]), to);
    }, [finalise, labelFor, order]);

    /* The keyboard has already previewed the outcome: `order` *is* the new order, so it is sent as
       it stands. Re-splicing it by the original indices would move a different row — the preview
       has already put the grabbed one where it belongs. */
    const dropGrabbed = useCallback((id) => {
        const index = order.findIndex((row) => String(row.id) === String(id));

        finalise(order.map((row) => row.id), id, labelFor(order[index]), index);
    }, [finalise, labelFor, order]);

    const handleProps = useCallback((id) => ({
        'data-sort-handle': String(id),
        'aria-label': `Reorder ${labelFor(order[at(id)])}, ${place(at(id)).toLowerCase()}`,
        'aria-pressed': grabbed?.id === id,
        'aria-describedby': instructionsId,
        title: 'Drag to reorder, or press Space then use the arrow keys',
        onPointerDown: (e) => {
            if (e.button !== 0 && e.pointerType === 'mouse') return;

            setGrabbed(null);
            e.currentTarget.setPointerCapture(e.pointerId);
            pointer.current = {
                id,
                startX: e.clientX,
                startY: e.clientY,
                clientX: e.clientX,
                clientY: e.clientY,
                dragging: false,
            };
        },
        onPointerMove: (e) => {
            const held = pointer.current;

            if (! held || held.id !== id) return;

            held.clientX = e.clientX;
            held.clientY = e.clientY;

            if (! held.dragging) {
                const far = Math.abs(e.clientX - held.startX) + Math.abs(e.clientY - held.startY);

                if (far < THRESHOLD) return;

                held.dragging = true;
                held.signature = signature;
                measure();
                setActiveId(id);
                autoScroll();
            }

            e.preventDefault();
            track(e.clientX, e.clientY);
        },
        onPointerUp: () => {
            const held = pointer.current;

            if (held?.dragging && dropAt) {
                const from = at(id);
                const insert = dropAt.edge === 'after' ? dropAt.index + 1 : dropAt.index;

                dropped(from, insert > from ? insert - 1 : insert, id);
            }

            finish();
        },
        onPointerCancel: finish,
        onKeyDown: (e) => {
            const index = at(id);
            const last = order.length - 1;

            if (e.key === ' ' || e.key === 'Enter') {
                e.preventDefault();

                if (grabbed?.id === id) {
                    if (grabbed.from !== grabbed.to) dropGrabbed(id);
                    setGrabbed(null);
                } else {
                    setGrabbed({ id, from: index, to: index });
                    say(`${labelFor(order[index])} grabbed. ${place(index)} Use the arrow keys to move it, Space to drop, Escape to cancel.`);
                }

                return;
            }

            if (! grabbed || grabbed.id !== id) return;

            if (e.key === 'Escape') {
                setGrabbed(null);
                say(`Move cancelled. ${labelFor(order[index])} is back at ${place(grabbed.from).toLowerCase()}`);

                return;
            }

            const to = {
                ArrowUp: index - 1,
                ArrowLeft: index - 1,
                ArrowDown: index + 1,
                ArrowRight: index + 1,
                Home: 0,
                End: last,
            }[e.key];

            if (to === undefined) return;

            e.preventDefault();

            const clamped = Math.max(0, Math.min(last, to));

            if (clamped === index) return;

            setGrabbed({ ...grabbed, to: clamped });
            say(place(clamped));
            containerRef.current
                ?.querySelector(`[data-sort-handle="${id}"]`)
                ?.scrollIntoView({ block: 'nearest' });
        },
        onBlur: () => setGrabbed((prev) => (prev?.id === id ? null : prev)),
    }), [at, autoScroll, dropAt, dropGrabbed, dropped, finish, grabbed, instructionsId, labelFor, measure, order, place, say, signature, track]);

    /* An in-flight response can repaint the list under a drag; the slot the pointer is over would
       then belong to a different row. Abort rather than drop into it. */
    useEffect(() => {
        if (pointer.current?.dragging && pointer.current.signature !== signature) finish();
    }, [finish, signature]);

    useEffect(() => {
        const id = refocusId.current;

        if (! id) return;

        refocusId.current = null;
        containerRef.current?.querySelector(`[data-sort-handle="${id}"]`)?.focus();
    }, [signature]);

    useEffect(() => {
        const abort = () => finish();

        window.addEventListener('resize', abort);

        return () => {
            window.removeEventListener('resize', abort);
            stopScrolling();
        };
    }, [finish]);

    return {
        order,
        activeId,
        liveMessage,
        settle: () => setPending(null),
        instructionsId,
        containerProps: { ref: containerRef },
        itemProps: (id) => ({ 'data-sort-id': String(id) }),
        handleProps,
        dropLineAt: (index) => (dropAt && dropAt.index === index ? dropAt.edge : null),
    };
}
