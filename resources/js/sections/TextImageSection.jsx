import ActionButton from './ActionButton';

export default function TextImageSection({ data, actions, anchor }) {
    const image = data.image || {};
    const flipped = data.imageSide === 'left';

    return (
        <section className={`text-image${flipped ? ' text-image--flipped' : ''}`} id={anchor}>
            <div className="container text-image__grid">
                <div className="text-image__copy">
                    {data.eyebrow ? <div className="eyebrow-line">{data.eyebrow}</div> : null}

                    {data.heading || data.headingEm ? (
                        <h2>
                            {data.heading} {data.headingEm ? <em>{data.headingEm}</em> : null}
                        </h2>
                    ) : null}

                    {data.body ? <p className="section-lead">{data.body}</p> : null}

                    <ActionButton cta={data.cta} actions={actions} className="btn ghost" />
                </div>

                <div className="text-image__media">
                    {image.src ? (
                        <img src={image.src} alt={image.alt || ''} loading="lazy" decoding="async" />
                    ) : null}
                </div>
            </div>
        </section>
    );
}
