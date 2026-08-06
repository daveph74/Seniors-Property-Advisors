import { useHeadingLevel } from './headingLevel';

export default function ProcessStepsSection({ data, anchor }) {
    const Heading = `h${useHeadingLevel()}`;

    return (
        <section className="how" id={anchor}>
            <div className="container">
                <div className="section-head center">
                    <div className="eyebrow-line">{data.eyebrow}</div>
                    <Heading>
                        {data.heading} <em>{data.headingEm}</em>
                    </Heading>
                    <p className="section-lead">{data.lead}</p>
                </div>

                <div className="steps">
                    {(data.items || []).map((s, i) => (
                        <div className="step" key={i}>
                            <div className="num">{s.num}</div>
                            <h3>{s.title}</h3>
                            <p>{s.body}</p>
                        </div>
                    ))}
                </div>
            </div>
        </section>
    );
}
