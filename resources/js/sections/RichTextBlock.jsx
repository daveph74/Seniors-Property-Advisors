import { spacingClasses, alignClasses } from './spacing';


export default function RichTextBlock({ data, anchor }) {
    const body = String(data.body || '');
    const className = `block-text ${alignClasses(data, 'block-text')} ${spacingClasses(data)}`.replace(/ +/g, ' ').trim();

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
