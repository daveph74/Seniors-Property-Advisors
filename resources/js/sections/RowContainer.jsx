export default function RowContainer({ anchor, children }) {
    return (
        <div className="row-container" id={anchor}>
            {children}
        </div>
    );
}
