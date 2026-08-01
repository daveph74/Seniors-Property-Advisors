import ActionButton from './ActionButton';

export default function CtaSection({ data, actions, anchor }) {
    return (
        <section className="cta" id={anchor}>
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
