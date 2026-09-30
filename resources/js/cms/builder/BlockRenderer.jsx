import { resolveSection } from '../../sections/registry';
import { HeadingLevel } from '../../sections/headingLevel';
import Backdrop from '../../sections/Backdrop';

export default function BlockRenderer({ block, library = {}, headingLevel = 2, children }) {
    const Section = resolveSection(block.type);

    if (!Section) return null;

    return (
        <HeadingLevel.Provider value={headingLevel}>
            <Backdrop type={block.type} data={block.data || {}}>
                <Section data={block.data || {}} anchor={block.anchor} childBlocks={Array.isArray(block.children) ? block.children : []} actions={{}} library={library} editing>
                    {children}
                </Section>
            </Backdrop>
        </HeadingLevel.Provider>
    );
}
