import { useEffect, useState } from 'react';

/**
 * Which of the home page's sections the reader is looking at, so the menu underline follows
 * them instead of sitting on whichever item the content marked as active and never moving.
 *
 * The rule is positional and deliberately simple: a section becomes current when its top
 * passes under the sticky header, and stays current until the next one does. Judging it by
 * how much of each section overlaps a band instead gets it wrong whenever a tall section
 * straddles that band — scrolling to the last section would still report the previous one.
 */
export default function useSectionInView(hrefs, enabled = true) {
    const [current, setCurrent] = useState(null);
    const keys = hrefs.join(',');

    useEffect(() => {
        if (! enabled || typeof window === 'undefined') {
            setCurrent(null);

            return undefined;
        }

        const ids = keys.split(',').filter(Boolean).map((href) => href.slice(1));

        if (ids.length === 0) return undefined;

        let frame = null;

        const measure = () => {
            frame = null;

            /*
             * Measured against each section's own scroll-margin-top — the offset the browser
             * uses when parking it under the sticky header. Anything else drifts: the header is
             * 85px and the margin 96px, so a threshold based on the header alone left a
             * scrolled-to section 3px short of counting, and the underline lagged one behind.
             */
            const tops = ids
                .map((id) => document.getElementById(id))
                .filter(Boolean)
                .map((el) => ({
                    id: el.id,
                    top: el.getBoundingClientRect().top,
                    line: (parseFloat(getComputedStyle(el).scrollMarginTop) || 0) + 4,
                }));

            if (tops.length === 0) return;

            const passed = tops.filter((s) => s.top <= s.line);

            /*
             * Above the first section the reader is in the hero, which no menu item names.
             * Marking the first one keeps the bar from looking broken on arrival, which is
             * how an empty state reads.
             */
            setCurrent(`#${(passed.length > 0 ? passed[passed.length - 1] : tops[0]).id}`);
        };

        const onScroll = () => {
            if (frame === null) frame = window.requestAnimationFrame(measure);
        };

        measure();
        window.addEventListener('scroll', onScroll, { passive: true });
        window.addEventListener('resize', onScroll);

        return () => {
            if (frame !== null) window.cancelAnimationFrame(frame);

            window.removeEventListener('scroll', onScroll);
            window.removeEventListener('resize', onScroll);
        };
    }, [keys, enabled]);

    return current;
}
