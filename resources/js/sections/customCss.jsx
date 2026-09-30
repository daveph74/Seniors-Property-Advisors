const SELF_STYLED = ['row', 'column'];

export function customCssOf(data = {}) {
    return typeof data.customCss === 'string' && data.customCss.trim() !== '' ? data.customCss : '';
}

export function stylesSelf(type) {
    return SELF_STYLED.includes(type);
}

export function splitCss(css) {
    const declarations = [];
    const rules = [];
    let depth = 0;
    let buffer = '';
    let selector = '';

    for (const ch of css) {
        if (ch === '{') {
            if (depth === 0) {
                selector = buffer.trim();
                buffer = '';
            } else {
                buffer += ch;
            }

            depth += 1;
        } else if (ch === '}') {
            depth = Math.max(0, depth - 1);

            if (depth === 0) {
                if (selector) rules.push({ selector, body: buffer.trim() });
                selector = '';
                buffer = '';
            } else {
                buffer += ch;
            }
        } else if (depth === 0 && ch === ';') {
            const declaration = buffer.trim();

            if (declaration) declarations.push(declaration);
            buffer = '';
        } else {
            buffer += ch;
        }
    }

    const tail = buffer.trim();

    if (depth === 0 && tail) declarations.push(tail);

    return { declarations, rules };
}

function scopedSelector(root, selector) {
    const parts = selector.split(',').map((s) => s.trim()).filter(Boolean);

    return parts.map((part) => {
        if (part.startsWith('&')) return `${root}${part.slice(1)}`;
        if (part.includes('::')) return part.startsWith(':') ? `${root}${part}, ${root} ${part}` : `${root} ${part}`;

        return `${root}:is(${part}), ${root} :is(${part})`;
    }).join(', ');
}

export function scopedCss(root, css) {
    const { declarations, rules } = splitCss(css);
    const out = [];

    if (declarations.length) out.push(`${root} {\n${declarations.join(';\n')};\n}`);

    rules.forEach(({ selector, body }) => {
        out.push(`${scopedSelector(root, selector)} {\n${body}\n}`);
    });

    return out.join('\n');
}

export function CustomStyle({ id, type, css }) {
    if (! css || ! id) return null;

    const block = `[data-cms-block=${JSON.stringify(String(id))}]`;
    const root = stylesSelf(type)
        ? block
        : `:is(${block} > :not(.backdrop--wrap), ${block} > .backdrop--wrap > :not(.section-block__bg):not(.section-block__overlay))`;
    const body = css.replace(/<\//g, '<\\/').replace(/<style/gi, '');

    return <style data-cms-custom={id}>{scopedCss(root, body)}</style>;
}

export default function CustomStyled({ id, type, data = {}, children }) {
    const css = customCssOf(data);

    if (! css) return children;

    return (
        <>
            <CustomStyle id={id} type={type} css={css} />
            {stylesSelf(type) ? children : <div className="cms-custom" data-cms-block={id}>{children}</div>}
        </>
    );
}
