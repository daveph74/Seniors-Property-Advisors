import SectionHead from './SectionHead';

export default function StatRowSection({ data, anchor }) {
    const stats = data.stats || [];

    return (
        <section className="stat-row" id={anchor}>
            <div className="container">
                <SectionHead {...data} />

                {stats.length > 0 ? (
                    <dl className="stat-row__grid">
                        {stats.map((s, i) => (
                            <div className="stat-row__item" key={i}>
                                <dt className="stat-row__value">{s.value}</dt>
                                <dd className="stat-row__label">{s.label}</dd>
                                {s.note ? <dd className="stat-row__note">{s.note}</dd> : null}
                            </div>
                        ))}
                    </dl>
                ) : null}
            </div>
        </section>
    );
}
