import { useEffect, useState } from 'react';
import SectionHead from './SectionHead';
import SiteLink from './SiteLink';
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

    /**
     * The section can be pinned to one category by an editor; a reader's own choice arrives
     * already filtered from the server, so it needs no second pass here.
     */
    const matches = (article) => ! data.category
        || article.category === data.category
        || (article.categories || []).includes(data.category);

    const chosen = library.postCategory || null;
    const filters = data.showFilters === false || data.category
        ? []
        : (library.postCategories || []);

    const filterHref = (slug) => {
        if (typeof window === 'undefined') return slug ? `?category=${slug}` : '?';

        const url = new URL(window.location.href);

        if (slug) {
            url.searchParams.set('category', slug);
        } else {
            url.searchParams.delete('category');
        }

        return `${url.pathname}${url.search}`;
    };

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

        const next = new URLSearchParams({ page: String(page + 1) });

        if (chosen) next.set('category', chosen);

        fetch(`/blog/articles?${next}`, { headers: { Accept: 'application/json' } })
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

    /**
     * A chosen filter keeps the section on the page even when it matches nothing, or a reader
     * would land on an empty page with no way back to the other categories.
     */
    if (articles.length === 0 && ! editing && ! chosen) return null;

    const chips = filters.length > 1 ? (
        <div className="filter-chips">
            <SiteLink className={`filter-chip ${chosen ? '' : 'filter-chip--on'}`} href={filterHref(null)}>
                All articles
            </SiteLink>
            {filters.map((c) => (
                <SiteLink
                    key={c.slug}
                    className={`filter-chip ${chosen === c.slug ? 'filter-chip--on' : ''}`}
                    href={filterHref(c.slug)}
                >
                    {c.name}
                </SiteLink>
            ))}
        </div>
    ) : null;

    return (
        <section className="blog-list" id={anchor}>
            <div className="container">
                <SectionHead {...data} />

                {editing ? null : chips}

                {articles.length === 0 && chosen ? (
                    <p className="blog-list__none">
                        Nothing in that category yet. <SiteLink href={filterHref(null)}>See all articles</SiteLink>.
                    </p>
                ) : articles.length === 0 ? (
                    <PendingModule
                        title="Blog articles"
                        waitingFor="blog module"
                        willPull={[
                            data.category ? `Category: ${data.category}` : 'All categories',
                            limit ? `Showing the ${limit} most recent` : 'Showing the most recent articles',
                            'Each card shows the featured image, published date and summary',
                            data.showMore !== false ? 'With a load-more control' : 'Without a load-more control',
                            data.showFilters === false || data.category
                                ? 'Without category filters'
                                : 'With category filters, once two categories have articles',
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
                                                alt={a.imageAlt || ''}
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
                                    ? <SiteLink className="article-card" href={a.url} key={a.slug ?? i}>{card}</SiteLink>
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
