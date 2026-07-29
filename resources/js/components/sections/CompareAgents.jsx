import { useFinder } from '../FinderContext';
import { AGENTS } from '../../data/content';

export default function CompareAgents({ headingLevel: Heading = 'h2' }) {
    const { openFinder } = useFinder();

    return (
        <section className="compare">
            <div className="container">
                <div className="section-head">
                    <div className="left">
                        <div className="eyebrow-line">Compare your shortlist</div>
                        <Heading className={Heading === 'h1' ? 'section-title' : undefined}>
                            A clear, honest view of <em>every option.</em>
                        </Heading>
                        <p className="section-lead">
                            Below is the kind of comparison you’ll receive — three real local
                            agents, side‑by‑side, with your advisor’s notes added in.
                        </p>
                    </div>
                    <a href="#" className="btn ghost">
                        See a sample report <span className="arr">→</span>
                    </a>
                </div>

                <div className="compare-shell">
                    <div className="compare-toolbar">
                        <div className="left">
                            <span className="chip active">
                                Mosman, NSW 2088 <span className="x">×</span>
                            </span>
                            <span className="chip">4‑bed home</span>
                            <span className="chip">Sell in 3 – 6 months</span>
                            <span className="chip count">3 agents shortlisted</span>
                        </div>
                        <div className="right">
                            <button className="chip btnlike">Sort: Advisor pick ▾</button>
                        </div>
                    </div>

                    <div className="compare-grid">
                        {/* Header row */}
                        <div className="cmp-cell first-label">Your shortlist</div>
                        {AGENTS.map((a) => (
                            <div
                                key={a.name}
                                className={`cmp-cell head${a.best ? ' best' : ''}`}
                            >
                                <div className="agent-head">
                                    <span
                                        className="av"
                                        style={{ backgroundImage: `url('${a.avatar}')` }}
                                    />
                                    <b>{a.name}</b>
                                    <small>{a.firm}</small>
                                </div>
                            </div>
                        ))}

                        {/* Local experience */}
                        <div className="cmp-cell label">Local experience</div>
                        {AGENTS.map((a) => (
                            <div className="cmp-cell" key={a.name}>
                                <strong>{a.experience.strong}</strong> {a.experience.rest}
                                <div className="meter">
                                    <i style={{ width: `${a.experience.meter}%` }} />
                                </div>
                            </div>
                        ))}

                        {/* Recent sales */}
                        <div className="cmp-cell label">Recent sales (12 mo)</div>
                        {AGENTS.map((a) => (
                            <div className="cmp-cell" key={a.name}>
                                <strong>{a.sales.strong}</strong>
                                <br />
                                <span className="sub">{a.sales.sub}</span>
                            </div>
                        ))}

                        {/* Commission */}
                        <div className="cmp-cell label">Commission estimate</div>
                        {AGENTS.map((a) => (
                            <div className="cmp-cell" key={a.name}>
                                <span className="price">{a.commission.price}</span>
                                <div className="sub">{a.commission.sub}</div>
                            </div>
                        ))}

                        {/* Marketing */}
                        <div className="cmp-cell label">Marketing budget</div>
                        {AGENTS.map((a) => (
                            <div className="cmp-cell" key={a.name}>
                                <strong>{a.marketing.strong}</strong>
                                <div className="sub">{a.marketing.sub}</div>
                            </div>
                        ))}

                        {/* Notes */}
                        <div className="cmp-cell label">Advisor notes</div>
                        {AGENTS.map((a) => (
                            <div className="cmp-cell note" key={a.name}>
                                {a.note}
                            </div>
                        ))}

                        {/* CTA */}
                        <div className="cmp-cell label">Next step</div>
                        {AGENTS.map((a) => (
                            <div className="cmp-cell cta" key={a.name}>
                                <button
                                    className={`btn ${a.cta.variant} sm block`}
                                    onClick={a.cta.variant === 'primary' ? openFinder : undefined}
                                >
                                    {a.cta.label}
                                    {a.cta.arrow && <span className="arr">→</span>}
                                </button>
                            </div>
                        ))}
                    </div>
                </div>
            </div>
        </section>
    );
}
