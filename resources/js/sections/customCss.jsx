const SELF_STYLED = ['row', 'column'];

export function customCssOf(data = {}) {
    return typeof data.customCss === 'string' && data.customCss.trim() !== '' ? data.customCss : '';
}

export function stylesSelf(type) {
    return SELF_STYLED.includes(type);
}

export function CustomStyle({ id, type, css }) {
    if (! css || ! id) return null;

    const block = `[data-cms-block=${JSON.stringify(String(id))}]`;
    const scope = stylesSelf(type)
        ? block
        : `:is(${block} > :not(.backdrop--wrap), ${block} > .backdrop--wrap > :not(.section-block__bg):not(.section-block__overlay))`;
    const body = css.replace(/<\//g, '<\\/').replace(/<style/gi, '');

    return <style data-cms-custom={id}>{`${scope} {\n${body}\n}`}</style>;
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
