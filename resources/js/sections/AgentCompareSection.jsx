import ActionButton from './ActionButton';
import { useHeadingLevel } from './headingLevel';

export default function AgentCompareSection({ data, actions, anchor }) {
    const Heading = `h${useHeadingLevel()}`;
    const agents = data.agents || [];
    const labels = data.labels || {};

    const rows = [
        {
            label: labels.experience,
            cell: (a) => (
                <>
                    <strong>{(a.experience || {}).strong}</strong> {(a.experience || {}).rest}
                    <div className="meter">
                        <i style={{ width: `${(a.experience || {}).meter || 0}%` }} />
                    </div>
                </>
            ),
        },
        {
            label: labels.sales,
            cell: (a) => (
                <>
                    <strong>{(a.sales || {}).strong}</strong>
                    <br />
                    <span className="sub">{(a.sales || {}).sub}</span>
                </>
            ),
        },
        {
            label: labels.commission,
            cell: (a) => (
                <>
                    <span className="price">{(a.commission || {}).price}</span>
                    <div className="sub">{(a.commission || {}).sub}</div>
                </>
            ),
        },
        {
            label: labels.marketing,
            cell: (a) => (
                <>
                    <strong>{(a.marketing || {}).strong}</strong>
                    <div className="sub">{(a.marketing || {}).sub}</div>
                </>
            ),
        },
        { label: labels.notes, cell: (a) => a.note, modifier: 'note' },
        {
            label: labels.next,
            modifier: 'cta',
            cell: (a) => (
                <ActionButton
                    cta={a.cta}
                    actions={actions}
                    tight
                    className={`btn ${(a.cta || {}).variant} sm block`}
                />
            ),
        },
    ];

    const agentHead = (a) => (
        <div className="agent-head">
            <span className="av" style={{ backgroundImage: `url('${a.avatar}')` }} />
            <b>{a.name}</b>
            <small>{a.firm}</small>
        </div>
    );

    return (
        <section className="compare" id={anchor}>
            <div className="container">
                <div className="section-head">
                    <div className="left">
                        <div className="eyebrow-line">{data.eyebrow}</div>
                        <Heading className="section-head__title">
                            {data.heading} <em>{data.headingEm}</em>
                        </Heading>
                        <p className="section-lead">{data.lead}</p>
                    </div>
                    <ActionButton cta={data.cta} actions={actions} className="btn ghost" />
                </div>

                <div className="compare-shell">
                    <div className="compare-toolbar">
                        <div className="left">
                            {(data.filters || []).map((f, i) => (
                                <span
                                    key={i}
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
                        {agents.map((a, i) => (
                            <div key={i} className={`cmp-cell head${a.best ? ' best' : ''}`}>
                                {agentHead(a)}
                            </div>
                        ))}

                        {rows.map((r, ri) => (
                            <div key={ri} style={{ display: 'contents' }}>
                                <div className="cmp-cell label">{r.label}</div>
                                {agents.map((a, i) => (
                                    <div key={i} className={`cmp-cell${r.modifier ? ` ${r.modifier}` : ''}`}>
                                        {r.cell(a)}
                                    </div>
                                ))}
                            </div>
                        ))}
                    </div>

                    <div className="compare-cards">
                        <h3 className="cmp-cards__title">{labels.shortlist}</h3>

                        {agents.map((a, i) => (
                            <article key={i} className={`cmp-card${a.best ? ' best' : ''}`}>
                                <div className="cmp-card__head">{agentHead(a)}</div>

                                <dl className="cmp-card__rows">
                                    {rows.map((r, ri) => (
                                        <div key={ri} className={`cmp-row${r.modifier ? ` ${r.modifier}` : ''}`}>
                                            <dt className="cmp-row__label">{r.label}</dt>
                                            <dd className="cmp-row__value">{r.cell(a)}</dd>
                                        </div>
                                    ))}
                                </dl>
                            </article>
                        ))}
                    </div>
                </div>
            </div>
        </section>
    );
}
