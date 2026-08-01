import SectionHead from './SectionHead';
import PendingModule from './PendingModule';

const SOURCE_LABEL = {
    featured: 'Featured testimonials only',
    all: 'All active testimonials',
    chosen: 'The testimonials chosen in the panel',
};

/*
 * Picked from the library in the browser rather than by the server: there is no paging to get
 * wrong, every section on a page draws from the same small set, and one payload beats a query per
 * section. Consent is enforced before this — the library only carries testimonials whose
 * permission has been recorded.
 */
function pull(data, library) {
    const all = library.testimonials || [];

    if (data.source === 'chosen') {
        return (data.chosen || '')
            .split(',')
            .map((id) => all.find((t) => String(t.id) === id.trim()))
            .filter(Boolean);
    }

    return data.source === 'all' ? all : all.filter((t) => t.featured);
}

export default function TestimonialsSection({ data, anchor, library = {}, editing = false }) {
    const limit = Number(data.limit) > 0 ? Number(data.limit) : null;
    const pulled = pull(data, library).slice(0, limit || undefined);

    const items = pulled.length > 0
        ? pulled
        : (data.items || []).filter((t) => t && (t.quote || t.name));

    const slider = data.layout === 'slider' && ! editing;

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
                            SOURCE_LABEL[data.source] || SOURCE_LABEL.featured,
                            limit ? `Showing up to ${limit}` : 'Showing all that match',
                            data.layout === 'slider' ? 'Displayed as a slider' : 'Displayed as a grid',
                            'Ordered by the display order set in the library',
                            'Only testimonials with recorded client permission',
                        ]}
                    />
                ) : (
                    <div
                        className={`testimonials__grid${slider ? ' testimonials__grid--slider' : ''}`}
                        /* A scroller is mouse-only unless it can be focused and arrow-keyed, and
                           this audience is exactly who that would shut out. */
                        tabIndex={slider ? 0 : undefined}
                        role={slider ? 'group' : undefined}
                        aria-label={slider ? 'Client testimonials — scroll or use the arrow keys' : undefined}
                    >
                        {items.map((t, i) => (
                            <figure className="testimonial" key={t.id ?? i}>
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
