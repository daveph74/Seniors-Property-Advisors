import { useEffect, useState } from 'react';
import SectionHead from './SectionHead';
import SiteLink from './SiteLink';
import PendingModule from './PendingModule';
import { useHeadingLevel } from './headingLevel';

/* The article list is the only thing a category changes. `errors` rides along because Inertia
   carries validation state in it and a partial reload that omits it drops whatever was there. */
const FILTER_PROPS = ['library', 'errors'];

export default function BlogListSection({ data, anchor, library = {}, editing = false }) {
    /* One below whatever the section's own heading is, so the listing does not skip a level on a
       page where it opens the page and its heading is the h1. */
    const level = useHeadingLevel();
    const CardTitle = `h${level + 1}`;
    /* Level 1 means ownerOfTheH1() nominated this section, so it is what the page is for. */
    const leadsPage = level === 1;
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
     *
     * The same holds when the section *is* the page: disappearing takes the heading and intro
     * with it, leaving /blog as a call to action under no title and with no h1 at all. Nothing
     * to list is a normal state before anyone has written — it should read as an empty page,
     * not a broken one.
     */
    if (articles.length === 0 && ! editing && ! chosen && ! leadsPage) return null;

    /**
     * A category is answered by the server — it is the only way the filter can reach articles on
     * later pages, and it keeps a filtered listing at an address a reader can send to somebody.
     * But it is a change to one section, not a move to another page, so it asks for one prop and
     * leaves the reader where they were.
     *
     * Without `only` the whole page came back to change the article list: sections, menus and
     * settings the filter cannot affect, about two thirds of the payload. Without preserveScroll
     * a reader choosing a category partway down a page was thrown to the top of it.
     */
    const chipVisit = { preserveScroll: true, only: FILTER_PROPS };

    const chips = filters.length > 1 ? (
        <div className="filter-chips">
            <SiteLink className={`filter-chip ${chosen ? '' : 'filter-chip--on'}`} href={filterHref(null)} {...chipVisit}>
                All articles
            </SiteLink>
            {filters.map((c) => (
                <SiteLink
                    key={c.slug}
                    className={`filter-chip ${chosen === c.slug ? 'filter-chip--on' : ''}`}
                    href={filterHref(c.slug)}
                    {...chipVisit}
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
                        Nothing in that category yet. <SiteLink href={filterHref(null)} {...chipVisit}>See all articles</SiteLink>.
                    </p>
                ) : articles.length === 0 && ! editing ? null : articles.length === 0 ? (
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
                                            <CardTitle className="article-card__title">{a.title}</CardTitle>
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
