import { resolveSection } from '../../sections/registry';

export default function BlockRenderer({ block, children }) {
    const Section = resolveSection(block.type);

    if (!Section) return null;

    return (
        <Section data={block.data || {}} anchor={block.anchor} actions={{}}>
            {children}
        </Section>
    );
}
