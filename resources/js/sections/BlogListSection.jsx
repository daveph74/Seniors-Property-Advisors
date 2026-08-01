import { useEffect, useState } from 'react';
import SectionHead from './SectionHead';
import PendingModule from './PendingModule';

export default function BlogListSection({ data, anchor, library = {}, editing = false }) {
    const limit = Number(data.limit) > 0 ? Number(data.limit) : null;
    const step = limit ?? 12;

    const [fetched, setFetched] = useState([]);
    const [page, setPage] = useState(library.page || 1);
    const [serverHasMore, setServerHasMore] = useState(Boolean(library.hasMorePosts));
    const [revealed, setRevealed] = useState(step);
    const [loading, setLoading] = useState(false);

    useEffect(() => {
        setFetched([]);
        setPage(library.page || 1);
        setServerHasMore(Boolean(library.hasMorePosts));
        setRevealed(step);
    }, [library.posts, library.page, library.hasMorePosts, step]);

    const matches = (article) => ! data.category
        || article.category === data.category
        || (article.categories || []).includes(data.category);

    const pulled = [...(library.posts || []), ...fetched].filter(matches);

    const articles = pulled.length > 0
        ? pulled
        : (data.articles || []).filter((a) => a && (a.title || a.summary || a.image));

    const pulling = pulled.length > 0;
    const shown = pulling ? articles.slice(0, revealed) : (limit ? articles.slice(0, limit) : articles);
    const canLoadMore = pulling
        && data.showMore !== false
        && ! editing
        && (revealed < articles.length || serverHasMore);

    /**
     * Reveal what is already here first, and only ask the server once it runs out — the
     * page ships one capped batch, not every article.
     */
    const loadMore = () => {
        if (revealed < articles.length) {
            setRevealed(revealed + step);

            return;
        }

        setLoading(true);

        fetch(`/blog/articles?page=${page + 1}`, { headers: { Accept: 'application/json' } })
            .then((r) => r.json())
            .then((batch) => {
                setFetched((held) => [...held, ...(batch.posts || [])]);
                setPage(batch.page || page + 1);
                setServerHasMore(Boolean(batch.hasMorePosts));
                setRevealed(revealed + step);
            })
            .catch(() => setServerHasMore(false))
            .finally(() => setLoading(false));
    };

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
                    <>
                        <div className="blog-list__grid">
                            {shown.map((a, i) => {
                                const card = (
                                    <>
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
                                    </>
                                );

                                return a.url && ! editing
                                    ? <a className="article-card" href={a.url} key={a.slug ?? i}>{card}</a>
                                    : <article className="article-card" key={a.slug ?? i}>{card}</article>;
                            })}
                        </div>

                        {canLoadMore ? (
                            <div className="blog-list__more">
                                <button type="button" className="btn ghost" onClick={loadMore} disabled={loading}>
                                    {loading ? 'Loading…' : 'Load more articles'}
                                </button>
                            </div>
                        ) : null}
                    </>
                )}
            </div>
        </section>
    );
}
