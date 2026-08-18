import { spacingClasses } from './spacing';
import { StarIcon } from '../components/icons';

export default function InfoCardBlock({ data, anchor }) {
    if (!data.title && !data.value) return null;

    const saving = data.cardStyle === 'saving';
    const classes = [
        'block-info-card',
        saving ? 'block-info-card--saving' : '',
        spacingClasses(data),
    ].filter(Boolean).join(' ');

    if (saving) {
        return (
            <div id={anchor} className={classes}>
                <b>{data.title}</b>
                <span className="big">{data.value}</span>
                <small>{data.note}</small>
            </div>
        );
    }

    return (
        <div id={anchor} className={classes}>
            <div className="ico">
                <StarIcon />
            </div>
            <div>
                <b>{data.title}</b>
                <small>{data.note}</small>
            </div>
        </div>
    );
}
