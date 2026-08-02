import { useHeadingLevel } from './headingLevel';

export default function SectionHead({ eyebrow, heading, headingEm, lead, centred = true }) {
    const Heading = `h${useHeadingLevel()}`;

    if (! eyebrow && ! heading && ! headingEm && ! lead) return null;

    return (
        <div className={`section-head${centred ? ' center' : ''}`}>
            <div className="left">
                {eyebrow ? <div className="eyebrow-line">{eyebrow}</div> : null}

                {heading || headingEm ? (
                    <Heading className="section-head__title">
                        {heading} {headingEm ? <em>{headingEm}</em> : null}
                    </Heading>
                ) : null}

                {lead ? <p className="section-lead">{lead}</p> : null}
            </div>
        </div>
    );
}
