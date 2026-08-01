import { spacingClasses } from './spacing';

const ALIGN = { left: '', center: 'block-stat-stamp--center', right: 'block-stat-stamp--right' };

export default function StatStampBlock({ data, anchor }) {
    if (!data.value && !data.text) return null;

    return (
        <div
            id={anchor}
            className={`block-stat-stamp ${ALIGN[data.align] || ''} ${spacingClasses(data)}`.replace(/ +/g, ' ').trim()}
        >
            <div className="big">{data.value}</div>
            <small>{data.text}</small>
        </div>
    );
}
