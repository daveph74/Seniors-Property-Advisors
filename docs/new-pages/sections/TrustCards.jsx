import { TRUST_CARDS } from '../../data/content';

export default function TrustCards() {
    return (
        <section className="trust">
            <div className="container">
                <div className="section-head">
                    <div className="left">
                        <div className="eyebrow-line">Why Agent Finder exists</div>
                        <h2>
                            Choosing an agent shouldn’t feel <em>overwhelming.</em>
                        </h2>
                        <p className="section-lead">
                            Picking the wrong agent can cost homeowners tens of thousands — in
                            commission, marketing, and a price that falls short. Agent Finder
                            gives you a calm, guided way to compare your options before you
                            commit.
                        </p>
                    </div>
                    <a href="#" className="btn ghost">
                        Read our approach <span className="arr">→</span>
                    </a>
                </div>

                <div className="cards">
                    {TRUST_CARDS.map((c) => (
                        <div className="card" key={c.title}>
                            <div className="ico">{c.icon}</div>
                            <h3>{c.title}</h3>
                            <p>{c.body}</p>
                        </div>
                    ))}
                </div>
            </div>
        </section>
    );
}
