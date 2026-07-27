import { resolveSection } from './registry';

export default function SectionResolver({ sections = [], actions = {} }) {
    return sections.map((section) => {
        const Section = resolveSection(section.type);

        if (!Section || section.active === false) return null;

        return (
            <Section
                key={section.id}
                data={section.data || {}}
                anchor={section.anchor}
                actions={actions}
            />
        );
    });
}
