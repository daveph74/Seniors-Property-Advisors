import { WHY_ITEMS } from '../../data/content';

export default function WhyAgentFinder({ headingLevel: Heading = 'h2' }) {
    return (
        <section className="why">
            <div className="container">
                <div className="why-grid">
                    <div>
                        <div className="eyebrow-line">Why use Agent Finder</div>
                        <Heading className={Heading === 'h1' ? 'section-title' : undefined}>
                            Confidence before you ever <em>sign anything.</em>
                        </Heading>
                        <p className="section-lead">
                            Selling the family home is one of life’s biggest financial decisions.
                            Agent Finder gives you the time, the information and the independent
                            support to make it feel calm — not pressured.
                        </p>

                        <ul className="why-list">
                            {WHY_ITEMS.map((w) => (
                                <li key={w.title}>
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
                        <div
                            className="ph"
                            role="img"
                            aria-label="Charming Australian home exterior"
                        />
                        <div className="stamp">
                            <div className="big">30+</div>
                            <small>
                                years of property advisory experience guiding Australian
                                families.
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    );
}
