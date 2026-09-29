import useReveal from './useReveal';

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

export default function ColumnContainer({ data = {}, anchor, editing = false, children }) {
    const reveal = useReveal(data.animation, data.animationDelay, editing);

    const classes = [
        'column-container',
        ACROSS[data.alignAcross] || '',
        DOWN[data.alignDown] || '',
        reveal.classes,
    ].filter(Boolean).join(' ');

    return (
        <div ref={reveal.ref} className={classes} id={anchor} data-animate={reveal.animation || undefined}>
            {children}
        </div>
    );
}
