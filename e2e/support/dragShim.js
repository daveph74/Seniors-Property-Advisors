/**
 * Synthetic HTML5 drag, because the builder canvas uses the real one.
 *
 * Playwright drives pointer events, not the drag API, so `dragTo` cannot move a component from the
 * library onto the canvas. The saving grace is that the builder keeps its drag state in React refs
 * (`drag.current`, `dragType`) and uses `dataTransfer` only to set `effectAllowed` — so the events
 * have to arrive, but they do not have to carry anything.
 *
 * The canvas is an `about:blank` iframe that React portals into. It is same-origin, so the parent
 * document can reach `contentDocument` and dispatch into it; React's synthetic events pick the
 * events up from the portal container.
 *
 * This simulates the browser rather than being it. A passing drag test is therefore weaker evidence
 * than a passing click test, and it is the first thing to suspect if the drag code is rewritten.
 */
const DISPATCH = async ({ fromSelector, toSelector, inFrame, edge }) => {
    const doc = document;
    const frame = doc.querySelector('.cms-canvas-iframe');
    const canvas = frame && frame.contentDocument;

    const source = doc.querySelector(fromSelector);
    const target = (inFrame ? canvas : doc).querySelector(toSelector);
    const settle = () => new Promise((resolve) => setTimeout(resolve, 150));

    if (! source) return `no source for ${fromSelector}`;
    if (! target) return `no target for ${toSelector}`;

    const transfer = new DataTransfer();

    const fire = (node, type, point) => {
        const event = new DragEvent(type, {
            bubbles: true,
            cancelable: true,
            composed: true,
            dataTransfer: transfer,
            clientX: point ? point.x : 0,
            clientY: point ? point.y : 0,
        });

        node.dispatchEvent(event);
    };

    const box = target.getBoundingClientRect();
    /* Which half decides whether the block lands before or after the one dropped on. */
    const point = {
        x: box.left + box.width / 2,
        y: edge === 'before' ? box.top + 2 : box.bottom - 2,
    };

    fire(source, 'dragstart');
    await settle();
    fire(target, 'dragenter', point);
    fire(target, 'dragover', point);
    await settle();
    fire(target, 'drop', point);
    fire(source, 'dragend');

    return 'ok';
};

/* `sel @last > rest` — the last match of `sel`, then `rest` inside it. `:last-of-type` cannot say
   "the last row on the page": a drop zone or a chrome block follows the last block as a sibling. */
const resolveLast = (root, selector) => {
    const [head, rest] = selector.split(' @last');
    const all = root.querySelectorAll(head.trim());
    const base = all[all.length - 1];

    if (! base) return null;

    return rest && rest.trim() ? base.querySelector(`:scope ${rest.trim()}`) : base;
};

/**
 * Drag a component out of the library and drop it on something in the canvas.
 *
 * `toSelector` is resolved inside the canvas iframe. `.cms-nest-drop` is the empty zone a section
 * or column shows while it holds nothing — the only way to place a block inside another.
 */
export async function dragLibraryItem(page, label, toSelector, { edge = 'after' } = {}) {
    const result = await page.evaluate(DISPATCH, {
        fromSelector: `.cms-component-card[title="${label}"]`,
        toSelector,
        inFrame: true,
        edge,
    });

    if (result !== 'ok') throw new Error(`drag shim: ${result}`);
}

/**
 * Drag one block in the canvas onto another. Both selectors resolve inside the frame.
 *
 * The pause between `dragstart` and `dragover` is not padding: the builder's drop rules read the
 * dragged type from React state, which a real browser has long since rendered by the time its
 * stream of dragover events begins. Fired back to back, the first dragover still sees nothing being
 * dragged and refuses, and the drop lands nowhere.
 */
export async function dragBlock(page, fromSelector, toSelector, { edge = 'before' } = {}) {
    const result = await page.evaluate(async ({ fromSelector, toSelector, edge, resolveSource }) => {
        const canvas = document.querySelector('.cms-canvas-iframe').contentDocument;
        // eslint-disable-next-line no-new-func
        const resolve = new Function(`return (${resolveSource});`)();
        const pick = (sel) => (sel.includes(' @last') ? resolve(canvas, sel) : canvas.querySelector(sel));
        const source = pick(fromSelector);
        const target = pick(toSelector);

        if (! source) return `no source for ${fromSelector}`;
        if (! target) return `no target for ${toSelector}`;

        const transfer = new DataTransfer();
        const box = target.getBoundingClientRect();
        const point = { x: box.left + box.width / 2, y: edge === 'before' ? box.top + 2 : box.bottom - 2 };
        const fire = (node, type) => node.dispatchEvent(new DragEvent(type, {
            bubbles: true, cancelable: true, composed: true, dataTransfer: transfer, clientX: point.x, clientY: point.y,
        }));
        const settle = () => new Promise((resolve) => setTimeout(resolve, 150));

        fire(source, 'dragstart');
        await settle();
        fire(target, 'dragenter');
        fire(target, 'dragover');
        await settle();
        const marker = canvas.querySelectorAll('[class*="cms-drop-line"]').length;
        fire(target, 'drop');
        fire(source, 'dragend');

        return marker > 0 ? 'ok' : 'ok-without-marker';
    }, { fromSelector, toSelector, edge, resolveSource: resolveLast.toString() });

    if (! result.startsWith('ok')) throw new Error(`drag shim: ${result}`);

    return result === 'ok';
}

/** Drag a component onto the page root rather than into anything. */
export async function dragLibraryItemToPage(page, label) {
    const result = await page.evaluate(DISPATCH, {
        fromSelector: `.cms-component-card[title="${label}"]`,
        toSelector: '.cms-canvas-scroll, .cms-canvas',
        inFrame: false,
        edge: 'after',
    });

    if (result !== 'ok') throw new Error(`drag shim: ${result}`);
}
