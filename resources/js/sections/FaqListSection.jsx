import { useState } from 'react';
import SectionHead from './SectionHead';
import PendingModule from './PendingModule';

export default function FaqListSection({ data, anchor, library = {}, editing = false }) {
    const limit = Number(data.limit) > 0 ? Number(data.limit) : null;
    const [chosen, setChosen] = useState(null);

    const pulled = (library.faqs || [])
        .filter((f) => ! data.category || f.category === data.category)
        .slice(0, limit || undefined);

    const items = pulled.length > 0
        ? pulled
        : (data.items || []).filter((f) => f && (f.question || f.answer));

    /*
     * Filtered in the browser, unlike the blog's chips. Every question a reader can see is
     * already in the payload — there is no paging to get wrong — so switching groups is instant
     * and needs no request. Pinning the section to one category from the builder removes the
     * choice, since there would be nothing to choose between.
     */
    const groups = data.showFilters === false || data.category || pulled.length === 0
        ? []
        : (library.faqCategories || []).filter(
            (name) => pulled.some((f) => f.category === name),
        );

    const shown = chosen ? items.filter((f) => f.category === chosen) : items;

    if (items.length === 0 && ! editing) return null;

    return (
        <section className="faq-list" id={anchor}>
            <div className="container">
                <SectionHead {...data} />

                {items.length === 0 ? (
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
                    <>
                        {! editing && groups.length > 1 ? (
                            <div className="filter-chips">
                                <button
                                    type="button"
                                    className={`filter-chip ${chosen ? '' : 'filter-chip--on'}`}
                                    onClick={() => setChosen(null)}
                                >
                                    All questions
                                </button>
                                {groups.map((name) => (
                                    <button
                                        key={name}
                                        type="button"
                                        className={`filter-chip ${chosen === name ? 'filter-chip--on' : ''}`}
                                        onClick={() => setChosen(name)}
                                    >
                                        {name}
                                    </button>
                                ))}
                            </div>
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
                    </>
                )}
            </div>
        </section>
    );
}
