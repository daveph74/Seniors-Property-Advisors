export default function PendingModule({ title, waitingFor, willPull = [] }) {
    return (
        <div className="section-pending">
            <b>{title}</b>
            <p>
                Nothing to show yet — this section pulls its content from the {waitingFor},
                which is not built yet.
            </p>
            {willPull.length > 0 ? (
                <ul>
                    {willPull.map((line) => <li key={line}>{line}</li>)}
                </ul>
            ) : null}
        </div>
    );
}
