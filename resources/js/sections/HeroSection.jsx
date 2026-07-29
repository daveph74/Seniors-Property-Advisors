import ActionButton from './ActionButton';
import { ShieldIcon, StarIcon } from '../components/icons';

export default function HeroSection({ data, actions, anchor }) {
    const { image = {}, rating = {}, ratingCard = {}, savingCard = {} } = data;

    return (
        <section className="hero" id={anchor}>
            <div className="container">
                <div className="hero-grid">
                    <div>
                        <span className="eyebrow">
                            <span className="ico" aria-hidden="true">
                                <ShieldIcon size={12} />
                            </span>
                            {data.eyebrow}
                        </span>
                        <h1 className="headline">
                            {data.heading} <em>{data.headingEm}</em>
                        </h1>
                        <p className="subhead">{data.subhead}</p>
                        <p className="lead">{data.lead}</p>
                        <div className="hero-ctas">
                            {(data.ctas || []).map((cta, i) => (
                                <ActionButton
                                    key={i}
                                    cta={cta}
                                    actions={actions}
                                    className={`btn ${cta.variant} lg`}
                                />
                            ))}
                        </div>
                        <div className="hero-trust">
                            <div className="avatars" aria-hidden="true">
                                {(data.avatars || []).map((src, i) => (
                                    <span key={i} style={{ backgroundImage: `url('${src}')` }} />
                                ))}
                            </div>
                            <div>
                                <div className="row">
                                    <span className="stars">{rating.stars}</span>
                                    <b>{rating.label}</b>
                                </div>
                                <small>{rating.note}</small>
                            </div>
                        </div>
                    </div>

                    <div className="hero-visual">
                        <div
                            className="photo"
                            role="img"
                            aria-label={image.alt}
                            style={image.src ? { backgroundImage: `url('${image.src}')` } : undefined}
                        />

                        <div className="hero-card rating">
                            <div className="ico">
                                <StarIcon />
                            </div>
                            <div>
                                <b>{ratingCard.label}</b>
                                <small>{ratingCard.note}</small>
                            </div>
                        </div>

                        <div className="hero-card saving">
                            <b>{savingCard.label}</b>
                            <span className="big">{savingCard.value}</span>
                            <small>{savingCard.note}</small>
                        </div>
                    </div>
                </div>

                <div className="hero-steps">
                    {(data.steps || []).map((s, i) => (
                        <div className="hs-item" key={i}>
                            <span className="hs-num">{s.n}</span>
                            <b>{s.label}</b>
                        </div>
                    ))}
                </div>
            </div>

            <div className="hero-pentagon" aria-hidden="true" />
        </section>
    );
}
