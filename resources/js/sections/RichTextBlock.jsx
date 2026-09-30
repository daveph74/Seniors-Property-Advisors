import { spacingClasses } from './spacing';

const ALIGN = { left: '', center: 'block-text--center', right: 'block-text--right' };

export default function RichTextBlock({ data, anchor }) {
    const body = String(data.body || '');
    const className = `block-text ${ALIGN[data.align] || ''} ${spacingClasses(data)}`.replace(/ +/g, ' ').trim();

    if (body.includes('<')) {
        return <div id={anchor} className={className} dangerouslySetInnerHTML={{ __html: body }} />;
    }

    const paragraphs = body
        .split(/\n{2,}/)
        .map((p) => p.trim())
        .filter(Boolean);

    if (paragraphs.length === 0) return null;

    return (
        <div id={anchor} className={className}>
            {paragraphs.map((p, i) => (
                <p key={i}>{p}</p>
            ))}
        </div>
    );
}
