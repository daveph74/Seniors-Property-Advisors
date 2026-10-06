import { useState } from 'react';
import PendingModule from './PendingModule';
import { lookClass } from './headingLevel';

export default function FaqListSection({ data, anchor, library = {}, editing = false }) {
    const limit = Number(data.limit) > 0 ? Number(data.limit) : null;
    const anchorsPage = Boolean(data.heading || data.headingEm);
    const [chosen, setChosen] = useState(null);

    const pulled = (library.faqs || [])
        .filter((f) => ! data.category || f.category === data.category)
        .slice(0, limit || undefined);

    const items = pulled.length > 0
        ? pulled
        : (data.items || []).filter((f) => f && (f.question || f.answer));

    const groups = data.showFilters === false || data.category || pulled.length === 0
        ? []
        : (library.faqCategories || []).filter(
            (name) => pulled.some((f) => f.category === name),
        );

    const shown = chosen ? items.filter((f) => f.category === chosen) : items;

    const railed = ! editing && groups.length > 1;

    if (items.length === 0 && ! editing && ! anchorsPage) return null;

    const hasIntro = data.eyebrow || data.heading || data.headingEm || data.lead;

    const count = (name) => items.filter((f) => f.category === name).length;

    const railButton = (name, label, total) => (
        <button
            key={name ?? 'all'}
            type="button"
            className={`filter-chip ${chosen === name ? 'filter-chip--on' : ''}`}
            onClick={() => setChosen(name)}
        >
            {label}
            <span className="faq-rail__count">{total}</span>
        </button>
    );

    return (
        <section className="faq-list" id={anchor}>
            <div className="container">
                {hasIntro ? (
                    <div className="section-head center">
                        <div className="left">
                            {data.eyebrow ? <div className="eyebrow-line">{data.eyebrow}</div> : null}

                            {data.heading || data.headingEm ? (
                                <h2 className={`block-heading block-heading--large ${lookClass(data.titleLook)}`.trim()}>
                                    {data.heading}
                                    {data.headingEm ? <> <em>{data.headingEm}</em></> : null}
                                </h2>
                            ) : null}

                            {data.lead ? <p className="section-lead">{data.lead}</p> : null}
                        </div>
                    </div>
                ) : null}

                {items.length === 0 && ! editing ? null : items.length === 0 ? (
                    <PendingModule
                        title="FAQs"
                        waitingFor="FAQ library"
                        willPull={[
                            data.category ? `Category: ${data.category}` : 'All categories',
                            limit ? `Showing up to ${limit}` : 'Showing all that match',
                            'Only active questions, in the order set in the library',
                            'Plus any question assigned to this page',
                            data.showFilters === false || data.category
                                ? 'Without category filters'
                                : 'With category filters, once two groups have questions',
                        ]}
                    />
                ) : (
                    <div className={railed ? 'faq-list__grid' : undefined}>
                        {railed ? (
                            <nav className="faq-rail" aria-label="Question categories">
                                <h2 className="faq-rail__title">Browse by topic</h2>
                                {railButton(null, 'All questions', items.length)}
                                {groups.map((name) => railButton(name, name, count(name)))}
                            </nav>
                        ) : null}

                        <div className="faq-list__items">
                            {shown.map((f, i) => (
                                <details
                                    className="faq"
                                    key={f.id ?? i}
                                    open={i === 0 && data.openFirst !== false}
                                >
                                    <summary className="faq__q">
                                        {f.question}
                                        <span className="faq__mark" aria-hidden="true" />
                                    </summary>
                                    <div className="faq__a">{f.answer}</div>
                                </details>
                            ))}
                        </div>
                    </div>
                )}
            </div>
        </section>
    );
}
