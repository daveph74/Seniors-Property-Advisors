import { Fragment } from 'react';
import { resolveSection } from './registry';
import { HeadingLevel, ownerOfTheH1 } from './headingLevel';
import Backdrop from './Backdrop';
import CustomStyled from './customCss';
import Reveal from './Reveal';

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

        const hidden = section.data?.hidden || {};
        const hideOn = BREAKPOINTS.filter((bp) => hidden[bp]);
        const hideClasses = hideOn.map((bp) => `u-hide-${bp}`).join(' ');
        const selfHiding = section.type === 'row' || section.type === 'column';

        const rendered = (
            <HeadingLevel.Provider value={section.id === topHeading ? 1 : 2}>
                <Reveal type={section.type} data={section.data || {}}>
                <CustomStyled id={section.id} type={section.type} data={section.data || {}}>
                    <Backdrop type={section.type} data={section.data || {}}>
                        <Section
                            data={section.data || {}}
                            anchor={section.anchor}
                            blockId={section.id}
                            hideClasses={selfHiding ? hideClasses : ''}
                            childBlocks={Array.isArray(section.children) ? section.children.filter((c) => c.active !== false) : []}
                            actions={actions}
                            library={library}
                            site={site}
                        >
                            {nested}
                        </Section>
                    </Backdrop>
                </CustomStyled>
                </Reveal>
            </HeadingLevel.Provider>
        );

        if (hideOn.length === 0 || selfHiding) {
            return <Fragment key={section.id}>{rendered}</Fragment>;
        }

        return (
            <div key={section.id} className={`u-hide-wrap ${hideClasses}`}>
                {rendered}
            </div>
        );
    });
}
