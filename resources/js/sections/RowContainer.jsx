export default function RowContainer({ data = {}, anchor, children }) {
    const classes = ['row-container', data.stack === 'never' ? 'row-container--no-stack' : '']
        .filter(Boolean)
        .join(' ');

    return (
        <div className={classes} id={anchor}>
            {children}
        </div>
    );
}
