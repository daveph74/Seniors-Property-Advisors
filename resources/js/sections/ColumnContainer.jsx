export default function ColumnContainer({ anchor, children }) {
    return (
        <div className="column-container" id={anchor}>
            {children}
        </div>
    );
}
