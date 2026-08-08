import { Modal } from '../components/ui';

const LINES = [
    ['added', 'added'],
    ['removed', 'removed'],
    ['edited', 'edited'],
    ['moved', 'moved'],
];

function summarise(diff, live) {
    if (!diff) return ['Working out what changed…'];

    if (diff.unchanged) {
        return [live
            ? 'Nothing has changed since the last publish.'
            : 'No changes to publish — this puts the page on the website as it stands.'];
    }

    return LINES
        .filter(([key]) => diff[key].length)
        .map(([key, verb]) => `${diff[key].length} ${verb}: ${diff[key].slice(0, 5).join(', ')}${diff[key].length > 5 ? '…' : ''}`);
}

export default function PublishModal({ open, onClose, onConfirm, pageTitle, pageUrl, status, diff, live = true }) {
    /*
     * Unchanged only means there is nothing to do when the page is already live. A page that has
     * never been published has the same tree in both columns and therefore no diff at all, so
     * disabling on the diff alone left every seeded and scaffolded page permanently unpublishable
     * from here — the button was greyed out and gave no reason.
     */
    const nothingToDo = live && !! diff?.unchanged;

    return (
        <Modal open={open} onClose={onClose} small>
            <h3 className="cms-modal__title">Publish this page?</h3>
            <p className="cms-modal__lead">
                {live
                    ? 'Your draft will replace the live version immediately and be visible to visitors.'
                    : 'This page is not on the website yet. Publishing puts it there immediately, and anyone can read it.'}
            </p>

            <div className="cms-publish-summary">
                <div className="cms-publish-summary__row">
                    <span className="cms-publish-summary__label">Page</span>
                    <span className="cms-publish-summary__value">{pageTitle}</span>
                </div>
                <div className="cms-publish-summary__row">
                    <span className="cms-publish-summary__label">URL</span>
                    <span style={{ fontSize: 13 }}>{pageUrl}</span>
                </div>
                <div className="cms-publish-summary__row">
                    <span className="cms-publish-summary__label">Current status</span>
                    <span className="cms-badge cms-badge--info">{status}</span>
                </div>
            </div>

            <div className="cms-publish-changes-title">Changes being published</div>
            <ul className="cms-publish-changes">
                {summarise(diff, live).map((line, i) => <li key={i}>{line}</li>)}
            </ul>

            <div className="cms-modal__actions">
                <button type="button" className="cms-btn" onClick={onClose}>Cancel</button>
                <button
                    type="button"
                    className="cms-btn cms-btn--primary"
                    style={{ height: 36, padding: '0 18px' }}
                    disabled={nothingToDo}
                    onClick={onConfirm}
                >
                    Publish now
                </button>
            </div>
        </Modal>
    );
}
