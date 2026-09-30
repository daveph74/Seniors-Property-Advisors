import useReveal from './useReveal';
import { breakpointClasses } from './responsive';
import { backdropClasses, BackdropLayers } from './Backdrop';
import { customCssOf } from './customCss';

const ACROSS = {
    fill: '',
    left: 'column-container--across-left',
    center: 'column-container--across-center',
    right: 'column-container--across-right',
};

const DOWN = {
    top: '',
    middle: 'column-container--down-middle',
    bottom: 'column-container--down-bottom',
    spread: 'column-container--down-spread',
};

const ORDERS = ['1', '2', '3', '4', '5', '6'];

export const COLUMN_WIDTHS = ['quarter', 'third', 'half', 'two-thirds', 'three-quarters', 'full'];

const SPANS = { quarter: 3, third: 4, half: 6, 'two-thirds': 8, 'three-quarters': 9, full: 12 };

export function autoColumnSpan(columns = []) {
    const widths = columns.map((c) => c?.data?.width).filter((w) => COLUMN_WIDTHS.includes(w));
    const autos = columns.length - widths.length;

    if (autos <= 0) return 12;

    const left = 12 - widths.reduce((sum, w) => sum + SPANS[w], 0);

    return left < autos ? 12 : Math.floor(left / autos);
}

export function isFiveEqual(columns = []) {
    return columns.length === 5 && ! columns.some((c) => COLUMN_WIDTHS.includes(c?.data?.width));
}

export function columnWidthClasses(data = {}) {
    return [
        COLUMN_WIDTHS.includes(data.width) ? `col-w-${data.width}` : '',
        breakpointClasses(data, 'width', (v) => (COLUMN_WIDTHS.includes(v) ? `col-w-${v}` : '')),
    ].filter(Boolean).join(' ');
}

export default function ColumnContainer({ data = {}, anchor, blockId, hideClasses = '', editing = false, children }) {
    const reveal = useReveal(data.animation, data.animationDelay, editing);

    const classes = [
        'column-container',
        hideClasses,
        ACROSS[data.alignAcross] || '',
        DOWN[data.alignDown] || '',
        reveal.classes,
        breakpointClasses(data, 'alignAcross', (v) => (v in ACROSS ? `column-container--across-${v}` : '')),
        breakpointClasses(data, 'alignDown', (v) => (v in DOWN ? `column-container--down-${v}` : '')),
        breakpointClasses(data, 'order', (v) => (ORDERS.includes(String(v)) ? `column-container--order-${v}` : '')),
        columnWidthClasses(data),
        backdropClasses(data),
    ].filter(Boolean).join(' ');

    return (
        <div ref={reveal.ref} className={classes} id={anchor} data-cms-block={customCssOf(data) ? blockId : undefined} data-animate={reveal.animation || undefined}>
            <BackdropLayers data={data} />
            {children}
        </div>
    );
}
