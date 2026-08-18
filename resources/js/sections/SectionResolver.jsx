import { Fragment } from 'react';
import { resolveSection } from './registry';
import { HeadingLevel, ownerOfTheH1 } from './headingLevel';

const BREAKPOINTS = ['desktop', 'tablet', 'mobile'];

export default function SectionResolver({ sections = [], actions = {}, library = {}, site = {}, depth = 0, h1Owner = null }) {
    /* Decided once, at the outermost run, then carried down — the winner can be a block inside a
       section container, which is the only heading a page assembled from blocks has. */
    const topHeading = depth === 0 ? ownerOfTheH1(sections) : h1Owner;

    return sections.map((section) => {
        const Section = resolveSection(section.type);

        if (!Section || section.active === false) return null;

        const nested = depth < 5 && Array.isArray(section.children)
            ? <SectionResolver sections={section.children} actions={actions} library={library} site={site} depth={depth + 1} h1Owner={topHeading} />
            : null;

        const rendered = (
            <HeadingLevel.Provider value={section.id === topHeading ? 1 : 2}>
                <Section
                    data={section.data || {}}
                    anchor={section.anchor}
                    actions={actions}
                    library={library}
                    site={site}
                >
                    {nested}
                </Section>
            </HeadingLevel.Provider>
        );

        const hidden = section.data?.hidden || {};
        const hideOn = BREAKPOINTS.filter((bp) => hidden[bp]);

        if (hideOn.length === 0) {
            return <Fragment key={section.id}>{rendered}</Fragment>;
        }

        return (
            <div key={section.id} className={`u-hide-wrap ${hideOn.map((bp) => `u-hide-${bp}`).join(' ')}`}>
                {rendered}
            </div>
        );
    });
}
