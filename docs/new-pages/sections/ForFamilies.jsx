import { useFinder } from '../FinderContext';
import { FAMILY_CHECKS } from '../../data/content';

export default function ForFamilies({ headingLevel: Heading = 'h2' }) {
    const { openFinder } = useFinder();

    return (
        <section className="family">
            <div className="container">
                <div className="family-grid">
                    <div className="family-visual">
                        <div
                            className="ph"
                            role="img"
                            aria-label="Adult daughter sitting with her mother going through paperwork"
                        />
                        <div className="quote">
                            <span
                                className="av"
                                style={{
                                    backgroundImage:
                                        "url('https://images.unsplash.com/photo-1494790108377-be9c29b29330?auto=format&fit=crop&w=160&q=80')",
                                }}
                            />
                            <div>
                                <p>
                                    “Mum felt heard the whole way through. We finally had a plan
                                    we all agreed on.”
                                </p>
                                <small>— Rachel, helping her mother sell in Glenelg, SA</small>
                            </div>
                        </div>
                    </div>

                    <div>
                        <div className="eyebrow-line">For families</div>
                        <Heading className={Heading === 'h1' ? 'section-title' : undefined}>
                            Helping a parent sell <em>their home?</em>
                        </Heading>
                        <p className="section-lead">
                            Many of the people we help aren’t selling themselves — they’re adult
                            children supporting a parent through a downsize, a move closer to
                            family, or the next chapter of care. Agent Finder is designed for
                            these conversations.
                        </p>

                        <ul className="checks">
                            {FAMILY_CHECKS.map((c) => (
                                <li key={c}>
                                    <span className="c">✓</span>
                                    <span>{c}</span>
                                </li>
                            ))}
                        </ul>

                        <div className="btn-row">
                            <button className="btn primary" onClick={openFinder}>
                                Start as a family <span className="arr">→</span>
                            </button>
                            <a href="#" className="btn ghost">
                                Family guide (PDF)
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    );
}
