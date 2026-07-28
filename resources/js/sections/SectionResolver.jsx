import { resolveSection } from './registry';

export default function SectionResolver({ sections = [], actions = {}, depth = 0 }) {
    return sections.map((section) => {
        const Section = resolveSection(section.type);

        if (!Section || section.active === false) return null;

        const nested = depth < 5 && Array.isArray(section.children)
            ? <SectionResolver sections={section.children} actions={actions} depth={depth + 1} />
            : null;

        return (
            <Section
                key={section.id}
                data={section.data || {}}
                anchor={section.anchor}
                actions={actions}
            >
                {nested}
            </Section>
        );
    });
}
