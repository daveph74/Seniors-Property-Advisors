import { useHeadingLevel } from './headingLevel';

export default function WhyListSection({ data, anchor }) {
    const { image = {}, stamp = {} } = data;
    const Heading = `h${useHeadingLevel()}`;

    return (
        <section className="why" id={anchor}>
            <div className="container">
                <div className="why-grid">
                    <div>
                        <div className="eyebrow-line">{data.eyebrow}</div>
                        <Heading>
                            {data.heading} <em>{data.headingEm}</em>
                        </Heading>
                        <p className="section-lead">{data.lead}</p>

                        <ul className="why-list">
                            {(data.items || []).map((w, i) => (
                                <li key={i}>
                                    <div className="check">✓</div>
                                    <div>
                                        <b>{w.title}</b>
                                        <span>{w.body}</span>
                                    </div>
                                </li>
                            ))}
                        </ul>
                    </div>

                    <div className="why-visual">
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
                        <div className="stamp">
                            <div className="big">{stamp.value}</div>
                            <small>{stamp.text}</small>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    );
}
