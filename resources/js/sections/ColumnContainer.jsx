import useReveal from './useReveal';
import { breakpointClasses } from './responsive';

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

export default function ColumnContainer({ data = {}, anchor, editing = false, children }) {
    const reveal = useReveal(data.animation, data.animationDelay, editing);

    const classes = [
        'column-container',
        ACROSS[data.alignAcross] || '',
        DOWN[data.alignDown] || '',
        reveal.classes,
        breakpointClasses(data, 'alignAcross', (v) => (v in ACROSS ? `column-container--across-${v}` : '')),
        breakpointClasses(data, 'alignDown', (v) => (v in DOWN ? `column-container--down-${v}` : '')),
        breakpointClasses(data, 'order', (v) => (ORDERS.includes(String(v)) ? `column-container--order-${v}` : '')),
    ].filter(Boolean).join(' ');

    return (
        <div ref={reveal.ref} className={classes} id={anchor} data-animate={reveal.animation || undefined}>
            {children}
        </div>
    );
}
