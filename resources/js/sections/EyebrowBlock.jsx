import { spacingClasses, alignClasses } from './spacing';


export default function EyebrowBlock({ data, anchor }) {
    if (!data.eyebrow) return null;

    return (
        <div id={anchor} className={`eyebrow-line block-eyebrow ${alignClasses(data, 'block-eyebrow')} ${spacingClasses(data)}`.replace(/ +/g, ' ').trim()}>
            {data.eyebrow}
        </div>
    );
}
