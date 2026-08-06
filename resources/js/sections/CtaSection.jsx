import ActionButton from './ActionButton';

export default function CtaSection({ data, actions, anchor }) {
    const { image = {} } = data;

    return (
        <section className={`cta${image.src ? ' cta--photo' : ''}`} id={anchor}>
            {image.src ? (
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
                            className={`btn ${cta.variant} lg${cta.onNavy ? ' on-navy' : ''}`}
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
