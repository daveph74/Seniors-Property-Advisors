import ActionButton from './ActionButton';

export default function CtaSection({ data, actions, anchor }) {
    const { image = {} } = data;
    /* Falls back to navy: every call to action written before this field existed has none. */
    const background = data.background || 'navy';
    const showPhoto = background === 'image' && !! image.src;
    /* `on-navy` is a white-on-dark button. On the light background it would be white on white. */
    const dark = background !== 'white';

    return (
        <section className={`cta cta--${background}`} id={anchor}>
            {showPhoto ? (
                <div
                    className="cta-bg"
                    style={{ backgroundImage: `url('${image.src}')` }}
                    role="img"
                    aria-label={image.alt || ''}
                />
            ) : null}

            <div className="container">
                <div className="eyebrow-line">
                    <span style={{ background: 'var(--blue)' }} />
                    {data.eyebrow}
                </div>
                <h2>
                    {data.heading} <em>{data.headingEm}</em>
                </h2>
                <p>{data.body}</p>
                <div className="row">
                    {(data.buttons || []).map((cta, i) => (
                        <ActionButton
                            key={i}
                            cta={cta}
                            actions={actions}
                            className={`btn ${cta.variant} lg${cta.onNavy && dark ? ' on-navy' : ''}`}
                        />
                    ))}
                </div>
                <div className="marks">
                    {(data.trustMarks || []).map((m, i) => (
                        <span key={i}>{m}</span>
                    ))}
                </div>
            </div>
        </section>
    );
}
