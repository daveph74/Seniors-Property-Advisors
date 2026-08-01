import { useEffect, useRef, useState } from 'react';

const TOOLS = [
    { label: 'H2', title: 'Heading', prefix: '## ', block: true },
    { label: 'H3', title: 'Smaller heading', prefix: '### ', block: true },
    { label: 'B', title: 'Bold', wrap: '**', style: { fontWeight: 700 } },
    { label: 'I', title: 'Italic', wrap: '*', style: { fontStyle: 'italic' } },
    { label: '“ ”', title: 'Quote', prefix: '> ', block: true },
    { label: '• List', title: 'Bulleted list', prefix: '- ', block: true },
    { label: '1. List', title: 'Numbered list', prefix: '1. ', block: true },
    { label: 'Link', title: 'Link', link: true },
    { label: 'Image', title: 'Image', image: true },
    { label: 'Table', title: 'Table', insert: '\n| Column | Column |\n| --- | --- |\n| | |\n' },
];

export default function MarkdownEditor({ value, onChange, onPickImage, error }) {
    const area = useRef(null);
    const [tab, setTab] = useState('write');
    const [html, setHtml] = useState('');
    const [rendering, setRendering] = useState(false);

    useEffect(() => {
        if (tab !== 'preview') return undefined;

        let live = true;

        setRendering(true);

        fetch('/cms/blog/render', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({ body: value || '' }),
        })
            .then((r) => r.json())
            .then((data) => { if (live) setHtml(data.html || ''); })
            .catch(() => { if (live) setHtml('<p>The preview could not be loaded.</p>'); })
            .finally(() => { if (live) setRendering(false); });

        return () => { live = false; };
    }, [tab, value]);

    const replace = (next, cursor) => {
        onChange(next);

        requestAnimationFrame(() => {
            const el = area.current;

            if (! el) return;

            el.focus();
            el.setSelectionRange(cursor, cursor);
        });
    };

    const apply = (tool) => {
        const el = area.current;
        const text = value || '';

        if (! el) return;

        const start = el.selectionStart ?? text.length;
        const end = el.selectionEnd ?? start;
        const selected = text.slice(start, end);

        if (tool.image) {
            onPickImage?.((src) => {
                const snippet = `![](${src})`;

                replace(text.slice(0, start) + snippet + text.slice(end), start + snippet.length);
            });

            return;
        }

        if (tool.link) {
            const snippet = `[${selected || 'link text'}](https://)`;

            replace(text.slice(0, start) + snippet + text.slice(end), start + snippet.length - 1);

            return;
        }

        if (tool.insert) {
            replace(text.slice(0, start) + tool.insert + text.slice(end), start + tool.insert.length);

            return;
        }

        if (tool.block) {
            const lineStart = text.lastIndexOf('\n', start - 1) + 1;
            const already = text.slice(lineStart).startsWith(tool.prefix);
            const next = already
                ? text.slice(0, lineStart) + text.slice(lineStart + tool.prefix.length)
                : text.slice(0, lineStart) + tool.prefix + text.slice(lineStart);

            replace(next, start + (already ? -tool.prefix.length : tool.prefix.length));

            return;
        }

        const snippet = tool.wrap + (selected || '') + tool.wrap;

        replace(
            text.slice(0, start) + snippet + text.slice(end),
            selected ? start + snippet.length : start + tool.wrap.length,
        );
    };

    return (
        <div className={`cms-md ${error ? 'cms-md--error' : ''}`}>
            <div className="cms-md__bar">
                {TOOLS.map((tool) => (
                    <button
                        key={tool.label}
                        type="button"
                        className="cms-md__tool"
                        title={tool.title}
                        style={tool.style}
                        onClick={() => apply(tool)}
                        disabled={tab === 'preview'}
                    >
                        {tool.label}
                    </button>
                ))}

                <div className="cms-md__tabs">
                    <button
                        type="button"
                        className={`cms-md__tab ${tab === 'write' ? 'cms-md__tab--on' : ''}`}
                        onClick={() => setTab('write')}
                    >
                        Write
                    </button>
                    <button
                        type="button"
                        className={`cms-md__tab ${tab === 'preview' ? 'cms-md__tab--on' : ''}`}
                        onClick={() => setTab('preview')}
                    >
                        Preview
                    </button>
                </div>
            </div>

            {tab === 'write' ? (
                <textarea
                    ref={area}
                    className="cms-md__area"
                    rows={22}
                    value={value || ''}
                    onChange={(e) => onChange(e.target.value)}
                    placeholder="Write the article. Use the buttons above for headings, lists, links and images."
                />
            ) : (
                <div className="cms-md__preview prose">
                    {rendering && html === '' ? <p>Loading the preview…</p> : null}
                    <div dangerouslySetInnerHTML={{ __html: html }} />
                </div>
            )}
        </div>
    );
}
