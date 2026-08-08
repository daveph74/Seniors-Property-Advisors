import { useFinder } from '../FinderContext';

/**
 * Full-bleed hero: the photograph runs edge to edge for a full viewport, with the copy
 * overlaid on the empty left side of the frame. Alternative to the boxed-photo `Hero`.
 */
export default function HeroFullBleed() {
    const { openFinder } = useFinder();

    return (
        <section className="hero-full">
            <div
                className="hero-full-bg"
                role="img"
                aria-label="Property advisor with a tablet guiding a senior couple in their living room"
            />

            <div className="container">
                <div className="hero-full-copy">
                    <span className="eyebrow-rule">Finding your agent</span>

                    <h1>
                        Find the right real estate agent, <em>without the stress.</em>
                    </h1>

                    <p>
                        We help Australian homeowners compare trusted local agents,
                        understand their options, and make confident selling decisions —
                        guided by an independent property advisor, not a salesperson.
                    </p>

                    <div className="hero-ctas">
                        <button className="btn secondary lg" onClick={openFinder}>
                            Find My Agent <span className="arr">→</span>
                        </button>
                        <a href="#" className="btn ghost lg on-navy">
                            Speak to an Advisor
                        </a>
                    </div>
                </div>
            </div>
        </section>
    );
}
