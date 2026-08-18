import { Link, router, usePage } from '@inertiajs/react';
import { isInternal } from './navHref';

/**
 * One link component for the public site, so no one has to remember which of Inertia's Link and
 * a plain anchor a given href needs.
 *
 * Internal paths go through Inertia; tel:, mailto: and anything off-origin stay with the
 * browser. Cross-page anchors — /#family from an article — are the awkward case: Inertia does
 * not restore fragment scroll, so they used to fall back to a full page load. They are handled
 * here instead, which is the whole point of the anchors being in a menu that appears on pages
 * that do not contain them.
 */
function split(href) {
    const at = String(href || '').indexOf('#');

    return at === -1
        ? { path: String(href || ''), hash: '' }
        : { path: String(href).slice(0, at), hash: String(href).slice(at + 1) };
}

/** How long to keep correcting the landing while the page is still settling. */
const SETTLE_FOR = 1200;

/**
 * Keeps re-landing until the target stops moving, rather than scrolling once and hoping.
 *
 * A section's position keeps changing while the images above it resolve, so a single scroll —
 * or a scroll plus one fixed delay — lands short by an amount that grows the further down the
 * page the target is. Measured on this site: #compare was 17px out and #family 126px out with a
 * 120ms second pass. This corrects on every frame until the offset is stable, and gives up
 * immediately if the reader starts scrolling themselves, so it can never fight them.
 *
 * Corrections are instant rather than smooth, and say so: animating them queued a stack of
 * scrolls that rode straight over a reader who had started scrolling themselves.
 * `scroll-margin-top` supplies the header offset.
 */
function glideTo(hash) {
    const el = document.getElementById(hash);

    if (! el) return;

    /*
     * Within a page there is nothing to correct — the layout settled long ago — so this is a
     * single scroll, and the only one on the site that glides. Asked for here rather than in CSS,
     * because a stylesheet rule would also animate the scrolls Inertia makes when it changes page.
     * Someone who has asked their system for less motion gets the jump instead.
     */
    const still = window.matchMedia?.('(prefers-reduced-motion: reduce)').matches;

    el.scrollIntoView({ behavior: still ? 'instant' : 'smooth' });
}

function jumpTo(hash) {
    const started = performance.now();
    let lastTop = null;
    let stop = false;

    const release = () => { stop = true; };
    const events = ['wheel', 'touchstart', 'keydown'];

    events.forEach((e) => window.addEventListener(e, release, { passive: true, once: true }));

    const settle = () => {
        const el = document.getElementById(hash);

        if (stop || ! el) {
            events.forEach((e) => window.removeEventListener(e, release));

            return;
        }

        const top = Math.round(el.getBoundingClientRect().top + window.scrollY);

        if (top !== lastTop) {
            lastTop = top;
            el.scrollIntoView({ behavior: 'instant' });
        }

        if (performance.now() - started < SETTLE_FOR) {
            requestAnimationFrame(settle);

            return;
        }

        events.forEach((e) => window.removeEventListener(e, release));
    };

    requestAnimationFrame(settle);
}

/**
 * `preserveScroll` and `only` are handled rather than spread. Inertia's Link understands both, but
 * two of the branches below render a plain anchor, and an unknown attribute on one of those
 * reaches the DOM. Each branch is given them in the form it takes.
 *
 * `only` names the props the server should send back. A link that changes one part of a page —
 * the article list under a category chip — has no reason to be sent the whole page again.
 */
export default function SiteLink({ href, children, onClick, preserveScroll = false, only, ...rest }) {
    const here = usePage().url.split('?')[0].split('#')[0] || '/';
    const { path, hash } = split(href);

    if (hash && isInternal(path || '/')) {
        const target = path || '/';

        return (
            <a
                href={href}
                onClick={(e) => {
                    if (e.defaultPrevented || e.metaKey || e.ctrlKey || e.shiftKey || e.button !== 0) return;

                    e.preventDefault();
                    onClick?.(e);

                    if (here === target) {
                        window.history.replaceState(null, '', href);
                        glideTo(hash);

                        return;
                    }

                    router.visit(href, {
                        preserveScroll,
                        ...(only ? { only } : {}),
                        onSuccess: () => jumpTo(hash),
                    });
                }}
                {...rest}
            >
                {children}
            </a>
        );
    }

    if (! isInternal(href)) {
        return <a href={href} onClick={onClick} {...rest}>{children}</a>;
    }

    return (
        <Link
            href={href}
            onClick={onClick}
            preserveScroll={preserveScroll}
            {...(only ? { only, preserveState: true } : {})}
            {...rest}
        >
            {children}
        </Link>
    );
}
