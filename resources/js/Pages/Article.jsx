import { useState } from 'react';
import { Head } from '@inertiajs/react';
import FindMyAgentModal from '../components/FindMyAgentModal';
import PreviewBanner from '../components/PreviewBanner';
import SiteHeader from '../sections/SiteHeader';
import SiteFooter from '../sections/SiteFooter';

export default function Article({ article, related = [], globals = {}, preview = null }) {
    const [modalOpen, setModalOpen] = useState(false);
    const actions = { 'open-finder': () => setModalOpen(true) };

    const seo = article.seo || {};
    const title = seo.title || article.title;
    const description = seo.description || article.summary;
    const image = seo.image || article.image;

    return (
        <>
            <Head title={title}>
                {description && <meta name="description" content={description} />}
                <meta property="og:type" content="article" />
                <meta property="og:title" content={title} />
                {description && <meta property="og:description" content={description} />}
                {image && <meta property="og:image" content={image} />}
                {preview && <meta name="robots" content="noindex" />}
            </Head>

            {preview && <PreviewBanner {...preview} />}

            <SiteHeader globals={globals} actions={actions} />

            <article className="article">
                <div className="container article__inner">
                    <header className="article__head">
                        {article.categories?.length ? (
                            <div className="article__tags">
                                {article.categories.map((c) => <span className="article-card__tag" key={c}>{c}</span>)}
                            </div>
                        ) : null}

                        <h1 className="article__title">{article.title}</h1>

                        <p className="article__byline">
                            {article.author ? <span>{article.author}</span> : null}
                            {article.date ? <time>{article.date}</time> : null}
                        </p>
                    </header>

                    {article.image ? (
                        <img className="article__hero" src={article.image} alt="" width="1200" height="675" />
                    ) : null}

                    {article.summary ? <p className="article__lead">{article.summary}</p> : null}

                    <div className="prose" dangerouslySetInnerHTML={{ __html: article.body }} />
                </div>

                {related.length > 0 ? (
                    <section className="blog-list article__related">
                        <div className="container">
                            <h2 className="article__related-title">More like this</h2>

                            <div className="blog-list__grid">
                                {related.map((r) => (
                                    <a className="article-card" href={r.url} key={r.slug}>
                                        {r.image ? (
                                            <img
                                                className="article-card__image"
                                                src={r.image}
                                                alt=""
                                                loading="lazy"
                                                decoding="async"
                                            />
                                        ) : null}
                                        <div className="article-card__body">
                                            {r.category ? <span className="article-card__tag">{r.category}</span> : null}
                                            <h3 className="article-card__title">{r.title}</h3>
                                            {r.date ? <time className="article-card__date">{r.date}</time> : null}
                                            <p className="article-card__summary">{r.summary}</p>
                                        </div>
                                    </a>
                                ))}
                            </div>
                        </div>
                    </section>
                ) : null}
            </article>

            <SiteFooter globals={globals} />

            <FindMyAgentModal open={modalOpen} onClose={() => setModalOpen(false)} />
        </>
    );
}
