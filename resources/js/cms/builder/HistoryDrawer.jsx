import { Drawer, Badge } from '../components/ui';
import { VERSIONS } from '../data/mockData';

export default function HistoryDrawer({ open, onClose, pageTitle, onRestore }) {
    return (
        <Drawer open={open} onClose={onClose} title="Version history" subtitle={pageTitle}>
            {VERSIONS.map((v) => (
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
