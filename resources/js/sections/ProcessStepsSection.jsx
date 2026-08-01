export default function ProcessStepsSection({ data, anchor }) {
    return (
        <section className="how" id={anchor}>
            <div className="container">
                <div className="section-head center">
                    <div className="eyebrow-line">{data.eyebrow}</div>
                    <h2>
                        {data.heading} <em>{data.headingEm}</em>
                    </h2>
                    <p className="section-lead">{data.lead}</p>
                </div>

                <div className="steps">
                    {(data.items || []).map((s, i) => (
                        <div className="step" key={i}>
                            <div className="num">{s.num}</div>
                            <h4>{s.title}</h4>
                            <p>{s.body}</p>
                        </div>
                    ))}
                </div>
            </div>
        </section>
    );
}
