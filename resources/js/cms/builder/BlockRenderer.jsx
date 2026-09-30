import { resolveSection } from '../../sections/registry';
import { HeadingLevel } from '../../sections/headingLevel';

export default function BlockRenderer({ block, library = {}, headingLevel = 2, children }) {
    const Section = resolveSection(block.type);

    if (!Section) return null;

    return (
        <HeadingLevel.Provider value={headingLevel}>
            <Section data={block.data || {}} anchor={block.anchor} childBlocks={Array.isArray(block.children) ? block.children : []} actions={{}} library={library} editing>
                {children}
            </Section>
        </HeadingLevel.Provider>
    );
}
