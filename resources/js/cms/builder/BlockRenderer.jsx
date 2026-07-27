import { resolveSection } from '../../sections/registry';

export default function BlockRenderer({ block, useRegistry = false }) {
    const { type, data } = block;

    const Section = useRegistry ? resolveSection(type) : null;

    if (Section) {
        return <Section data={data || {}} actions={{}} />;
    }

    if (type === 'hero') {
        return (
            <div className="cms-blk-hero">
                <div className="cms-blk-hero__inner">
                    <div className="cms-blk-hero__eyebrow">{data.eyebrow}</div>
                    <h1 className="cms-blk-hero__heading">{data.heading}</h1>
                    <p className="cms-blk-hero__body">{data.body}</p>
                    <div className="cms-blk-hero__ctas">
                        <span className="cms-blk-hero__primary">{data.primary}</span>
                        <span className="cms-blk-hero__secondary">{data.secondary}</span>
                    </div>
                </div>
            </div>
        );
    }

    if (type === 'services') {
        return (
            <div className="cms-blk-services">
                <h2 className="cms-blk-services__heading">{data.heading}</h2>
                <div className="cms-blk-services__grid">
                    {data.items.map((it, i) => (
                        <div key={i} className="cms-blk-services__item">
                            <div className="cms-blk-services__item-title">{it.title}</div>
                            <div className="cms-blk-services__item-text">{it.text}</div>
                        </div>
                    ))}
                </div>
            </div>
        );
    }

    if (type === 'stats') {
        return (
            <div className="cms-blk-stats">
                {data.items.map((it, i) => (
                    <div key={i}>
                        <div className="cms-blk-stats__n">{it.n}</div>
                        <div className="cms-blk-stats__label">{it.label}</div>
                    </div>
                ))}
            </div>
        );
    }

    if (type === 'testimonials') {
        return (
            <div className="cms-blk-testimonials">
                <h2 className="cms-blk-testimonials__heading">{data.heading}</h2>
                <div className="cms-blk-testimonials__grid">
                    {data.items.map((it, i) => (
                        <div key={i} className="cms-blk-testimonials__item">
                            <div className="cms-blk-testimonials__quote">“{it.quote}”</div>
                            <div className="cms-blk-testimonials__name">{it.name} · {it.loc}</div>
                        </div>
                    ))}
                </div>
            </div>
        );
    }

    if (type === 'faqs') {
        return (
            <div className="cms-blk-faqs">
                <h2 className="cms-blk-faqs__heading">{data.heading}</h2>
                <div className="cms-blk-faqs__list">
                    {data.items.map((it, i) => (
                        <div key={i} className="cms-blk-faqs__item">
                            <span className="cms-blk-faqs__item-q">{it.q}</span>
                            <svg style={{ marginLeft: 'auto' }} width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="#9AA6B6" strokeWidth="2" strokeLinecap="round">
                                <path d="m6 9 6 6 6-6" />
                            </svg>
                        </div>
                    ))}
                </div>
            </div>
        );
    }

    if (type === 'cta') {
        return (
            <div className="cms-blk-cta">
                <div style={{ flex: 1 }}>
                    <h2 className="cms-blk-cta__heading">{data.heading}</h2>
                    <p className="cms-blk-cta__body">{data.body}</p>
                </div>
                <span className="cms-blk-cta__primary">{data.primary}</span>
            </div>
        );
    }

    return (
        <div className="cms-blk-rich">
            <h2 className="cms-blk-rich__heading">{data.heading}</h2>
            <p className="cms-blk-rich__body">{data.body}</p>
        </div>
    );
}
