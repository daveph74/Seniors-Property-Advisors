import { spacingClasses } from './spacing';

const ALIGN = { left: '', center: 'block-text--center', right: 'block-text--right' };

export default function RichTextBlock({ data, anchor }) {
    const paragraphs = String(data.body || '')
        .split(/\n{2,}/)
        .map((p) => p.trim())
        .filter(Boolean);

    if (paragraphs.length === 0) return null;

    return (
        <div id={anchor} className={`block-text ${ALIGN[data.align] || ''} ${spacingClasses(data)}`.replace(/ +/g, ' ').trim()}>
            {paragraphs.map((p, i) => (
                <p key={i}>{p}</p>
            ))}
        </div>
    );
}
