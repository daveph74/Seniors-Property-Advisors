export default function SectionHead({ eyebrow, heading, headingEm, lead, centred = true }) {
    if (! eyebrow && ! heading && ! headingEm && ! lead) return null;

    return (
        <div className={`section-head${centred ? ' center' : ''}`}>
            <div className="left">
                {eyebrow ? <div className="eyebrow-line">{eyebrow}</div> : null}

                {heading || headingEm ? (
                    <h2>
                        {heading} {headingEm ? <em>{headingEm}</em> : null}
                    </h2>
                ) : null}

                {lead ? <p className="section-lead">{lead}</p> : null}
            </div>
        </div>
    );
}
