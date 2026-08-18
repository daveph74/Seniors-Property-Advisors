import { spacingClasses } from './spacing';

const ALIGN = { left: '', center: 'block-rating--center', right: 'block-rating--right' };

export default function RatingStarsBlock({ data, anchor }) {
    if (!data.stars && !data.ratingLabel) return null;

    return (
        <div
            id={anchor}
            className={`block-rating ${ALIGN[data.align] || ''} ${spacingClasses(data)}`.replace(/ +/g, ' ').trim()}
        >
            <div className="row">
                <span className="stars">{data.stars}</span>
                <b>{data.ratingLabel}</b>
            </div>
            {data.note ? <small>{data.note}</small> : null}
        </div>
    );
}
