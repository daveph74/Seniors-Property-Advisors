import { useEffect, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import CanvasSkeleton from './CanvasSkeleton';

const STYLE_SELECTOR = 'link[rel="stylesheet"], style';
const STYLE_TIMEOUT = 10000;

function isOurs(link) {
    try {
        return new URL(link.href, window.location.href).origin === window.location.origin;
    } catch (e) {
        return false;
    }
}

const keyFor = (node) => (node.tagName === 'LINK' ? `link:${node.href}` : `style:${node.textContent}`);

function adoptStyles(target) {
    const wanted = new Map();

    document.head.querySelectorAll(STYLE_SELECTOR).forEach((node) => wanted.set(keyFor(node), node));

    const present = new Map();

    target.querySelectorAll('[data-canvas-style]').forEach((copy) => {
        const key = copy.getAttribute('data-canvas-key');

        if (key === 'reset') return;

        if (wanted.has(key)) {
            present.set(key, copy);
        } else {
            copy.remove();
        }
    });

    const links = [];

    wanted.forEach((node, key) => {
        const copy = present.get(key) || node.cloneNode(true);

        if (! present.has(key)) {
            copy.setAttribute('data-canvas-style', '');
            copy.setAttribute('data-canvas-key', key);
            target.appendChild(copy);
        }

        if (copy.tagName === 'LINK') links.push(copy);
    });

    return links;
}

function whenStyled(allLinks) {
    const links = allLinks.filter(isOurs);

    const settled = links.map((link) => (
        link.sheet
            ? Promise.resolve()
            : new Promise((resolve) => {
                link.addEventListener('load', resolve, { once: true });
                link.addEventListener('error', resolve, { once: true });
            })
    ));

    return Promise.race([
        Promise.all(settled),
        new Promise((resolve) => setTimeout(resolve, STYLE_TIMEOUT)),
    ]);
}

export default function CanvasFrame({ width, scale = 1, onHeight, onReady, children }) {
    const frame = useRef(null);
    const [body, setBody] = useState(null);
    const [ready, setReady] = useState(false);
    const [height, setHeight] = useState(600);

    useEffect(() => {
        const iframe = frame.current;

        if (! iframe) return;

        let recovering = false;
        let cancelled = false;

        const stayPut = (event) => {
            if (event.target.closest?.('a[href]')) event.preventDefault();
        };

        const blockSubmit = (event) => event.preventDefault();

        const attach = () => {
            const doc = iframe.contentDocument;

            if (! doc) return;

            if (iframe.contentWindow?.location.href !== 'about:blank') {
                if (recovering) return;

                recovering = true;
                iframe.contentWindow.location.replace('about:blank');

                return;
            }

            recovering = false;

            doc.documentElement.lang = 'en';
            doc.documentElement.classList.add('cms-portal');

            const base = doc.createElement('base');

            base.href = '/';
            doc.head.appendChild(base);

            const links = adoptStyles(doc.head);

            const reset = doc.createElement('style');

            reset.setAttribute('data-canvas-style', '');
            reset.setAttribute('data-canvas-key', 'reset');
            reset.textContent = 'html,body{margin:0;padding:0;background:#fff;scrollbar-width:none;'
                + '-ms-overflow-style:none;}'
                + 'html::-webkit-scrollbar,body::-webkit-scrollbar{width:0;height:0;display:none;}';
            doc.head.appendChild(reset);

            doc.addEventListener('click', stayPut, true);
            doc.addEventListener('auxclick', stayPut, true);
            doc.addEventListener('submit', blockSubmit, true);

            whenStyled(links).then(() => {
                if (cancelled) return;

                setBody(doc.body);
                setReady(true);
                if (onReady) onReady(true);
            });
        };

        attach();
        iframe.addEventListener('load', attach);

        const watchParent = new MutationObserver(() => {
            const doc = iframe.contentDocument;

            if (doc) adoptStyles(doc.head);
        });

        watchParent.observe(document.head, { childList: true });

        return () => {
            cancelled = true;
            iframe.removeEventListener('load', attach);
            watchParent.disconnect();
        };
    }, []);

    useEffect(() => {
        if (! body) return;

        const root = body.ownerDocument.documentElement;

        const measure = () => {
            const next = Math.max(
                root.scrollHeight,
                body.scrollHeight,
                Math.ceil(body.getBoundingClientRect().height),
            );

            if (next > 0) {
                setHeight(next);
                if (onHeight) onHeight(next);
            }
        };

        measure();

        const watch = new ResizeObserver(measure);

        watch.observe(root);
        watch.observe(body);
        Array.from(body.children).forEach((child) => watch.observe(child));

        const watchTree = new MutationObserver(measure);

        watchTree.observe(body, { childList: true, subtree: true });

        return () => { watch.disconnect(); watchTree.disconnect(); };
    }, [body, width, onHeight]);

    return (
        <>
            <iframe
                ref={frame}
                title="Page canvas"
                className={`cms-canvas-iframe ${ready ? 'cms-anim-fade' : 'cms-canvas-iframe--waiting'}`}
                style={{
                    width,
                    height,
                    transform: scale < 1 ? `scale(${scale})` : undefined,
                    transformOrigin: 'top left',
                }}
            >
                {body ? createPortal(children, body) : null}
            </iframe>

            {ready ? null : (
                <div
                    className="cms-skeleton-holder"
                    style={{ width: width * scale, height: 620 * scale }}
                >
                    <div style={{ transform: scale < 1 ? `scale(${scale})` : undefined, transformOrigin: 'top left' }}>
                        <CanvasSkeleton width={width} />
                    </div>
                </div>
            )}
        </>
    );
}
