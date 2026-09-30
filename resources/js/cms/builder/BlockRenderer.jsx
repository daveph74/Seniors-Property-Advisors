import { resolveSection } from '../../sections/registry';
import { HeadingLevel } from '../../sections/headingLevel';
import Backdrop from '../../sections/Backdrop';
import CustomStyled from '../../sections/customCss';
import Reveal from '../../sections/Reveal';

export default function BlockRenderer({ block, library = {}, headingLevel = 2, children }) {
    const Section = resolveSection(block.type);

    if (!Section) return null;

    return (
        <HeadingLevel.Provider value={headingLevel}>
            <Reveal type={block.type} data={block.data || {}} editing>
                <CustomStyled id={block.id} type={block.type} data={block.data || {}}>
                    <Backdrop type={block.type} data={block.data || {}}>
                        <Section data={block.data || {}} anchor={block.anchor} blockId={block.id} childBlocks={Array.isArray(block.children) ? block.children : []} actions={{}} library={library} editing>
                            {children}
                        </Section>
                    </Backdrop>
                </CustomStyled>
            </Reveal>
        </HeadingLevel.Provider>
    );
}
