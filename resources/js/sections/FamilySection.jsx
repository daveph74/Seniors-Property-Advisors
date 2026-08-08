import ActionButton from './ActionButton';
import { useHeadingLevel } from './headingLevel';

export default function FamilySection({ data, actions, anchor }) {
    const Heading = `h${useHeadingLevel()}`;
    const { image = {}, testimonial = {} } = data;

    return (
        <section className="family" id={anchor}>
            <div className="container">
                <div className="family-grid">
                    <div className="family-visual">
                        {image.src ? (
                            <img
                                className="ph"
                                src={image.src}
                                alt={image.alt || ""}
                                loading="lazy"
                                decoding="async"
                            />
                        ) : (
                            <div className="ph" />
                        )}
                        <div className="quote">
                            <span
                                className="av"
                                style={{ backgroundImage: `url('${testimonial.avatar}')` }}
                            />
                            <div>
                                <p>{testimonial.quote}</p>
                                <small>{testimonial.by}</small>
                            </div>
                        </div>
                    </div>

                    <div>
                        <div className="eyebrow-line">{data.eyebrow}</div>
                        <Heading className="section-head__title">
                            {data.heading} <em>{data.headingEm}</em>
                        </Heading>
                        <p className="section-lead">{data.lead}</p>

                        <ul className="checks">
                            {(data.checks || []).map((c, i) => (
                                <li key={i}>
                                    <span className="c">✓</span>
                                    <span>{c}</span>
                                </li>
                            ))}
                        </ul>

                        <div className="btn-row">
                            {(data.ctas || []).map((cta, i) => (
                                <ActionButton
                                    key={i}
                                    cta={cta}
                                    actions={actions}
                                    className={`btn ${cta.variant}`}
                                />
                            ))}
                        </div>
                    </div>
                </div>
            </div>
        </section>
    );
}
