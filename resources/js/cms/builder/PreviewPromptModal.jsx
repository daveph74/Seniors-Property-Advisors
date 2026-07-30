import { Modal } from '../components/ui';

export default function PreviewPromptModal({ open, onClose, onSaveAndPreview, onPreviewSaved }) {
    return (
        <Modal open={open} onClose={onClose} small>
            <h3 className="cms-modal__title">You have unsaved changes</h3>
            <p className="cms-modal__lead">Preview shows the last draft saved to the server, so your latest edits will not appear unless you save them first.</p>

            <div className="cms-modal__actions">
                <button type="button" className="cms-btn" onClick={onClose}>Cancel</button>
                <button type="button" className="cms-btn" onClick={onPreviewSaved}>Preview last saved</button>
                <button type="button" className="cms-btn cms-btn--primary" style={{ height: 36, padding: '0 18px' }} onClick={onSaveAndPreview}>Save &amp; preview</button>
            </div>
        </Modal>
    );
}
