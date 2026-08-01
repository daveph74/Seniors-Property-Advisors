export default function CanvasSkeleton({ width }) {
    return (
        <div className="cms-skeleton" style={{ width }} aria-hidden="true">
            <div className="cms-skeleton__header">
                <div className="cms-skeleton__bar cms-skeleton__bar--logo" />
                <div className="cms-skeleton__nav">
                    {[0, 1, 2, 3].map((i) => <div key={i} className="cms-skeleton__bar cms-skeleton__bar--nav" />)}
                </div>
                <div className="cms-skeleton__bar cms-skeleton__bar--pill" />
            </div>

            <div className="cms-skeleton__hero">
                <div className="cms-skeleton__col">
                    <div className="cms-skeleton__bar cms-skeleton__bar--eyebrow" />
                    <div className="cms-skeleton__bar cms-skeleton__bar--title" />
                    <div className="cms-skeleton__bar cms-skeleton__bar--title cms-skeleton__bar--title-short" />
                    <div className="cms-skeleton__bar cms-skeleton__bar--text" />
                    <div className="cms-skeleton__bar cms-skeleton__bar--text cms-skeleton__bar--text-short" />
                    <div className="cms-skeleton__actions">
                        <div className="cms-skeleton__bar cms-skeleton__bar--button" />
                        <div className="cms-skeleton__bar cms-skeleton__bar--link" />
                    </div>
                </div>
                <div className="cms-skeleton__image" />
            </div>

            <div className="cms-skeleton__cards">
                {[0, 1, 2].map((i) => (
                    <div key={i} className="cms-skeleton__card">
                        <div className="cms-skeleton__bar cms-skeleton__bar--icon" />
                        <div className="cms-skeleton__bar cms-skeleton__bar--cardtitle" />
                        <div className="cms-skeleton__bar cms-skeleton__bar--text" />
                        <div className="cms-skeleton__bar cms-skeleton__bar--text cms-skeleton__bar--text-short" />
                    </div>
                ))}
            </div>
        </div>
    );
}
