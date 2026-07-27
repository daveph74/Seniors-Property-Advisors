import { useState } from 'react';
import CmsLayout from '../../../cms/layout/CmsLayout';
import { SearchInput } from '../../../cms/components/ui';
import { useCmsToast } from '../../../cms/ToastContext';
import { MEDIA_ITEMS } from '../../../cms/data/mockData';

export default function MediaIndex() {
    const flash = useCmsToast();
    const [search, setSearch] = useState('');
    const [selected, setSelected] = useState(0);
    const [uploading, setUploading] = useState(true);

    const filtered = MEDIA_ITEMS.filter((m) => !search.trim() || m.name.toLowerCase().includes(search.trim().toLowerCase()));
    const selectedItem = MEDIA_ITEMS[selected] || MEDIA_ITEMS[0];

    return (
        <div className="cms-media-layout">
            <div className="cms-media-main">
                <div className="cms-toolbar">
                    <SearchInput value={search} onChange={(e) => setSearch(e.target.value)} placeholder="Search media" width={250} />
                    <select className="cms-select" style={{ width: 170 }}>
                        <option>All collections</option>
                        <option>Client photography</option>
                        <option>Team</option>
                        <option>Icons</option>
                    </select>
                    <select className="cms-select" style={{ width: 150 }}>
                        <option>All file types</option>
                        <option>Images</option>
                        <option>PDF</option>
                    </select>
                    <button type="button" className="cms-btn cms-btn--primary cms-spacer" onClick={() => flash('Choose files to upload')}>Upload</button>
                </div>

                {uploading && (
                    <div className="cms-upload-progress">
                        <div style={{ flex: 1 }}>
                            <div style={{ fontSize: 12.5, fontWeight: 500, marginBottom: 6 }}>Uploading garden-terrace.jpg — 68%</div>
                            <div className="cms-upload-progress__bar"><div className="cms-upload-progress__fill" style={{ width: '68%' }} /></div>
                        </div>
                        <button type="button" className="cms-btn cms-btn--xs" onClick={() => setUploading(false)}>Cancel</button>
                    </div>
                )}

                <div className="cms-media-grid">
                    {filtered.map((m) => {
                        const idx = MEDIA_ITEMS.indexOf(m);
                        return (
                            <button
                                key={m.name}
                                type="button"
                                onClick={() => setSelected(idx)}
                                className={`cms-media-item ${idx === selected ? 'cms-media-item--selected' : ''}`}
                            >
                                <div className="cms-media-item__thumb" />
                                <div className="cms-media-item__meta">
                                    <div className="cms-media-item__name">{m.name}</div>
                                    <div className="cms-media-item__sub">{m.meta}</div>
                                </div>
                            </button>
                        );
                    })}
                </div>
            </div>

            <aside className="cms-media-side">
                <div className="cms-media-side__preview" />
                <div style={{ fontSize: 14, fontWeight: 600, marginBottom: 2 }}>{selectedItem.name}</div>
                <div style={{ fontSize: 12, color: 'var(--cms-text-mid)', marginBottom: 16 }}>{selectedItem.meta} · uploaded by Helen Marsh</div>

                <div className="cms-field">
                    <label className="cms-field-label">Alternative text</label>
                    <textarea className="cms-textarea" rows={2} defaultValue="Advisor speaking with a couple in their garden" />
                </div>
                <div className="cms-field">
                    <label className="cms-field-label">Caption</label>
                    <input className="cms-input" />
                </div>

                <div className="cms-usage-warning">
                    <div className="cms-usage-warning__title">Used on 3 pages</div>
                    <div className="cms-usage-warning__body">Home, Downsizing Support, About Our Advisors. Deleting this image will leave those sections without an image.</div>
                </div>

                <div style={{ display: 'flex', gap: 8 }}>
                    <button type="button" className="cms-btn cms-btn--block" style={{ height: 34 }} onClick={() => flash('Choose a replacement image')}>Replace</button>
                    <button type="button" className="cms-btn cms-btn--danger-outline cms-btn--block" style={{ height: 34 }} onClick={() => flash('This image is used on 3 pages — review before deleting')}>Delete</button>
                </div>
            </aside>
        </div>
    );
}

MediaIndex.layout = (page) => <CmsLayout>{page}</CmsLayout>;
