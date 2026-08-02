import { useEffect, useState } from 'react';
import { Head } from '@inertiajs/react';
import FindMyAgentModal from '../components/FindMyAgentModal';
import PreviewBanner from '../components/PreviewBanner';
import SiteHeader from '../sections/SiteHeader';
import SiteLink from '../sections/SiteLink';
import SiteFooter from '../sections/SiteFooter';
import { list as recentlyRead, remember } from '../recentlyRead';

function RailList({ title, articles }) {
    if (articles.length === 0) return null;

    return (
        <section className="article-rail__block">
            <h2 className="article-rail__title">{title}</h2>

            <ul className="article-rail__list">
                {articles.map((a) => (
                    <li key={a.slug}>
                        <SiteLink className="article-rail__item" href={a.url}>
                            {a.image
                                ? <img className="article-rail__thumb" src={a.image} alt="" loading="lazy" decoding="async" />
                                : <span className="article-rail__thumb article-rail__thumb--blank" />}
                            <span className="article-rail__text">
                                <span className="article-rail__name">{a.title}</span>
                                {a.date ? <time className="article-rail__date">{a.date}</time> : null}
                            </span>
                        </SiteLink>
                    </li>
                ))}
            </ul>
        </section>
    );
}

export default function Article({ article, seo = {}, related = [], globals = {}, preview = null }) {
    const [modalOpen, setModalOpen] = useState(false);
    const actions = { 'open-finder': () => setModalOpen(true) };

    /**
     * Read before remembering, or this article turns up in its own "you were reading" list.
     * An initialiser runs once, before the effect below writes to storage.
     */
    const [recent] = useState(() => recentlyRead(article.slug));

    useEffect(() => {
        if (preview) return;

        remember(article);
    }, [article, preview]);

    const title = seo.title || article.title;
    const description = seo.description || article.summary;
    const hasRail = related.length > 0 || recent.length > 0;

    return (
        <>
            {/* The rest of the head, and the structured data, are printed by the server — see
                AgentFinder for why. */}
            <Head title={title} />

            {preview && <PreviewBanner {...preview} />}

            <SiteHeader globals={globals} actions={actions} />

            <div className="article">
                <div className={`container ${hasRail ? 'article__grid' : ''}`}>
                    <article className="article__body">
                        <header className="article__head">
                            {article.categories?.length ? (
                                <div className="article__tags">
                                    {article.categories.map((c) => (
                                        <span className="article-card__tag" key={c}>{c}</span>
                                    ))}
                                </div>
                            ) : null}

                            <h1 className="article__title">{article.title}</h1>

                            <p className="article__byline">
                                {article.author ? <span>{article.author}</span> : null}
                                {article.date ? <time>{article.date}</time> : null}
                            </p>
                        </header>

                        {article.image ? (
                            <img
                                className="article__hero"
                                src={article.image}
                                alt={article.imageAlt || ''}
                                width="1200"
                                height="675"
                            />
                        ) : null}

                        {article.summary ? <p className="article__lead">{article.summary}</p> : null}

                        <div className="prose" dangerouslySetInnerHTML={{ __html: article.body }} />
                    </article>

                    {hasRail ? (
                        <aside className="article__rail">
                            <RailList title="More like this" articles={related} />
                            <RailList title="You were reading" articles={recent} />
                        </aside>
                    ) : null}
                </div>
            </div>

            <SiteFooter globals={globals} />

            <FindMyAgentModal open={modalOpen} onClose={() => setModalOpen(false)} />
        </>
    );
}
