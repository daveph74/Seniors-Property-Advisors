import { spacingClasses } from './spacing';

const LEVELS = { h2: 'h2', h3: 'h3', h4: 'h4' };
const ALIGN = { left: '', center: 'block-heading--center', right: 'block-heading--right' };

export default function HeadingBlock({ data, anchor }) {
    if (!data.heading) return null;

    const Tag = LEVELS[data.level] || 'h2';

    return (
        <Tag id={anchor} className={`block-heading ${ALIGN[data.align] || ''} ${spacingClasses(data)}`.replace(/ +/g, ' ').trim()}>
            {data.heading}
            {data.headingEm ? <> <em>{data.headingEm}</em></> : null}
        </Tag>
    );
}
