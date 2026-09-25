import { useForm } from '@inertiajs/react';
import { Modal } from './ui';

export default function ImportPageModal({ open, onClose }) {
    const { data, setData, post, processing, errors, reset, clearErrors } = useForm({ file: null });

    const problems = [...new Set(Object.values(errors))];

    const close = () => {
        reset();
        clearErrors();
        onClose();
    };

    const submit = () => post('/cms/pages/import', {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => close(),
    });

    return (
        <Modal open={open} onClose={close}>
            <h3 className="cms-modal__title">Import a page</h3>
            <p className="cms-modal__lead">
                Upload a page file saved with “Download as file” on another site. It arrives as a draft,
                so nothing goes live until you publish it.
            </p>

            <div className="cms-field">
                <label className="cms-field-label" htmlFor="cms-import-file">Page file</label>
                <input
                    id="cms-import-file"
                    className="cms-input"
                    style={{ height: 'auto', padding: 8 }}
                    type="file"
                    accept=".json,application/json"
                    onChange={(e) => {
                        clearErrors();
                        setData('file', e.target.files?.[0] ?? null);
                    }}
                />
                <div className="cms-hint">
                    Images aren’t inside the file. Any this site doesn’t have yet are listed after the import.
                </div>
                {problems.slice(0, 4).map((message) => (
                    <div key={message} className="cms-field-error">{message}</div>
                ))}
            </div>

            <div className="cms-modal__actions">
                <button type="button" className="cms-btn" onClick={close}>Cancel</button>
                <button
                    type="button"
                    className="cms-btn cms-btn--primary"
                    style={{ height: 38, padding: '0 18px' }}
                    disabled={processing || !data.file}
                    onClick={submit}
                >
                    {processing ? 'Importing…' : 'Import as a draft'}
                </button>
            </div>
        </Modal>
    );
}
