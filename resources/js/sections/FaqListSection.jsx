import SectionHead from './SectionHead';
import PendingModule from './PendingModule';

export default function FaqListSection({ data, anchor, library = {}, editing = false }) {
    const limit = Number(data.limit) > 0 ? Number(data.limit) : null;

    const pulled = (library.faqs || [])
        .filter((f) => ! data.category || f.category === data.category)
        .slice(0, limit || undefined);

    const items = pulled.length > 0
        ? pulled
        : (data.items || []).filter((f) => f && (f.question || f.answer));

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
                        ]}
                    />
                ) : (
                    <div className="faq-list__items">
                        {items.map((f, i) => (
                            <details className="faq" key={f.id ?? i} open={i === 0 && data.openFirst !== false}>
                                <summary className="faq__q">
                                    {f.question}
                                    <span className="faq__mark" aria-hidden="true" />
                                </summary>
                                <div className="faq__a">{f.answer}</div>
                            </details>
                        ))}
                    </div>
                )}
            </div>
        </section>
    );
}
