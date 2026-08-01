import { useCallback, useEffect, useMemo, useRef, useState } from 'react';

const THRESHOLD = 5;
const EDGE_BAND = 80;
const INSTRUCTIONS_ID = 'sortable-instructions';

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
export default function useSortableList({ items, axis = 'y', labelFor = () => '', onReorder }) {
    const containerRef = useRef(null);
    const boxes = useRef([]);
    const pointer = useRef(null);
    const scrolling = useRef(null);
    const refocusId = useRef(null);

    const [activeId, setActiveId] = useState(null);
    const [dropAt, setDropAt] = useState(null);
    const [grabbed, setGrabbed] = useState(null);
    const [liveMessage, setLiveMessage] = useState('');

    const signature = items.map((row) => row.id).join(',');

    const order = useMemo(() => {
        if (! grabbed) return items;

        const next = items.slice();
        const [moved] = next.splice(grabbed.from, 1);

        next.splice(grabbed.to, 0, moved);

        return next;
    }, [items, grabbed]);

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

    const commit = useCallback((from, to, id) => {
        if (from === to) return;

        refocusId.current = id;
        onReorder(from, to);
        say(`Order updated. ${labelFor(order[from])} is now ${to + 1} of ${order.length}.`);
    }, [labelFor, onReorder, order, say]);

    const handleProps = useCallback((id) => ({
        'data-sort-handle': String(id),
        'aria-label': `Reorder ${labelFor(order[at(id)])}, ${place(at(id)).toLowerCase()}`,
        'aria-pressed': grabbed?.id === id,
        'aria-describedby': INSTRUCTIONS_ID,
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

                commit(from, insert > from ? insert - 1 : insert, id);
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
                    commit(grabbed.from, grabbed.to, id);
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
    }), [at, autoScroll, commit, dropAt, finish, grabbed, labelFor, measure, order, place, say, signature, track]);

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
        instructionsId: INSTRUCTIONS_ID,
        containerProps: { ref: containerRef },
        itemProps: (id) => ({ 'data-sort-id': String(id) }),
        handleProps,
        dropLineAt: (index) => (dropAt && dropAt.index === index ? dropAt.edge : null),
    };
}
