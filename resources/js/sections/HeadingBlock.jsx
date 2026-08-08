import { spacingClasses } from './spacing';
import { useHeadingLevel } from './headingLevel';

const LEVELS = { h2: 'h2', h3: 'h3', h4: 'h4' };
const ALIGN = { left: '', center: 'block-heading--center', right: 'block-heading--right' };

export default function HeadingBlock({ data, anchor }) {
    /* The resolver nominates one heading on the page to be its h1. On a page written out of blocks
       — a policy, a set of terms — this is the only thing that can be it, so the nomination wins
       over the level chosen in the builder. Everywhere else the editor's choice stands. */
    const leadsPage = useHeadingLevel() === 1;

    if (!data.heading) return null;

    const Tag = leadsPage ? 'h1' : (LEVELS[data.level] || 'h2');

    return (
        <Tag id={anchor} className={`block-heading ${ALIGN[data.align] || ''} ${spacingClasses(data)}`.replace(/ +/g, ' ').trim()}>
            {data.heading}
            {data.headingEm ? <> <em>{data.headingEm}</em></> : null}
        </Tag>
    );
}
