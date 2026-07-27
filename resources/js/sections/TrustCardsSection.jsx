import ActionButton from './ActionButton';
import { ICON_MAP } from './iconMap';

export default function TrustCardsSection({ data, actions, anchor }) {
    return (
        <section className="trust" id={anchor}>
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

                <div className="cards">
                    {(data.items || []).map((c) => {
                        const Icon = ICON_MAP[c.icon];
                        return (
                            <div className="card" key={c.title}>
                                <div className="ico">{Icon ? <Icon /> : null}</div>
                                <h3>{c.title}</h3>
                                <p>{c.body}</p>
                            </div>
                        );
                    })}
                </div>
            </div>
        </section>
    );
}
