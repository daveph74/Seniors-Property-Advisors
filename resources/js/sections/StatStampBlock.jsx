import { spacingClasses, alignClasses } from './spacing';


export default function StatStampBlock({ data, anchor }) {
    if (!data.value && !data.text) return null;

    return (
        <div
            id={anchor}
            className={`block-stat-stamp ${alignClasses(data, 'block-stat-stamp')} ${spacingClasses(data)}`.replace(/ +/g, ' ').trim()}
        >
            <div className="big">{data.value}</div>
            <small>{data.text}</small>
        </div>
    );
}
