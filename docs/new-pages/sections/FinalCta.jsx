import { useFinder } from '../FinderContext';

export default function FinalCta() {
    const { openFinder } = useFinder();

    return (
        <section className="cta">
            <div className="container">
                <div className="eyebrow-line">
                    <span style={{ background: 'var(--blue)' }} />
                    Ready when you are
                </div>
                <h2>
                    Ready to find <em>the right agent?</em>
                </h2>
                <p>
                    Start with a free, no‑pressure conversation. We’ll help you understand your
                    options — and only move forward when it feels right.
                </p>
                <div className="row">
                    <button className="btn secondary lg" onClick={openFinder}>
                        Get Started <span className="arr">→</span>
                    </button>
                    <a href="tel:1300277228" className="btn ghost lg on-navy">
                        Call 1300 277 228
                    </a>
                </div>
                <div className="marks">
                    <span>Independent advice</span>
                    <span>No agent commissions</span>
                    <span>Australian owned</span>
                    <span>Member, REIA</span>
                </div>
            </div>
        </section>
    );
}
