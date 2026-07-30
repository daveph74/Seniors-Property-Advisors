import { useState } from 'react';
import { Drawer, Badge } from '../components/ui';

function fromRevisions(revisions) {
    return revisions.map((r, i) => ({
        n: r.n,
        tag: i === 0 ? 'live' : null,
        when: new Date(r.at).toLocaleString('en-AU'),
        who: `${r.by} · ${r.action}`,
        changes: [`${r.sections} sections published`],
    }));
}

const LINES = [
    ['added', 'added'],
    ['removed', 'removed'],
    ['edited', 'edited'],
    ['moved', 'moved'],
];

function summarise(diff) {
    if (diff.unchanged) return ['Identical to the live version'];

    return LINES
        .filter(([key]) => diff[key].length)
        .map(([key, verb]) => `${diff[key].length} ${verb}: ${diff[key].slice(0, 4).join(', ')}${diff[key].length > 4 ? '…' : ''}`);
}

export default function HistoryDrawer({ open, onClose, pageTitle, onRestore, onPreview, onCompare, revisions = null }) {
    const versions = fromRevisions(revisions ?? []);
    const [compared, setCompared] = useState({});

    const compare = async (n) => {
        setCompared((prev) => ({ ...prev, [n]: ['Comparing…'] }));

        const diff = await onCompare(n);

        setCompared((prev) => ({ ...prev, [n]: diff ? summarise(diff) : ['Could not compare that version'] }));
    };

    return (
        <Drawer open={open} onClose={onClose} title="Version history" subtitle={pageTitle}>
            {versions.length === 0 && (
                <p className="cms-no-selection__body">No versions yet — publish this page to create the first snapshot.</p>
            )}
            {versions.map((v) => (
                <div key={v.n} className="cms-version-item">
                    <div className="cms-version-item__row">
                        <span className="cms-version-item__n">Version {v.n}</span>
                        {v.tag === 'live' && <Badge tone="success" small>Live</Badge>}
                        <span className="cms-version-item__when">{v.when}</span>
                    </div>
                    <div className="cms-version-item__who">{v.who}</div>
                    <ul className="cms-version-item__changes">
                        {v.changes.map((c, i) => <li key={i}>{c}</li>)}
                        {(compared[v.n] || []).map((c, i) => <li key={`d${i}`}>{c}</li>)}
                    </ul>
                    <div className="cms-version-item__actions">
                        <button type="button" className="cms-btn cms-btn--xs" onClick={() => onPreview(v.n)}>Preview</button>
                        <button type="button" className="cms-btn cms-btn--xs" onClick={() => compare(v.n)}>Compare with live</button>
                        <button type="button" className="cms-btn cms-btn--primary cms-btn--xs" onClick={() => onRestore(v.n)}>Restore</button>
                    </div>
                </div>
            ))}
        </Drawer>
    );
}
