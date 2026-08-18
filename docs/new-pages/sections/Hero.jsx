import { useFinder } from '../FinderContext';
import { ShieldIcon, StarIcon } from '../icons';
import { HERO_AVATARS } from '../../data/content';

export default function Hero() {
    const { openFinder } = useFinder();

    return (
        <section className="hero">
            <div className="container">
                <div className="hero-grid">
                    <div>
                        <span className="eyebrow">
                            <span className="ico" aria-hidden="true">
                                <ShieldIcon size={12} />
                            </span>
                            Personal Guidance · Compare Trusted local agents
                        </span>
                        <h1 className="headline">
                            Independent advice. <em>Better selling decisions.</em>
                        </h1>
                        <p className="subhead">
                            Find the right local agent and sell with confidence.
                        </p>
                        <p className="lead">
                            Compare trusted local agents, understand your options and sell
                            with confidence—with independent guidance at every step.
                        </p>
                        <div className="hero-ctas">
                            <button className="btn primary lg" onClick={openFinder}>
                                Find My Agent <span className="arr">→</span>
                            </button>
                            <a href="#" className="btn ghost lg">
                                Speak to an Advisor
                            </a>
                        </div>
                        <div className="hero-trust">
                            <div className="avatars" aria-hidden="true">
                                {HERO_AVATARS.map((src) => (
                                    <span
                                        key={src}
                                        style={{ backgroundImage: `url('${src}')` }}
                                    />
                                ))}
                            </div>
                            <div>
                                <div className="row">
                                    <span className="stars">★★★★★</span>
                                    <b>Rated 4.9 / 5</b>
                                </div>
                                <small>by more than 1,800 Australian homeowners</small>
                            </div>
                        </div>
                    </div>

                    <div className="hero-visual">
                        <div
                            className="photo"
                            role="img"
                            aria-label="Property advisor with a tablet guiding a senior couple"
                        />

                        <div className="hero-card rating">
                            <div className="ico">
                                <StarIcon />
                            </div>
                            <div>
                                <b>Rated 4.9 / 5</b>
                                <small>Trusted by 1,800+ homeowners</small>
                            </div>
                        </div>

                        <div className="hero-card saving">
                            <b>Average client saving</b>
                            <span className="big">$11.4k</span>
                            <small>Based on clients who compared multiple agents.</small>
                        </div>
                    </div>
                </div>

                <div className="hero-steps">
                    {[
                        ['1', 'Tell us about your property'],
                        ['2', 'Compare suitable agents'],
                        ['3', 'Choose with confidence'],
                    ].map(([n, label]) => (
                        <div className="hs-item" key={n}>
                            <span className="hs-num">{n}</span>
                            <b>{label}</b>
                        </div>
                    ))}
                </div>
            </div>

            <div className="hero-pentagon" aria-hidden="true" />
        </section>
    );
}
