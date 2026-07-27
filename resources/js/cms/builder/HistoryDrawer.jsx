import { Drawer, Badge } from '../components/ui';
import { VERSIONS } from '../data/mockData';

function fromRevisions(revisions) {
    return revisions.map((r, i) => ({
        n: r.n,
        tag: i === 0 ? 'live' : null,
        when: new Date(r.at).toLocaleString('en-AU'),
        who: `${r.by} · ${r.action}`,
        changes: [`${r.sections} sections published`],
    }));
}

export default function HistoryDrawer({ open, onClose, pageTitle, onRestore, revisions = null }) {
    const versions = revisions ? fromRevisions(revisions) : VERSIONS;

    return (
        <Drawer open={open} onClose={onClose} title="Version history" subtitle={pageTitle}>
            {revisions && versions.length === 0 && (
                <p className="cms-no-selection__body">No versions yet — publish this page to create the first snapshot.</p>
            )}
            {versions.map((v) => (
                <div key={v.n} className="cms-version-item">
                    <div className="cms-version-item__row">
                        <span className="cms-version-item__n">Version {v.n}</span>
                        {v.tag === 'current' && <Badge tone="info" small>Current draft</Badge>}
                        {v.tag === 'live' && <Badge tone="success" small>Live</Badge>}
                        <span className="cms-version-item__when">{v.when}</span>
                    </div>
                    <div className="cms-version-item__who">{v.who}</div>
                    <ul className="cms-version-item__changes">
                        {v.changes.map((c, i) => <li key={i}>{c}</li>)}
                    </ul>
                    <div className="cms-version-item__actions">
                        <button type="button" className="cms-btn cms-btn--xs">Preview</button>
                        <button type="button" className="cms-btn cms-btn--xs">Compare</button>
                        <button type="button" className="cms-btn cms-btn--primary cms-btn--xs" onClick={() => onRestore(v.n)}>Restore</button>
                    </div>
                </div>
            ))}
        </Drawer>
    );
}
