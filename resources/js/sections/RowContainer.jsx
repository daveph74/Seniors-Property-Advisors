import { spacingClasses } from './spacing';
import { breakpointClasses } from './responsive';
import { autoColumnSpan, isFiveEqual } from './ColumnContainer';
import { backdropClasses, BackdropLayers } from './Backdrop';
import { customCssOf } from './customCss';

const GAPS = { none: 'row-container--gap-none', small: 'row-container--gap-small', medium: '', large: 'row-container--gap-large', xlarge: 'row-container--gap-xlarge' };

export default function RowContainer({ data = {}, anchor, blockId, hideClasses = '', childBlocks = [], children }) {
    const classes = [
        'row-container',
        hideClasses,
        isFiveEqual(childBlocks) ? 'row-container--five' : '',
        data.stack === 'never' ? 'row-container--no-stack' : '',
        GAPS[data.gap] || '',
        spacingClasses(data),
        breakpointClasses(data, 'gap', (v) => (v in GAPS ? `row-container--gap-${v}` : '')),
        backdropClasses(data),
    ]
        .filter(Boolean)
        .join(' ');

    return (
        <div className={classes} id={anchor} data-cms-block={customCssOf(data) ? blockId : undefined} style={{ '--auto-span': autoColumnSpan(childBlocks) }}>
            <BackdropLayers data={data} />
            {children}
        </div>
    );
}
