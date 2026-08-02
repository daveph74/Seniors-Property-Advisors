import { createContext, useContext } from 'react';

/**
 * Which level a section's own heading should render at.
 *
 * A page needs exactly one h1, and it should be the first thing the page says rather than something
 * hidden for screen readers alone. The hero already renders one; a page built without a hero — the
 * FAQ page, the blog listing — had no h1 at all, and its first real heading sat at h2 under nothing.
 *
 * The resolver decides, before anything renders, which section owns it. Doing it by "whichever
 * heading renders first" would depend on render order, and React is free to run a component twice.
 */
export const HeadingLevel = createContext(2);

export const useHeadingLevel = () => useContext(HeadingLevel);

/** The id of the section that should carry the h1, or null when a hero already does. */
export function ownerOfTheH1(sections = []) {
    const visible = sections.filter((s) => s && s.active !== false);

    if (visible.some((s) => s.type === 'hero')) {
        return null;
    }

    return visible.find((s) => (s.data?.heading || s.data?.headingEm))?.id ?? null;
}
