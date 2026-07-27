import ActionButton from './ActionButton';

export default function AgentCompareSection({ data, actions, anchor }) {
    const agents = data.agents || [];
    const labels = data.labels || {};

    const row = (label, render) => (
        <>
            <div className="cmp-cell label">{label}</div>
            {agents.map((a) => render(a))}
        </>
    );

    return (
        <section className="compare" id={anchor}>
            <div className="container">
                <div className="section-head">
                    <div className="left">
                        <div className="eyebrow-line">{data.eyebrow}</div>
                        <h2>
                            {data.heading} <em>{data.headingEm}</em>
                        </h2>
                        <p className="section-lead">{data.lead}</p>
                    </div>
                    <ActionButton cta={data.cta} actions={actions} className="btn ghost" />
                </div>

                <div className="compare-shell">
                    <div className="compare-toolbar">
                        <div className="left">
                            {(data.filters || []).map((f) => (
                                <span
                                    key={f.label}
                                    className={`chip${f.active ? ' active' : ''}${f.count ? ' count' : ''}`}
                                >
                                    {f.label}
                                    {f.removable && <span className="x">×</span>}
                                </span>
                            ))}
                        </div>
                        <div className="right">
                            <button type="button" className="chip btnlike">{data.sort}</button>
                        </div>
                    </div>

                    <div className="compare-grid">
                        <div className="cmp-cell first-label">{labels.shortlist}</div>
                        {agents.map((a) => (
                            <div key={a.name} className={`cmp-cell head${a.best ? ' best' : ''}`}>
                                <div className="agent-head">
                                    <span
                                        className="av"
                                        style={{ backgroundImage: `url('${a.avatar}')` }}
                                    />
                                    <b>{a.name}</b>
                                    <small>{a.firm}</small>
                                </div>
                            </div>
                        ))}

                        {row(labels.experience, (a) => (
                            <div className="cmp-cell" key={a.name}>
                                <strong>{a.experience.strong}</strong> {a.experience.rest}
                                <div className="meter">
                                    <i style={{ width: `${a.experience.meter}%` }} />
                                </div>
                            </div>
                        ))}

                        {row(labels.sales, (a) => (
                            <div className="cmp-cell" key={a.name}>
                                <strong>{a.sales.strong}</strong>
                                <br />
                                <span className="sub">{a.sales.sub}</span>
                            </div>
                        ))}

                        {row(labels.commission, (a) => (
                            <div className="cmp-cell" key={a.name}>
                                <span className="price">{a.commission.price}</span>
                                <div className="sub">{a.commission.sub}</div>
                            </div>
                        ))}

                        {row(labels.marketing, (a) => (
                            <div className="cmp-cell" key={a.name}>
                                <strong>{a.marketing.strong}</strong>
                                <div className="sub">{a.marketing.sub}</div>
                            </div>
                        ))}

                        {row(labels.notes, (a) => (
                            <div className="cmp-cell note" key={a.name}>
                                {a.note}
                            </div>
                        ))}

                        {row(labels.next, (a) => (
                            <div className="cmp-cell cta" key={a.name}>
                                <ActionButton
                                    cta={a.cta}
                                    actions={actions}
                                    tight
                                    className={`btn ${a.cta.variant} sm block`}
                                />
                            </div>
                        ))}
                    </div>
                </div>
            </div>
        </section>
    );
}
