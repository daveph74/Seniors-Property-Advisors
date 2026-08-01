import SectionHead from './SectionHead';
import PendingModule from './PendingModule';

export default function BlogListSection({ data, anchor, editing = false }) {
    const articles = (data.articles || []).filter((a) => a && (a.title || a.summary || a.image));
    const limit = Number(data.limit) > 0 ? Number(data.limit) : null;

    if (articles.length === 0 && ! editing) return null;

    return (
        <section className="blog-list" id={anchor}>
            <div className="container">
                <SectionHead {...data} />

                {articles.length === 0 ? (
                    <PendingModule
                        title="Blog articles"
                        waitingFor="blog module"
                        willPull={[
                            data.category ? `Category: ${data.category}` : 'All categories',
                            limit ? `Showing the ${limit} most recent` : 'Showing the most recent articles',
                            'Each card shows the featured image, published date and summary',
                            data.showMore !== false ? 'With a load-more control' : 'Without a load-more control',
                        ]}
                    />
                ) : (
                    <div className="blog-list__grid">
                        {articles.map((a, i) => (
                            <article className="article-card" key={i}>
                                {a.image ? (
                                    <img
                                        className="article-card__image"
                                        src={a.image}
                                        alt={a.title || ''}
                                        loading="lazy"
                                        decoding="async"
                                    />
                                ) : null}
                                <div className="article-card__body">
                                    {a.category ? <span className="article-card__tag">{a.category}</span> : null}
                                    <h3 className="article-card__title">{a.title}</h3>
                                    {a.date ? <time className="article-card__date">{a.date}</time> : null}
                                    <p className="article-card__summary">{a.summary}</p>
                                </div>
                            </article>
                        ))}
                    </div>
                )}
            </div>
        </section>
    );
}
