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

export default function ColumnContainer({ data = {}, anchor, children }) {
    const classes = [
        'column-container',
        ACROSS[data.alignAcross] || '',
        DOWN[data.alignDown] || '',
    ].filter(Boolean).join(' ');

    return (
        <div className={classes} id={anchor}>
            {children}
        </div>
    );
}
