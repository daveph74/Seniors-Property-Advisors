import { Modal } from './ui';

export default function ConfirmModal({ open, onClose, onConfirm, title, lead, detail, confirmLabel = 'Confirm', danger }) {
    return (
        <Modal open={open} onClose={onClose} small>
            <h3 className="cms-modal__title">{title}</h3>
            {lead ? <p className="cms-modal__lead">{lead}</p> : null}

            {detail ? (
                <div className={`cms-confirm-detail ${danger ? 'cms-confirm-detail--danger' : ''}`}>{detail}</div>
            ) : null}

            <div className="cms-modal__actions">
                <button type="button" className="cms-btn" onClick={onClose}>Cancel</button>
                <button
                    type="button"
                    className={`cms-btn ${danger ? 'cms-btn--danger' : 'cms-btn--primary'}`}
                    style={{ height: 36, padding: '0 18px' }}
                    onClick={onConfirm}
                >
                    {confirmLabel}
                </button>
            </div>
        </Modal>
    );
}
