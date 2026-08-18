import ActionButton from './ActionButton';

/**
 * Full-bleed hero: the photograph runs edge to edge and the copy sits over the darkened
 * left of the frame. The alternative to the boxed-photo `hero`.
 *
 * The picture is a data field rather than a CSS background so an editor can change it from
 * the media library. With none chosen the gradient stands alone, which is legible rather
 * than broken — the copy is white on navy either way.
 */
export default function HeroFullSection({ data, actions, anchor }) {
    const { image = {} } = data;

    return (
        <section className="hero-full" id={anchor}>
            <div
                className="hero-full-bg"
                style={image.src ? { backgroundImage: `url('${image.src}')` } : undefined}
                role={image.src ? 'img' : undefined}
                aria-label={image.src ? image.alt || '' : undefined}
            />

            <div className="container">
                <div className="hero-full-copy">
                    {data.eyebrow ? <span className="eyebrow-rule">{data.eyebrow}</span> : null}

                    <h1>
                        {data.heading} {data.headingEm ? <em>{data.headingEm}</em> : null}
                    </h1>

                    <p>{data.lead}</p>

                    <div className="hero-ctas">
                        {(data.ctas || []).map((cta, i) => (
                            <ActionButton
                                key={i}
                                cta={cta}
                                actions={actions}
                                className={`btn ${cta.variant}${cta.onNavy ? ' on-navy' : ''} lg`}
                            />
                        ))}
                    </div>
                </div>
            </div>
        </section>
    );
}
