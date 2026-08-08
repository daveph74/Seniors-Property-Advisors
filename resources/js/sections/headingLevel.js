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

    /* Every hero variant renders its own h1, so none of them may nominate a second one. */
    if (visible.some((s) => s.type === 'hero' || s.type === 'hero-full')) {
        return null;
    }

    /*
     * The first heading the page says, in reading order — each section, then inside it, then on to
     * the next. A page assembled from the block system has nothing to nominate at the top level:
     * the container carries the width and the background, and the words are the blocks within it.
     *
     * Depth-first rather than "top level, and only failing that, inside": a page whose words live
     * in a container and which closes with a call to action would otherwise skip past the
     * container to the first heading it found alongside it, and open on "Ready to find the right
     * agent?" instead of its own title.
     */
    for (const section of visible) {
        if (section.data?.heading || section.data?.headingEm) {
            return section.id;
        }

        const nested = ownerOfTheH1(section.children ?? []);

        if (nested) {
            return nested;
        }
    }

    return null;
}
