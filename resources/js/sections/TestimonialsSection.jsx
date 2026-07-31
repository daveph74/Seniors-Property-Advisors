import SectionHead from './SectionHead';
import PendingModule from './PendingModule';

const SOURCE_LABEL = {
    featured: 'Featured testimonials only',
    all: 'All active testimonials',
};

export default function TestimonialsSection({ data, anchor, editing = false }) {
    const items = (data.items || []).filter((t) => t && (t.quote || t.name));
    const source = SOURCE_LABEL[data.source] || SOURCE_LABEL.featured;
    const limit = Number(data.limit) > 0 ? Number(data.limit) : null;

    if (items.length === 0 && ! editing) return null;

    return (
        <section className="testimonials" id={anchor}>
            <div className="container">
                <SectionHead {...data} />

                {items.length === 0 ? (
                    <PendingModule
                        title="Testimonials"
                        waitingFor="testimonial library"
                        willPull={[
                            source,
                            limit ? `Showing up to ${limit}` : 'Showing all that match',
                            data.layout === 'slider' ? 'Displayed as a slider' : 'Displayed as a grid',
                            'Ordered by the display order set in the library',
                        ]}
                    />
                ) : (
                    <div className={`testimonials__grid${data.layout === 'slider' ? ' testimonials__grid--slider' : ''}`}>
                        {items.map((t, i) => (
                            <figure className="testimonial" key={i}>
                                {t.rating ? (
                                    <div className="testimonial__rating" aria-label={`${t.rating} out of 5`}>
                                        {'★'.repeat(Math.max(0, Math.min(5, Number(t.rating) || 0)))}
                                    </div>
                                ) : null}

                                {t.headline ? <h3 className="testimonial__headline">{t.headline}</h3> : null}

                                <blockquote className="testimonial__quote">{t.quote}</blockquote>

                                <figcaption className="testimonial__by">
                                    {t.avatar ? <img src={t.avatar} alt="" loading="lazy" decoding="async" /> : null}
                                    <span>
                                        <b>{t.name}</b>
                                        {t.location ? <small>{t.location}</small> : null}
                                    </span>
                                </figcaption>
                            </figure>
                        ))}
                    </div>
                )}
            </div>
        </section>
    );
}
