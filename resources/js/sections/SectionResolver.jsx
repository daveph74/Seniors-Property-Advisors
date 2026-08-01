import { Fragment } from 'react';
import { resolveSection } from './registry';

const BREAKPOINTS = ['desktop', 'tablet', 'mobile'];

export default function SectionResolver({ sections = [], actions = {}, library = {}, depth = 0 }) {
    return sections.map((section) => {
        const Section = resolveSection(section.type);

        if (!Section || section.active === false) return null;

        const nested = depth < 5 && Array.isArray(section.children)
            ? <SectionResolver sections={section.children} actions={actions} library={library} depth={depth + 1} />
            : null;

        const rendered = (
            <Section
                data={section.data || {}}
                anchor={section.anchor}
                actions={actions}
                library={library}
            >
                {nested}
            </Section>
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
