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
const DISPATCH = ({ fromSelector, toSelector, inFrame, edge }) => {
    const doc = document;
    const frame = doc.querySelector('.cms-canvas-iframe');
    const canvas = frame && frame.contentDocument;

    const source = doc.querySelector(fromSelector);
    const target = (inFrame ? canvas : doc).querySelector(toSelector);

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
    fire(target, 'dragenter', point);
    fire(target, 'dragover', point);
    fire(target, 'drop', point);
    fire(source, 'dragend');

    return 'ok';
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
