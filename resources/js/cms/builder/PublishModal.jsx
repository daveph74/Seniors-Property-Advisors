import { Modal } from '../components/ui';

export default function PublishModal({ open, onClose, onConfirm, pageTitle, pageUrl }) {
    return (
        <Modal open={open} onClose={onClose} small>
            <h3 className="cms-modal__title">Publish this page?</h3>
            <p className="cms-modal__lead">Your draft will replace the live version immediately and be visible to visitors.</p>

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
                    <span className="cms-badge cms-badge--info">Live with unpublished changes</span>
                </div>
            </div>

            <div className="cms-publish-changes-title">Changes being published</div>
            <ul className="cms-publish-changes">
                <li>Hero heading and supporting text updated</li>
                <li>Statistics section added</li>
                <li>Featured testimonials reordered</li>
            </ul>

            <div className="cms-modal__actions">
                <button type="button" className="cms-btn" onClick={onClose}>Cancel</button>
                <button type="button" className="cms-btn cms-btn--primary" style={{ height: 36, padding: '0 18px' }} onClick={onConfirm}>Publish now</button>
            </div>
        </Modal>
    );
}
