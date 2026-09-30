const SELF_STYLED = ['row', 'column'];

export function customCssOf(data = {}) {
    return typeof data.customCss === 'string' && data.customCss.trim() !== '' ? data.customCss : '';
}

export function stylesSelf(type) {
    return SELF_STYLED.includes(type);
}

export function CustomStyle({ id, type, css }) {
    if (! css || ! id) return null;

    const scope = `[data-cms-block=${JSON.stringify(String(id))}]${stylesSelf(type) ? '' : ' > *'}`;
    const body = css.replace(/<\//g, '<\\/').replace(/<style/gi, '');

    return <style data-cms-custom={id}>{`${scope} {\n${body}\n}`}</style>;
}

export default function CustomStyled({ id, type, data = {}, children }) {
    const css = customCssOf(data);

    if (! css) return children;

    if (stylesSelf(type)) {
        return (
            <>
                <CustomStyle id={id} type={type} css={css} />
                {children}
            </>
        );
    }

    return (
        <div className="cms-custom" data-cms-block={id}>
            <CustomStyle id={id} type={type} css={css} />
            {children}
        </div>
    );
}
