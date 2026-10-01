import { spacingClasses } from './spacing';
import { breakpointClasses } from './responsive';
import { autoColumnSpan, isFiveEqual } from './ColumnContainer';
import { backdropClasses, BackdropLayers } from './Backdrop';
import { customCssOf } from './customCss';
import useReveal from './useReveal';
import { revealScope } from './Reveal';

const GAPS = { none: 'row-container--gap-none', small: 'row-container--gap-small', medium: '', large: 'row-container--gap-large', xlarge: 'row-container--gap-xlarge' };

export default function RowContainer({ data = {}, anchor, blockId, hideClasses = '', childBlocks = [], editing = false, children }) {
    const reveal = useReveal(data.animation, data.animationDelay, editing, revealScope('row', data));
    const classes = [
        'row-container',
        hideClasses,
        reveal.classes,
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
        <div ref={reveal.ref} className={classes} id={anchor} data-cms-block={customCssOf(data) ? blockId : undefined} data-animate={reveal.animation || undefined} style={{ '--auto-span': autoColumnSpan(childBlocks) }}>
            <BackdropLayers data={data} />
            {children}
        </div>
    );
}
