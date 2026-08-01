import { useEffect, useState } from 'react';
import { Drawer, Badge } from '../components/ui';
import { relative, exact } from '../relativeTime';

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

export default function HistoryDrawer({ open, onClose, pageTitle, onRestore, onPreview, onCompare, onLoadOlder, revisions = null }) {
    const [rows, setRows] = useState([]);
    const [compared, setCompared] = useState({});
    const [loading, setLoading] = useState(false);

    const total = revisions?.total ?? 0;

    useEffect(() => {
        setRows(revisions?.rows ?? []);
        setCompared({});
    }, [revisions]);

    const compare = async (n) => {
        setCompared((prev) => ({ ...prev, [n]: ['Comparing…'] }));

        const diff = await onCompare(n);

        setCompared((prev) => ({ ...prev, [n]: diff ? summarise(diff) : ['Could not compare that version'] }));
    };

    const loadOlder = async () => {
        setLoading(true);

        const older = await onLoadOlder(rows[rows.length - 1].n);

        setLoading(false);

        if (older?.rows?.length) setRows((prev) => [...prev, ...older.rows]);
    };

    return (
        <Drawer open={open} onClose={onClose} title="Version history" subtitle={pageTitle}>
            {rows.length === 0 && (
                <p className="cms-no-selection__body">No versions yet — publish this page to create the first snapshot.</p>
            )}
            {rows.map((v, i) => (
                <div key={v.n} className="cms-version-item">
                    <div className="cms-version-item__row">
                        <span className="cms-version-item__n">{relative(v.at)}</span>
                        {i === 0 && <Badge tone="success" small>Live</Badge>}
                    </div>
                    <div className="cms-version-item__who">{exact(v.at)} · {v.by}</div>
                    <ul className="cms-version-item__changes">
                        <li>{v.sectionCount} section{v.sectionCount === 1 ? '' : 's'}</li>
                        {(compared[v.n] || []).map((c, j) => <li key={`d${j}`}>{c}</li>)}
                    </ul>
                    <div className="cms-version-item__actions">
                        <button type="button" className="cms-btn cms-btn--xs" onClick={() => onPreview(v.n)}>Preview</button>
                        <button type="button" className="cms-btn cms-btn--xs" onClick={() => compare(v.n)}>Compare with live</button>
                        <button type="button" className="cms-btn cms-btn--primary cms-btn--xs" onClick={() => onRestore(v.n, v.at)}>Restore</button>
                    </div>
                </div>
            ))}
            {rows.length > 0 && rows.length < total && (
                <div className="cms-version-more">
                    <div className="cms-hint">Showing the {rows.length} most recent of {total} versions.</div>
                    <button type="button" className="cms-btn cms-btn--sm" disabled={loading} onClick={loadOlder}>
                        {loading ? 'Loading…' : 'Show older versions'}
                    </button>
                </div>
            )}
        </Drawer>
    );
}
