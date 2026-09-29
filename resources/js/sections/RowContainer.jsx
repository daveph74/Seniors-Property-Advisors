import { spacingClasses } from './spacing';

const GAPS = { none: 'row-container--gap-none', small: 'row-container--gap-small', medium: '', large: 'row-container--gap-large', xlarge: 'row-container--gap-xlarge' };

export default function RowContainer({ data = {}, anchor, children }) {
    const classes = ['row-container', data.stack === 'never' ? 'row-container--no-stack' : '', GAPS[data.gap] || '', spacingClasses(data)]
        .filter(Boolean)
        .join(' ');

    return (
        <div className={classes} id={anchor}>
            {children}
        </div>
    );
}
