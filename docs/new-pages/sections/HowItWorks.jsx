import { STEPS } from '../../data/content';

export default function HowItWorks({ headingLevel: Heading = 'h2' }) {
    return (
        <section className="how">
            <div className="container">
                <div className="section-head center">
                    <div className="eyebrow-line">How it works</div>
                    <Heading className={Heading === 'h1' ? 'section-title' : undefined}>
                        A simple, supported process — <em>start to sold.</em>
                    </Heading>
                    <p className="section-lead">
                        Four calm steps, with an advisor walking with you the whole way.
                    </p>
                </div>

                <div className="steps">
                    {STEPS.map((s) => (
                        <div className="step" key={s.num}>
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
