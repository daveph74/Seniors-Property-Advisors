import { useForm } from '@inertiajs/react';
import { Modal } from './ui';

function slugify(value) {
    return value
        .toLowerCase()
        .trim()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-|-$/g, '');
}

export default function CreatePageModal({ open, onClose, pages = [], layouts = [] }) {
    const { data, setData, post, processing, errors, reset, clearErrors } = useForm({
        title: '',
        parent: '',
        layout: 'blank',
    });

    const parents = pages.filter((p) => p.status !== 'archived' && p.url !== '/');
    const preview = `/${data.parent ? `${data.parent}/` : ''}${slugify(data.title) || 'new-page'}`;

    const close = () => {
        reset();
        clearErrors();
        onClose();
    };

    const submit = () => post('/cms/pages', { onSuccess: () => reset() });

    return (
        <Modal open={open} onClose={close}>
            <h3 className="cms-modal__title">Create a page</h3>
            <p className="cms-modal__lead">Give it a name and pick a starting point. The builder opens next.</p>

            <div className="cms-field">
                <label className="cms-field-label">Page name</label>
                <input
                    className="cms-input"
                    style={{ height: 38 }}
                    value={data.title}
                    onChange={(e) => setData('title', e.target.value)}
                    onKeyDown={(e) => e.key === 'Enter' && !processing && data.title.trim() && submit()}
                    placeholder="e.g. Preparing to Move"
                />
                {errors.title ? <div className="cms-field-error">{errors.title}</div> : null}
            </div>

            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 12, marginBottom: 14 }}>
                <div>
                    <label className="cms-field-label">Web address</label>
                    <div className="cms-slug-display">{preview}</div>
                    <div className="cms-hint">A preview — the final address is tidied up and kept unique.</div>
                </div>
                <div>
                    <label className="cms-field-label">Sits under</label>
                    <select
                        className="cms-select"
                        style={{ height: 38 }}
                        value={data.parent}
                        onChange={(e) => setData('parent', e.target.value)}
                    >
                        <option value="">Nothing — top level</option>
                        {parents.map((p) => (
                            <option key={p.id} value={p.url.replace(/^\//, '')}>
                                {'— '.repeat(p.depth)}{p.title}
                            </option>
                        ))}
                    </select>
                    {errors.parent ? <div className="cms-field-error">{errors.parent}</div> : null}
                </div>
            </div>

            <label className="cms-field-label" style={{ marginBottom: 7 }}>Start from</label>
            <div className="cms-template-grid">
                {layouts.map((l) => (
                    <button
                        key={l.key}
                        type="button"
                        className={`cms-template-option ${data.layout === l.key ? 'cms-template-option--active' : ''}`}
                        onClick={() => setData('layout', l.key)}
                    >
                        {l.label}
                    </button>
                ))}
            </div>

            <div className="cms-modal__actions">
                <button type="button" className="cms-btn" onClick={close}>Cancel</button>
                <button
                    type="button"
                    className="cms-btn cms-btn--primary"
                    style={{ height: 38, padding: '0 18px' }}
                    disabled={processing || !data.title.trim()}
                    onClick={submit}
                >
                    {processing ? 'Creating…' : 'Create and open builder'}
                </button>
            </div>
        </Modal>
    );
}
