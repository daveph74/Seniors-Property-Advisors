import { useState } from 'react';
import { router } from '@inertiajs/react';
import { Modal } from './ui';
import { PAGE_TEMPLATES } from '../data/mockData';

function slugify(value) {
    return value
        .toLowerCase()
        .trim()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-|-$/g, '');
}

export default function CreatePageModal({ open, onClose }) {
    const [title, setTitle] = useState('');
    const [templateIndex, setTemplateIndex] = useState(1);
    const slug = slugify(title) || 'new-page';

    const createAndOpen = () => {
        onClose();
        router.visit(`/cms/pages/${slug}/edit`);
    };

    return (
        <Modal open={open} onClose={onClose}>
            <h3 className="cms-modal__title">Create a page</h3>
            <p className="cms-modal__lead">Two quick steps, then the builder opens.</p>

            <div className="cms-field">
                <label className="cms-field-label">Page title</label>
                <input
                    className="cms-input"
                    style={{ height: 38 }}
                    value={title}
                    onChange={(e) => setTitle(e.target.value)}
                    placeholder="e.g. Preparing to Move"
                />
            </div>

            <div style={{ display: 'grid', gridTemplateColumns: '1fr 1fr', gap: 12, marginBottom: 14 }}>
                <div>
                    <label className="cms-field-label">URL slug</label>
                    <div className="cms-slug-display"><span style={{ color: 'var(--cms-text-mid)' }}>/</span>{slug}</div>
                </div>
                <div>
                    <label className="cms-field-label">Parent page</label>
                    <select className="cms-select" style={{ height: 38 }}>
                        <option>No parent (top level)</option>
                        <option>Downsizing Support</option>
                        <option>Retirement Living Advice</option>
                    </select>
                </div>
            </div>

            <label className="cms-field-label" style={{ marginBottom: 7 }}>Template</label>
            <div className="cms-template-grid">
                {PAGE_TEMPLATES.map((label, i) => (
                    <button
                        key={label}
                        type="button"
                        className={`cms-template-option ${templateIndex === i ? 'cms-template-option--active' : ''}`}
                        onClick={() => setTemplateIndex(i)}
                    >
                        {label}
                    </button>
                ))}
            </div>

            <div className="cms-modal__actions">
                <button type="button" className="cms-btn" onClick={onClose}>Cancel</button>
                <button type="button" className="cms-btn cms-btn--primary" style={{ height: 38, padding: '0 18px' }} onClick={createAndOpen}>
                    Create and open builder
                </button>
            </div>
        </Modal>
    );
}
