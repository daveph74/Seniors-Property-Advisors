import { spacingClasses } from './spacing';

export default function ImageBlock({ data, anchor }) {
    if (!data.src) return null;

    return (
        <figure id={anchor} className={`block-image ${spacingClasses(data)}`.trim()}>
            <img src={data.src} alt={data.alt || ''} loading="lazy" />
            {data.caption ? <figcaption>{data.caption}</figcaption> : null}
        </figure>
    );
}
