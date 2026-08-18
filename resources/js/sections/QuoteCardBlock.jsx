import { spacingClasses } from './spacing';

export default function QuoteCardBlock({ data, anchor }) {
    if (!data.quote) return null;

    return (
        <div id={anchor} className={`block-quote-card ${spacingClasses(data)}`.trim()}>
            {data.avatar ? <span className="av" style={{ backgroundImage: `url('${data.avatar}')` }} /> : null}
            <div>
                <p>{data.quote}</p>
                {data.by ? <small>{data.by}</small> : null}
            </div>
        </div>
    );
}
